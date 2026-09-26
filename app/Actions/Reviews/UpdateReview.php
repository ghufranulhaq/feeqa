<?php

namespace App\Actions\Reviews;

use App\Domain\Reviews\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

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

        $outcome = $this->screening->handle($review->reviewer, $review->business, $title, $text);

        $review->update([
            'star_rating' => $starRating,
            'title' => $title,
            'text' => $text,
            'date_of_experience' => $dateOfExperience,
            'reference_number' => $referenceNumber,
            'status' => $outcome->status,
            // Keeps the review's original publish date if it already had
            // one; only sets it the first time an edit newly clears
            // screening (e.g. an edit to a previously held review).
            'published_at' => $review->published_at ?? ($outcome->status === ReviewStatus::Published ? now() : null),
            'edited_at' => now(),
        ]);

        return $review->fresh();
    }
}
