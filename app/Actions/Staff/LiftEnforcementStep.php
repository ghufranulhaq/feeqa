<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-16: lifting a Consumer Warning needs a Senior Moderator and the
 * 6-month minimum; every other step can be lifted by a Moderator or above
 * at any time (a business or reviewer earning their way back off an
 * earlier, lighter rung).
 */
class LiftEnforcementStep
{
    private const CONSUMER_WARNING_MINIMUM_MONTHS = 6;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, EnforcementAction $action, string $resolutionNotes): EnforcementAction
    {
        if ($action->isLifted()) {
            throw ValidationException::withMessages(['action' => 'This enforcement step has already been lifted.']);
        }

        if ($action->step === EnforcementStep::ConsumerWarning) {
            if (! in_array($staff->staffRole(), [StaffRole::SeniorModerator, StaffRole::Admin], true)) {
                throw new AuthorizationException('Only a Senior Moderator can lift a Consumer Warning.');
            }

            if ($action->applied_at->copy()->addMonths(self::CONSUMER_WARNING_MINIMUM_MONTHS)->isFuture()) {
                throw ValidationException::withMessages(['action' => 'A Consumer Warning must stay for at least 6 months.']);
            }
        } elseif (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can lift an enforcement step.');
        }

        $action->update([
            'lifted_by' => $staff->id,
            'lifted_at' => now(),
            'lift_reason' => $resolutionNotes,
        ]);

        $subject = $action->subject;

        if ($action->step === EnforcementStep::ConsumerWarning && $subject instanceof Business) {
            $this->liftConsumerWarning($subject);
        } elseif ($action->step === EnforcementStep::AccountBlock) {
            $subject->update(['blocked_at' => null, 'blocked_reason' => null]);
        }

        ComplianceLogEntry::record(
            staff: $staff,
            action: "enforcement_{$action->step->value}_lifted",
            reasonCode: $resolutionNotes,
            target: $subject,
        );

        return $action->fresh();
    }

    /**
     * Doesn't restore `plan` — there's no billing record of what the
     * Business was on before the warning forced it to Free (017 doesn't
     * exist), so re-subscribing is left to whatever future flow 017 adds,
     * an honest gap rather than a guess.
     */
    private function liftConsumerWarning(Business $business): void
    {
        $restricted = array_values(array_diff(
            $business->restricted_features ?? [],
            [RestrictableFeature::Invitations->value, RestrictableFeature::ProfileEdits->value],
        ));

        $business->update([
            'consumer_warning_at' => null,
            'consumer_warning_reason' => null,
            'restricted_features' => $restricted ?: null,
        ]);
    }
}
