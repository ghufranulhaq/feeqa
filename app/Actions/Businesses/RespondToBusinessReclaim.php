<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\ClaimStatus;
use App\Models\BusinessClaim;
use App\Models\User;
use App\Notifications\BusinessClaimRejectedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * FR-002-14: an existing Owner approving or rejecting a verified
 * re-claim request.
 */
class RespondToBusinessReclaim
{
    /**
     * @throws AuthorizationException
     */
    public function handle(BusinessClaim $claim, User $owner, bool $approve): void
    {
        $business = $claim->business;

        if (! $business->hasBusinessRole($owner, BusinessRole::Owner)) {
            throw new AuthorizationException('Only an Owner can respond to this request.');
        }

        if ($claim->status !== ClaimStatus::AwaitingOwnerResponse) {
            throw ValidationException::withMessages(['status' => 'This request is no longer awaiting a response.']);
        }

        if ($approve) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
            $claim->claimant->assignRole(BusinessRole::Owner->value);
        }

        $claim->forceFill([
            'status' => $approve ? ClaimStatus::Approved : ClaimStatus::Rejected,
            'reviewed_by' => $owner->id,
            'reviewed_at' => now(),
        ])->save();

        if (! $approve) {
            $claim->claimant->notify(new BusinessClaimRejectedNotification($claim));
        }
    }
}
