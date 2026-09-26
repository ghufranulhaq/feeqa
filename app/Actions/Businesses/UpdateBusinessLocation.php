<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateBusinessLocation
{
    /**
     * @param array{
     *     name?: string, address?: array<string, mixed>, latitude?: ?float,
     *     longitude?: ?float, phone?: ?string, hours?: ?array<string, mixed>,
     * } $data
     *
     * @throws AuthorizationException
     */
    public function handle(Location $location, User $actor, array $data): Location
    {
        $business = $location->business;

        if (! $business->userCan($actor, BusinessPermission::EditProfile)) {
            throw new AuthorizationException('You cannot manage locations for this business.');
        }

        if (array_key_exists('name', $data) && $data['name'] !== $location->name) {
            $data['slug'] = Location::uniqueSlugFor($business->id, $data['name'], excludingId: $location->id);
        }

        $location->update($data);

        return $location->fresh();
    }
}
