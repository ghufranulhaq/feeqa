<?php

namespace App\Actions\Reviews;

use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\CategoryQuestion;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Support\Reviews\ReviewText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * FR-003-02, FR-003-04 through FR-003-09: the core submission rules.
 * FR-003-14, FR-003-15: source label is set here, by the system only, and
 * is always `Organic` — `Invited`/`Redirected` both need the invitation/
 * generic-link system (005), not built yet.
 */
class SubmitReview
{
    public function __construct(private readonly ScreenReviewSubmission $screening) {}

    /**
     * @param array{
     *     star_rating: int, title: string, text: string,
     *     date_of_experience: string, reference_number?: ?string,
     *     confirmed_genuine: bool, idempotency_key?: ?string,
     *     answers?: array<string, mixed>,
     * } $data
     */
    public function handle(User $reviewer, Business $business, array $data, ?Location $location = null): Review
    {
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if (($existing = $this->existingByIdempotencyKey($reviewer, $idempotencyKey)) !== null) {
            return $existing;
        }

        if ($location !== null) {
            $this->guardLocationBelongsToBusiness($business, $location);
        }

        $this->guardConfirmation($data);
        $starRating = $this->guardStarRating($data);
        $title = $this->guardTitle($data['title']);
        $text = $this->guardText($data['text']);
        $dateOfExperience = $this->guardDateOfExperience($data['date_of_experience']);
        $referenceNumber = $this->guardReferenceNumber($data['reference_number'] ?? null);
        [$questionSetVersion, $answers] = $this->guardAnswers($business, $data['answers'] ?? []);

        $this->guardMembership($business, $reviewer);
        $this->guardOneReviewPerBusinessPer30Days($business, $reviewer);

        $outcome = $this->screening->handle($reviewer, $business, $title, $text);

        return Review::create([
            'business_id' => $business->id,
            'location_id' => $location?->id,
            'reviewer_id' => $reviewer->id,
            'status' => $outcome->status,
            'source_label' => SourceLabel::Organic,
            'star_rating' => $starRating,
            'title' => $title,
            'text' => $text,
            'date_of_experience' => $dateOfExperience,
            'reference_number' => $referenceNumber,
            'language' => 'en',
            'question_set_version' => $questionSetVersion,
            'answers' => $answers,
            'confirmed_genuine' => true,
            'idempotency_key' => $idempotencyKey,
            'published_at' => $outcome->status === ReviewStatus::Published ? now() : null,
        ]);
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
     * @param  array<string, mixed>  $data
     *
     * FR-003-02, edge case: "Rating missing or outside 1-5".
     */
    private function guardStarRating(array $data): int
    {
        $rating = $data['star_rating'] ?? null;

        if (! is_int($rating) || $rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['star_rating' => 'Choose a star rating from 1 to 5.']);
        }

        return $rating;
    }

    /**
     * FR-003-02, edge case: "Empty title or text, or only whitespace/emoji".
     */
    private function guardTitle(string $raw): string
    {
        $title = ReviewText::sanitize($raw);
        $length = mb_strlen($title);

        if ($length < 5 || $length > 100) {
            throw ValidationException::withMessages(['title' => 'The title must be between 5 and 100 characters.']);
        }

        return $title;
    }

    /**
     * FR-003-02, edge case table: "Text > 5,000 characters... Emoji count
     * toward length, but text must have >= 30 non-whitespace characters."
     */
    private function guardText(string $raw): string
    {
        $text = ReviewText::sanitize($raw);
        $length = mb_strlen($text);
        $nonWhitespaceLength = mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');

        if ($length > 5000) {
            throw ValidationException::withMessages(['text' => 'The review text must be 5,000 characters or fewer.']);
        }

        if ($nonWhitespaceLength < 30) {
            throw ValidationException::withMessages(['text' => 'The review text must have at least 30 non-whitespace characters.']);
        }

        return $text;
    }

    /**
     * FR-003-04, edge case: "Date of experience in the future or > 12
     * months ago".
     */
    private function guardDateOfExperience(string $raw): Carbon
    {
        try {
            $date = Carbon::parse($raw)->startOfDay();
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['date_of_experience' => 'Enter a valid date.']);
        }

        $today = Carbon::now()->startOfDay();

        if ($date->greaterThan($today)) {
            throw ValidationException::withMessages(['date_of_experience' => 'The date of experience cannot be in the future.']);
        }

        if ($date->lessThan($today->copy()->subMonths(12))) {
            throw ValidationException::withMessages(['date_of_experience' => 'The date of experience must be within the last 12 months.']);
        }

        return $date;
    }

    /**
     * FR-003-02: reference/order number, optional, <= 64 characters.
     */
    private function guardReferenceNumber(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (mb_strlen($value) > 64) {
            throw ValidationException::withMessages(['reference_number' => 'The reference number must be 64 characters or fewer.']);
        }

        return $value;
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
