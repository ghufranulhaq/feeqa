<?php

namespace App\Actions\Reviews;

use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewDraft;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * FR-003-10: server-side autosave for a signed-in user. Called repeatedly
 * as the reviewer types; each call overwrites that user's one draft for
 * this Business (or Location).
 */
class SaveReviewDraft
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(User $user, Business $business, ?Location $location, array $payload): ReviewDraft
    {
        if ($location !== null) {
            $this->guardLocationBelongsToBusiness($business, $location);
        }

        $draft = $this->existingDraft($user, $business, $location);

        if ($draft === null) {
            return ReviewDraft::create([
                'user_id' => $user->id,
                'business_id' => $business->id,
                'location_id' => $location?->id,
                'payload' => $payload,
            ]);
        }

        $draft->update(['payload' => $payload]);

        return $draft;
    }

    private function existingDraft(User $user, Business $business, ?Location $location): ?ReviewDraft
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

    private function guardLocationBelongsToBusiness(Business $business, Location $location): void
    {
        if ($location->business_id !== $business->id) {
            throw ValidationException::withMessages([
                'location' => 'That location does not belong to this business.',
            ]);
        }
    }
}
