<?php

namespace App\Actions\Staff;

use App\Domain\Moderation\IncidentStatus;
use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\ModerationIncident;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-006-06: "staff may freeze new reviews on the Business for up to 72
 * hours while investigating." Freezing is scoped to the incident's own
 * Business — `ScreenReviewSubmission` (T2) checks
 * `Business::hasActiveModerationFreeze()` on every new submission.
 */
class FreezeBusinessReviews
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $staff, ModerationIncident $incident, int $hours): ModerationIncident
    {
        if (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can freeze reviews.');
        }

        $maxHours = (int) config('platform.moderation.anomalies.max_freeze_hours', 72);
        $hours = min($hours, $maxHours);

        $incident->update([
            'status' => IncidentStatus::Investigating,
            'frozen_until' => now()->addHours($hours),
        ]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'business_reviews_frozen',
            reasonCode: 'anomaly_investigation',
            target: $incident->business,
        );

        return $incident;
    }
}
