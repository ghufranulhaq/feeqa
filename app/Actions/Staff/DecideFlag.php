<?php

namespace App\Actions\Staff;

use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\Flag;
use App\Models\Review;
use App\Models\User;
use App\Notifications\FlagDecidedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-11: resolves a flag to `upheld` or `rejected` — not named as its
 * own line in tasks.md T5, but necessary for spec.md's own User Scenario
 * 2 ("a moderator removes the phone number ... and restores the review"):
 * `Review::isBlurred()` (T4) checks for any flag still carrying
 * `blurred`, so moving a flag off that status is what "restores" the
 * review — no separate review-side action needed. The moderator would
 * call `ModerateReview`'s `redact` verb first to actually remove the
 * offending text, then this to close the flag out.
 */
class DecideFlag
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, Flag $flag, FlagStatus $decision, ReasonCode $reasonCode, string $decisionReason): Flag
    {
        if (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can decide a flag.');
        }

        if (! in_array($decision, [FlagStatus::Upheld, FlagStatus::Rejected], true)) {
            throw ValidationException::withMessages(['decision' => 'A flag can only be decided upheld or rejected.']);
        }

        if (! $flag->isUnresolved()) {
            throw ValidationException::withMessages(['flag' => 'This flag has already been decided.']);
        }

        // Edge cases table: same conflict-of-interest rule as
        // `ModerateReview`, for the one flaggable type that has a
        // Business today.
        if ($flag->flaggable instanceof Review && $flag->flaggable->business->hasConflictWithStaff($staff)) {
            throw new AuthorizationException('You have a declared conflict of interest with this business.');
        }

        $flag->update([
            'status' => $decision,
            'decided_by' => $staff->id,
            'decided_at' => now(),
            'decision_reason' => $decisionReason,
        ]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'flag_'.$decision->value,
            reasonCode: $reasonCode->value,
            target: $flag,
        );

        if ($flag->reporter !== null) {
            $flag->reporter->notify(new FlagDecidedNotification($flag));
        } elseif ($flag->reporter_email !== null) {
            Notification::route('mail', $flag->reporter_email)->notify(new FlagDecidedNotification($flag));
        }

        return $flag->fresh();
    }
}
