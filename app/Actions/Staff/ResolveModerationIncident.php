<?php

namespace App\Actions\Staff;

use App\Domain\Moderation\IncidentStatus;
use App\Models\ComplianceLogEntry;
use App\Models\ModerationIncident;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-06, FR-006-11: closes an incident, lifting any active freeze.
 */
class ResolveModerationIncident
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $staff, ModerationIncident $incident, bool $confirmedFraud, string $notes): ModerationIncident
    {
        if ($staff->staffRole() === null) {
            throw new AuthorizationException('Only staff can resolve an incident.');
        }

        if (in_array($incident->status, [IncidentStatus::Resolved, IncidentStatus::Dismissed], true)) {
            throw ValidationException::withMessages(['status' => 'This incident is already closed.']);
        }

        $incident->update([
            'status' => $confirmedFraud ? IncidentStatus::Resolved : IncidentStatus::Dismissed,
            'frozen_until' => null,
            'resolved_by' => $staff->id,
            'resolved_at' => now(),
            'resolution_notes' => $notes,
        ]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: $confirmedFraud ? 'moderation_incident_resolved' : 'moderation_incident_dismissed',
            reasonCode: $notes,
            target: $incident->business,
        );

        return $incident;
    }
}
