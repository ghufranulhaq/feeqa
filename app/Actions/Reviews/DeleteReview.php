<?php

namespace App\Actions\Reviews;

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-003-24, FR-003-25: only the author may delete a review. The soft
 * delete (T1's `SoftDeletes`) removes it from public view right away —
 * every public query already excludes trashed rows by default. A
 * review's own lifecycle updates (T11) simply stop being reachable along
 * with it — the parent's own public-visibility checks already gate them.
 */
class DeleteReview
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Review $review): void
    {
        if ($review->reviewer_id !== $actor->id) {
            throw new AuthorizationException('Only the author can delete this review.');
        }

        $business = $review->business;

        $review->delete();

        // FR-003-30: never the tagged business (FR-003-32's zero-score-
        // effect clause) — only the business actually being reviewed.
        app(RecalculateBusinessScore::class)->handle($business);
    }
}
