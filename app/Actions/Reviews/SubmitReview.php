<?php

namespace App\Actions\Reviews;

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Actions\Verification\IssueTransactionInvitationAttestation;
use App\Domain\Invitations\InvitationStatus;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\CategoryQuestion;
use App\Models\Location;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Models\User;
use App\Notifications\ReviewTaggedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * FR-003-02, FR-003-04 through FR-003-09: the core submission rules.
 * FR-003-14, FR-003-15, FR-005-02: source label is `Organic` unless an
 * optional invitation token is supplied, in which case it comes from the
 * invitation's own method (005) — `Invited` for bcc/integration/api/csv/
 * manual, `Redirected` for `link`. Edge cases table: a `closed` Business
 * (002) still accepts reviews until 12 months after its closure date
 * (`Business::acceptsNewReviews()`).
 */
class SubmitReview
{
    public function __construct(private readonly ScreenReviewSubmission $screening) {}

    /**
     * @param array{
     *     star_rating: int, title: string, text: string,
     *     date_of_experience: string, reference_number?: ?string,
     *     confirmed_genuine: bool, idempotency_key?: ?string,
     *     answers?: array<string, mixed>, tagged_business_ids?: list<int>,
     * } $data
     */
    public function handle(User $reviewer, Business $business, array $data, ?Location $location = null, ?string $invitationToken = null): Review
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if (($existing = $this->existingByIdempotencyKey($reviewer, $idempotencyKey)) !== null) {
            return $existing;
        }

        if ($location !== null) {
            $this->guardLocationBelongsToBusiness($business, $location);
        }

        $invitation = $this->resolveInvitation($invitationToken, $business);

        $this->guardConfirmation($data);
        $starRating = ReviewFieldGuards::starRating($data['star_rating']);
        $title = ReviewFieldGuards::title($data['title']);
        $text = ReviewFieldGuards::text($data['text']);
        $dateOfExperience = ReviewFieldGuards::dateOfExperience($data['date_of_experience']);
        $referenceNumber = ReviewFieldGuards::referenceNumber($data['reference_number'] ?? null);
        [$questionSetVersion, $answers] = $this->guardAnswers($business, $data['answers'] ?? []);
        $taggedBusiness = ReviewFieldGuards::taggedBusiness($business, $data['tagged_business_ids'] ?? []);

        $this->guardMembership($business, $reviewer);
        $this->guardBusinessAcceptsReviews($business);
        $this->guardOneReviewPerBusinessPer30Days($business, $reviewer);

        $outcome = $this->screening->handle($reviewer, $business, $title, $text);

        $review = Review::create([
            'business_id' => $business->id,
            'location_id' => $location?->id,
            'reviewer_id' => $reviewer->id,
            'status' => $outcome->status,
            'source_label' => $invitation?->method->sourceLabel() ?? SourceLabel::Organic,
            'star_rating' => $starRating,
            'title' => $title,
            'text' => $text,
            'date_of_experience' => $dateOfExperience,
            'reference_number' => $referenceNumber,
            'language' => 'en',
            'question_set_version' => $questionSetVersion,
            'answers' => $answers,
            'tagged_business_id' => $taggedBusiness?->id,
            'confirmed_genuine' => true,
            'idempotency_key' => $idempotencyKey,
            'published_at' => $outcome->status === ReviewStatus::Published ? now() : null,
        ]);

        if ($outcome->status === ReviewStatus::Published && $taggedBusiness !== null) {
            Notification::send($taggedBusiness->members(), new ReviewTaggedNotification($review));
        }

        if ($invitation !== null) {
            $invitation->update(['status' => InvitationStatus::Reviewed, 'reviewed_at' => now(), 'review_id' => $review->id]);

            if ($invitation->method->isTransactionLinked() && $invitation->reference !== null) {
                app(IssueTransactionInvitationAttestation::class)->handle($business, $review, $invitation->reference);
            }
        }

        // FR-003-30: never the tagged business (FR-003-32's zero-score-
        // effect clause) — only the business actually being reviewed.
        app(RecalculateBusinessScore::class)->handle($business);

        return $review;
    }

    /**
     * FR-005-02: a token identifies which invitation this review closes
     * out, if any — resolved before the review exists so its source_label
     * can be set at creation, not patched in afterwards.
     */
    private function resolveInvitation(?string $token, Business $business): ?ReviewInvitation
    {
        if ($token === null) {
            return null;
        }

        $invitation = ReviewInvitation::where('token', $token)->first();

        if ($invitation === null) {
            throw ValidationException::withMessages(['invitation_token' => 'That invitation link is not valid.']);
        }

        if ($invitation->business_id !== $business->id) {
            throw ValidationException::withMessages(['invitation_token' => 'That invitation is for a different business.']);
        }

        if ($invitation->review_id !== null) {
            throw ValidationException::withMessages(['invitation_token' => 'That invitation has already been used.']);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['invitation_token' => 'That invitation has expired.']);
        }

        return $invitation;
    }

    private function guardLocationBelongsToBusiness(Business $business, Location $location): void
    {
        if ($location->business_id !== $business->id) {
            throw ValidationException::withMessages([
                'location' => 'That location does not belong to this business.',
            ]);
        }
    }

    /**
     * FR-003-05: the form shows the question set for the Business's
     * primary category. Locations have no category of their own in the
     * current schema (spec 002 never added one), so "the Location's
     * category, if set" is a pending hook — this always falls back to the
     * Business's primary category.
     *
     * @param  array<string, mixed>  $rawAnswers
     * @return array{0: ?int, 1: ?array<string, mixed>}
     */
    private function guardAnswers(Business $business, array $rawAnswers): array
    {
        $category = $business->primaryCategory;

        if ($category === null) {
            return [null, null];
        }

        $questions = $category->effectiveQuestions();
        $answers = [];

        /** @var CategoryQuestion $question */
        foreach ($questions as $question) {
            $value = $rawAnswers[$question->key] ?? null;

            if ($value === null) {
                if ($question->required) {
                    throw ValidationException::withMessages([
                        "answers.{$question->key}" => "Please answer \"{$question->localisedLabel()}\".",
                    ]);
                }

                continue;
            }

            $answers[$question->key] = $value;
        }

        $knownKeys = $questions->pluck('key')->all();

        foreach (array_diff(array_keys($rawAnswers), $knownKeys) as $unknownKey) {
            Log::info('Dropped a review answer for a question not in the effective set', [
                'business_id' => $business->id,
                'category_id' => $category->id,
                'question_key' => $unknownKey,
            ]);
        }

        return [$category->currentQuestionSet()?->version, $answers === [] ? null : $answers];
    }

    private function existingByIdempotencyKey(User $reviewer, ?string $key): ?Review
    {
        if ($key === null || $key === '') {
            return null;
        }

        return Review::where('reviewer_id', $reviewer->id)
            ->where('idempotency_key', $key)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * FR-003-06.
     */
    private function guardConfirmation(array $data): void
    {
        if (($data['confirmed_genuine'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'confirmed_genuine' => 'You must confirm this describes your own genuine experience.',
            ]);
        }
    }

    /**
     * FR-003-07.
     */
    private function guardMembership(Business $business, User $reviewer): void
    {
        if ($business->hasMembership($reviewer)) {
            throw ValidationException::withMessages([
                'business' => 'You cannot review a business you are a member of.',
            ]);
        }
    }

    /**
     * Edge cases table: "Review of a closed Business: allowed until 12
     * months after the closure date."
     */
    private function guardBusinessAcceptsReviews(Business $business): void
    {
        if (! $business->acceptsNewReviews()) {
            throw ValidationException::withMessages([
                'business' => 'This business closed more than 12 months ago and can no longer be reviewed.',
            ]);
        }
    }

    /**
     * FR-003-08's base rule. The different-invitation/verified-transaction
     * exception (004/005) is not built yet.
     */
    private function guardOneReviewPerBusinessPer30Days(Business $business, User $reviewer): void
    {
        $recent = Review::where('reviewer_id', $reviewer->id)
            ->where('business_id', $business->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'business' => 'You already reviewed this business in the last 30 days.',
            ]);
        }
    }
}
