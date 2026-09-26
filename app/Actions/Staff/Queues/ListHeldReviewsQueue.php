<?php

namespace App\Actions\Staff\Queues;

use App\Domain\Reviews\ReviewStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * FR-006-11: the held-content queue — reviews `ScreenReviewSubmission`
 * (T2) sent to `held` rather than publishing or rejecting outright.
 * Oldest first, since no per-review priority/category field exists yet
 * for this queue beyond assignment (an honest gap, unlike the flags queue
 * which already has `sla_due_at` from T4).
 */
class ListHeldReviewsQueue
{
    /**
     * @return Collection<int, Review>
     *
     * @throws AuthorizationException
     */
    public function handle(User $staff, ?int $assignedTo = null): Collection
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can see the held-content queue.');
        }

        return Review::query()
            ->where('status', ReviewStatus::Held)
            ->when($assignedTo !== null, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->with(['business', 'reviewer'])
            ->oldest()
            ->get();
    }
}
