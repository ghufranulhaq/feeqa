<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-16: "A claimed Business may define Locations."
 */
class CreateBusinessLocation
{
    /**
     * @param array{
     *     name: string, address: array<string, mixed>, latitude?: ?float,
     *     longitude?: ?float, phone?: ?string, hours?: ?array<string, mixed>,
     * } $data
     *
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, array $data): Location
    {
        if (! $business->userCan($actor, BusinessPermission::EditProfile)) {
            throw new AuthorizationException('You cannot manage locations for this business.');
        }

        if ($business->status !== BusinessStatus::Claimed) {
            throw ValidationException::withMessages(['business' => 'Only a claimed business can add locations.']);
        }

        return $business->locations()->create([
            ...$data,
            'slug' => Location::uniqueSlugFor($business->id, $data['name']),
        ]);
    }
}
