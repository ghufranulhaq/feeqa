<?php

namespace App\Actions\Reviews;

use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * FR-003-27: any signed-in user may mark any review but their own as
 * Useful, once; tapping again removes the vote. Votes from fraud-flagged
 * accounts are meant to not count (edge case table) — pending spec 006,
 * which doesn't exist yet to flag anyone.
 */
class ToggleUsefulVote
{
    /**
     * @return bool true if the vote now exists, false if it was removed.
     */
    public function handle(User $user, Review $review): bool
    {
        if ($review->reviewer_id === $user->id) {
            throw ValidationException::withMessages([
                'review' => 'You cannot mark your own review as useful.',
            ]);
        }

        $vote = $review->usefulVotes()->where('user_id', $user->id)->first();

        if ($vote !== null) {
            $vote->delete();

            return false;
        }

        $review->usefulVotes()->create(['user_id' => $user->id]);

        return true;
    }
}
