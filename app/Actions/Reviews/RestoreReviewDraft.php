<?php

namespace App\Actions\Reviews;

use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewDraft;
use App\Models\User;

/**
 * FR-003-10: looks up the signed-in user's saved draft for this Business
 * (or Location), so the submission form can restore it — including right
 * after the user signs in part-way through writing a review.
 */
class RestoreReviewDraft
{
    public function handle(User $user, Business $business, ?Location $location): ?ReviewDraft
    {
        return ReviewDraft::where('user_id', $user->id)
            ->where('business_id', $business->id)
            ->when(
                $location === null,
                fn ($query) => $query->whereNull('location_id'),
                fn ($query) => $query->where('location_id', $location->id),
            )
            ->first();
    }
}
