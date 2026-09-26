<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\EmployeeSizeBand;
use App\Domain\Businesses\EmployeeSizeBandDisputeStatus;
use App\Models\ComplianceLogEntry;
use App\Models\EmployeeSizeBandDispute;
use App\Models\User;
use App\Notifications\EmployeeSizeBandDisputeResolvedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-25 edge case: staff either uphold the current band or change it.
 */
class ResolveEmployeeSizeBandDispute
{
    /**
     * @throws AuthorizationException
     */
    public function handle(EmployeeSizeBandDispute $dispute, User $staff, bool $changeBand, ?EmployeeSizeBand $newBand = null, ?string $notes = null): void
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can resolve an employee size band dispute.');
        }

        if ($dispute->status !== EmployeeSizeBandDisputeStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This dispute has already been decided.']);
        }

        if ($changeBand) {
            if ($newBand === null) {
                throw ValidationException::withMessages(['new_band' => 'A new band is required to change it.']);
            }

            $dispute->business->update(['employee_size_band' => $newBand]);
        }

        $dispute->forceFill([
            'status' => $changeBand ? EmployeeSizeBandDisputeStatus::Changed : EmployeeSizeBandDisputeStatus::Upheld,
            'decided_by' => $staff->id,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ])->save();

        ComplianceLogEntry::record(
            staff: $staff,
            action: $changeBand ? 'employee_size_band_changed' : 'employee_size_band_upheld',
            reasonCode: $notes ?? 'dispute_resolved',
            target: $dispute->business,
        );

        $dispute->submitter->notify(new EmployeeSizeBandDisputeResolvedNotification($dispute));
    }
}
