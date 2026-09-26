<?php

namespace App\Actions\Staff;

use App\Domain\Moderation\GuidelineAudience;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\GuidelineVersion;
use App\Models\User;
use App\Notifications\StatementOfReasonsNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-15: the reviewer ladder's final step — "severe cases (fraud
 * rings, threats) go straight to a block," and lesser cases arrive here
 * after the ladder's educational-notice and warning steps (T6).
 */
class BlockUserAccount
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, User $target, ReasonCode $reasonCode): User
    {
        if (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can block an account.');
        }

        if ($target->isBlocked()) {
            throw ValidationException::withMessages(['user' => 'This account is already blocked.']);
        }

        $target->update([
            'blocked_at' => now(),
            'blocked_reason' => $reasonCode->value,
        ]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'user_blocked',
            reasonCode: $reasonCode->value,
            target: $target,
        );

        $target->notify(new StatementOfReasonsNotification(
            whatWasAffected: 'your account',
            reasonCode: $reasonCode,
            guidelineVersion: GuidelineVersion::query()
                ->where('audience', GuidelineAudience::Reviewer)
                ->where('is_current', true)
                ->value('version'),
            automated: false,
        ));

        return $target->fresh();
    }
}
