<?php

namespace App\Actions\Reviews;

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Domain\Reviews\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewTaggedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;

/**
 * FR-003-23, FR-003-25: only the author may edit a review, and edited
 * content is screened again through T2's rules, exactly like a fresh
 * submission (T3).
 */
class UpdateReview
{
    public function __construct(private readonly ScreenReviewSubmission $screening) {}

    /**
     * @param  array{
     *     star_rating: int, title: string, text: string,
     *     date_of_experience: string, reference_number?: ?string,
     *     tagged_business_ids?: list<int>,
     * }  $data
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, Review $review, array $data): Review
    {
        if ($review->reviewer_id !== $actor->id) {
            throw new AuthorizationException('Only the author can edit this review.');
        }

        $starRating = ReviewFieldGuards::starRating($data['star_rating']);
        $title = ReviewFieldGuards::title($data['title']);
        $text = ReviewFieldGuards::text($data['text']);
        $dateOfExperience = ReviewFieldGuards::dateOfExperience($data['date_of_experience']);
        $referenceNumber = ReviewFieldGuards::referenceNumber($data['reference_number'] ?? null);
        // FR-003-33: the tag may be added, changed, or removed on every
        // edit — like every other field here, the edit fully replaces it
        // rather than patching it, so an omitted tag means "no tag".
        $taggedBusiness = ReviewFieldGuards::taggedBusiness($review->business, $data['tagged_business_ids'] ?? []);
        $previousTaggedBusinessId = $review->tagged_business_id;

        $outcome = $this->screening->handle($review->reviewer, $review->business, $title, $text);

        $review->update([
            'star_rating' => $starRating,
            'title' => $title,
            'text' => $text,
            'date_of_experience' => $dateOfExperience,
            'reference_number' => $referenceNumber,
            'tagged_business_id' => $taggedBusiness?->id,
            'status' => $outcome->status,
            // Keeps the review's original publish date if it already had
            // one; only sets it the first time an edit newly clears
            // screening (e.g. an edit to a previously held review).
            'published_at' => $review->published_at ?? ($outcome->status === ReviewStatus::Published ? now() : null),
            'edited_at' => now(),
        ]);

        $review = $review->fresh();

        $this->screening->record($review, $outcome);

        // FR-003-32: only notify when the tag is newly set or changed to a
        // different business, and only once the review is actually
        // visible — an edit that doesn't touch the tag never re-notifies.
        if ($outcome->status === ReviewStatus::Published && $taggedBusiness !== null && $taggedBusiness->id !== $previousTaggedBusinessId) {
            Notification::send($taggedBusiness->members(), new ReviewTaggedNotification($review));
        }

        // FR-003-30: never the tagged business (FR-003-32's zero-score-
        // effect clause) — only the business actually being reviewed.
        app(RecalculateBusinessScore::class)->handle($review->business);

        return $review;
    }
}
