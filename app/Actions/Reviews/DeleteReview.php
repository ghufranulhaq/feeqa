<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-003-24, FR-003-25: only the author may delete a review. The soft
 * delete (T1's `SoftDeletes`) removes it from public view right away —
 * every public query already excludes trashed rows by default. Lifecycle
 * updates aren't cascaded here since none can exist yet (T11 not built).
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

        $review->delete();
    }
}
