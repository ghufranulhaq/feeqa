<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\BusinessPlan;
use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\EnforcementLadder;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\GuidelineAudience;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\GuidelineVersion;
use App\Models\User;
use App\Notifications\StatementOfReasonsNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-14, FR-006-15: records one rung of a ladder and applies its side
 * effects. Doesn't delegate to T5's `BlockUserAccount`/`RestrictBusinessFeature`
 * for the `account_block`/`feature_restriction` steps — each of those runs
 * its own full compliance-log-and-notify cycle for one ad hoc decision,
 * while a ladder step is one decision that can touch several columns at
 * once, so this inlines the same mutations and logs exactly once, "every
 * step call is a ModerateReview-style wrapper" (tasks.md).
 */
class ApplyEnforcementStep
{
    /**
     * FR-006-14 step 4's own parenthetical: which features a business
     * ladder's feature-restriction step switches off. Flagging is
     * deliberately left out — FR-006-16's "reply/flag-only access" for the
     * later Consumer Warning step implies flagging survives a business
     * ladder restriction too.
     *
     * @var list<RestrictableFeature>
     */
    private const FEATURES_RESTRICTED_AT_STEP_4 = [RestrictableFeature::Invitations, RestrictableFeature::ProfileEdits];

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, Model $subject, EnforcementStep $step, ReasonCode $reasonCode, ?User $seniorApprover = null): EnforcementAction
    {
        if (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can apply an enforcement step.');
        }

        $ladder = $this->ladderFor($subject);
        $sequence = EnforcementStep::sequenceFor($ladder);

        if (! in_array($step, $sequence, true)) {
            throw ValidationException::withMessages(['step' => "This step doesn't belong to the {$ladder->value} ladder."]);
        }

        if ($subject instanceof Business && $subject->hasConflictWithStaff($staff)) {
            throw new AuthorizationException('You have a declared conflict of interest with this business.');
        }

        $currentIndex = $this->highestActiveStepIndex($subject, $sequence);
        $targetIndex = array_search($step, $sequence, true);

        // "Staff may skip steps for severe or proven fraud ... with Senior
        // Moderator approval."
        if ($targetIndex > $currentIndex + 1) {
            if ($seniorApprover === null || ! in_array($seniorApprover->staffRole(), [StaffRole::SeniorModerator, StaffRole::Admin], true)) {
                throw new AuthorizationException('Skipping a ladder step needs Senior Moderator approval.');
            }
        }

        $action = EnforcementAction::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'ladder' => $ladder,
            'step' => $step,
            'reason_code' => $reasonCode,
            'applied_by' => $staff->id,
            'applied_at' => now(),
            'senior_approved_by' => $seniorApprover?->id,
        ]);

        match ($step) {
            EnforcementStep::FeatureRestriction => $subject instanceof Business ? $this->restrictLadderFeatures($subject) : null,
            EnforcementStep::ConsumerWarning => $subject instanceof Business ? $this->applyConsumerWarning($subject, $reasonCode) : null,
            EnforcementStep::AccountBlock => $subject->update(['blocked_at' => now(), 'blocked_reason' => $reasonCode->value]),
            default => null,
        };

        ComplianceLogEntry::record(
            staff: $staff,
            action: "enforcement_{$step->value}_applied",
            reasonCode: $reasonCode->value,
            target: $subject,
        );

        $notification = new StatementOfReasonsNotification(
            whatWasAffected: $subject instanceof Business ? 'your business' : 'your account',
            reasonCode: $reasonCode,
            guidelineVersion: GuidelineVersion::query()
                ->where('audience', $subject instanceof Business ? GuidelineAudience::Business : GuidelineAudience::Reviewer)
                ->where('is_current', true)
                ->value('version'),
            automated: false,
        );

        // Business has no Notifiable trait of its own — same "notify every
        // member with a role" pattern `RestrictBusinessFeature` (T5) uses.
        if ($subject instanceof Business) {
            foreach ($subject->owners() as $owner) {
                $owner->notify($notification);
            }
        } elseif ($subject instanceof User) {
            $subject->notify($notification);
        }

        return $action;
    }

    private function ladderFor(Model $subject): EnforcementLadder
    {
        return match (true) {
            $subject instanceof Business => EnforcementLadder::Business,
            $subject instanceof User => EnforcementLadder::Reviewer,
            default => throw ValidationException::withMessages(['subject' => 'This subject type has no enforcement ladder.']),
        };
    }

    /**
     * @param  list<EnforcementStep>  $sequence
     */
    private function highestActiveStepIndex(Model $subject, array $sequence): int
    {
        $appliedSteps = EnforcementAction::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->whereNull('lifted_at')
            ->get('step')
            ->pluck('step');

        $indexes = $appliedSteps
            ->map(fn (EnforcementStep $step) => array_search($step, $sequence, true))
            ->filter(fn ($index) => $index !== false);

        return $indexes->isEmpty() ? -1 : $indexes->max();
    }

    private function restrictLadderFeatures(Business $business): void
    {
        $restricted = $business->restricted_features ?? [];

        foreach (self::FEATURES_RESTRICTED_AT_STEP_4 as $feature) {
            if (! in_array($feature->value, $restricted, true)) {
                $restricted[] = $feature->value;
            }
        }

        $business->update(['restricted_features' => $restricted]);
    }

    /**
     * FR-006-16: the banner fact, hidden scores (via `trustSignalsHidden()`),
     * "reply/flag-only access" (every feature restricted except flagging),
     * and paid features suspended — this repo's only Business-plan
     * machinery today is the `plan` column itself, so "suspended" means
     * forced down to Free until the warning is lifted.
     */
    private function applyConsumerWarning(Business $business, ReasonCode $reasonCode): void
    {
        $restricted = $business->restricted_features ?? [];

        foreach (RestrictableFeature::cases() as $feature) {
            if ($feature !== RestrictableFeature::Flagging && ! in_array($feature->value, $restricted, true)) {
                $restricted[] = $feature->value;
            }
        }

        $business->update([
            'consumer_warning_at' => now(),
            'consumer_warning_reason' => $reasonCode->value,
            'restricted_features' => $restricted,
            'plan' => BusinessPlan::Free,
        ]);
    }
}
