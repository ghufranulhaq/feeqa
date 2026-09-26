<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Models\Location;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeleteBusinessLocation
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Location $location, User $actor): void
    {
        if (! $location->business->userCan($actor, BusinessPermission::EditProfile)) {
            throw new AuthorizationException('You cannot manage locations for this business.');
        }

        $location->delete();
    }
}
