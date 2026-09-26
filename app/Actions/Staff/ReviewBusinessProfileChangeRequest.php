<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\ProfileChangeRequestStatus;
use App\Models\BusinessProfileChangeRequest;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use App\Notifications\BusinessProfileChangeRejectedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-002-07. Any staff role may review one of these — the spec doesn't
 * name a specific role, unlike FR-002-33's industry permissions — a
 * finer-grained queue/role split can follow with spec 006's moderation
 * console if the client asks for one.
 */
class ReviewBusinessProfileChangeRequest
{
    /**
     * @throws AuthorizationException
     */
    public function handle(BusinessProfileChangeRequest $request, User $staff, bool $approve, ?string $notes = null): void
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can review a profile change request.');
        }

        if ($request->status !== ProfileChangeRequestStatus::Pending) {
            throw ValidationException::withMessages(['status' => 'This request has already been reviewed.']);
        }

        if ($approve) {
            $request->business->update(array_map(
                fn (array $change) => $change['new'],
                $request->changes,
            ));
        }

        $request->forceFill([
            'status' => $approve ? ProfileChangeRequestStatus::Approved : ProfileChangeRequestStatus::Rejected,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        ComplianceLogEntry::record(
            staff: $staff,
            action: $approve ? 'business_profile_change_approved' : 'business_profile_change_rejected',
            reasonCode: $notes ?? ($approve ? 'approved' : 'rejected'),
            target: $request->business,
        );

        if (! $approve) {
            $request->requester->notify(new BusinessProfileChangeRejectedNotification($request));
        }
    }
}
