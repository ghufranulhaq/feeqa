<?php

namespace App\Actions\Businesses;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\EmployeeSizeBand;
use App\Models\Business;
use App\Models\EmployeeSizeBandDispute;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-002-25 edge case: "It submits evidence. Staff decide within 7 days.
 * Until then, the current band stays" — this never touches
 * businesses.employee_size_band itself.
 */
class DisputeEmployeeSizeBand
{
    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, string $evidence, ?EmployeeSizeBand $proposedBand = null): EmployeeSizeBandDispute
    {
        if (! $business->userCan($actor, BusinessPermission::EditProfile)) {
            throw new AuthorizationException('You cannot dispute this business\'s employee size band.');
        }

        return EmployeeSizeBandDispute::create([
            'business_id' => $business->id,
            'submitted_by' => $actor->id,
            'evidence' => $evidence,
            'proposed_band' => $proposedBand,
        ]);
    }
}
