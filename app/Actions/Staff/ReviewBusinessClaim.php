<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\ClaimStatus;
use App\Models\BusinessClaim;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use App\Notifications\BusinessClaimRejectedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * FR-002-11(d) (manual review) and FR-002-14's staff-escalation path.
 */
class ReviewBusinessClaim
{
    /**
     * @throws AuthorizationException
     */
    public function handle(BusinessClaim $claim, User $staff, bool $approve, ?string $notes = null): void
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can review this claim.');
        }

        if (! in_array($claim->status, [ClaimStatus::Pending, ClaimStatus::EscalatedToStaff], true)) {
            throw ValidationException::withMessages(['status' => 'This claim is not awaiting staff review.']);
        }

        $business = $claim->business;

        if ($approve) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
            $claim->claimant->assignRole(BusinessRole::Owner->value);

            if ($business->status !== BusinessStatus::Claimed) {
                $business->update(['status' => BusinessStatus::Claimed, 'claimed_at' => now()]);
            }
        }

        $claim->forceFill([
            'status' => $approve ? ClaimStatus::Approved : ClaimStatus::Rejected,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        ComplianceLogEntry::record(
            staff: $staff,
            action: $approve ? 'business_claim_approved' : 'business_claim_rejected',
            reasonCode: $notes ?? ($approve ? 'approved' : 'rejected'),
            target: $business,
        );

        if (! $approve) {
            $claim->claimant->notify(new BusinessClaimRejectedNotification($claim));
        }
    }
}
