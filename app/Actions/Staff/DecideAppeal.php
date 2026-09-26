<?php

namespace App\Actions\Staff;

use App\Actions\Businesses\RecalculateBusinessScore;
use App\Domain\Moderation\AppealableDecision;
use App\Domain\Moderation\AppealStatus;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Staff\StaffRole;
use App\Models\Appeal;
use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\Flag;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AppealDecidedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-19: the decider must be a different staff member than whoever
 * made the original call, enforced (not just documented) via
 * `AppealableDecision::deciderId()`. `overturned` reverses what it can:
 * republishes a removed/mark-not-genuine'd review and recalculates the
 * business's score, flips a flag's upheld/rejected verdict, or marks an
 * enforcement step lifted and reverses its side effects outright —
 * bypassing `LiftEnforcementStep`'s own gating (a Senior Moderator and a
 * 6-month minimum for a Consumer Warning) because an appeal being
 * overturned means staff already judged the underlying step wrong, not
 * that it ran its course.
 */
class DecideAppeal
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $staff, Appeal $appeal, AppealStatus $decision, string $decisionReason): Appeal
    {
        if (! in_array($staff->staffRole(), [StaffRole::Moderator, StaffRole::SeniorModerator, StaffRole::Admin], true)) {
            throw new AuthorizationException('Only a Moderator or above can decide an appeal.');
        }

        if (! in_array($decision, [AppealStatus::Upheld, AppealStatus::Overturned], true)) {
            throw ValidationException::withMessages(['decision' => 'An appeal can only be decided upheld or overturned.']);
        }

        if (! $appeal->isPending()) {
            throw ValidationException::withMessages(['appeal' => 'This appeal has already been decided.']);
        }

        $originalDeciderId = AppealableDecision::deciderId($appeal->appealable);

        if ($originalDeciderId !== null && $originalDeciderId === $staff->id) {
            throw new AuthorizationException('The original decision-maker cannot decide this appeal.');
        }

        if ($decision === AppealStatus::Overturned) {
            $this->reverse($appeal->appealable, $staff, $decisionReason);
        }

        $appeal->update([
            'status' => $decision,
            'decided_by' => $staff->id,
            'decided_at' => now(),
            'decision_reason' => $decisionReason,
        ]);

        ComplianceLogEntry::record(
            staff: $staff,
            action: 'appeal_'.$decision->value,
            reasonCode: $decisionReason,
            target: $appeal,
        );

        $appeal->appellant?->notify(new AppealDecidedNotification($appeal->fresh()));

        return $appeal->fresh();
    }

    private function reverse(Model $appealable, User $staff, string $decisionReason): void
    {
        match (true) {
            $appealable instanceof EnforcementAction => $this->overturnEnforcementAction($appealable, $staff, $decisionReason),
            $appealable instanceof Flag => $appealable->update([
                'status' => $appealable->status === FlagStatus::Upheld ? FlagStatus::Rejected : FlagStatus::Upheld,
            ]),
            $appealable instanceof Review => $this->reverseReviewDecision($appealable),
            default => throw ValidationException::withMessages(['appealable' => 'This decision type cannot be overturned.']),
        };
    }

    /**
     * Marks the row lifted (same columns `LiftEnforcementStep` sets) and
     * reverses its side effects, so the ladder's history shows an
     * overturned step the same way it shows a lifted one.
     */
    private function overturnEnforcementAction(EnforcementAction $action, User $staff, string $decisionReason): void
    {
        $action->update([
            'lifted_by' => $staff->id,
            'lifted_at' => now(),
            'lift_reason' => "Overturned on appeal: {$decisionReason}",
        ]);

        $action->reverseSideEffects();
    }

    private function reverseReviewDecision(Review $review): void
    {
        $decisionAction = AppealableDecision::reviewDecisionAction($review);

        if ($decisionAction === null) {
            throw ValidationException::withMessages(['appealable' => 'No moderation decision was found to overturn.']);
        }

        if (in_array($decisionAction, ['review_remove', 'review_mark_not_genuine'], true)) {
            $review->update([
                'status' => ReviewStatus::Published,
                'published_at' => $review->published_at ?? now(),
            ]);

            app(RecalculateBusinessScore::class)->handle($review->business);
        }

        // 'review_redact': the exact original text isn't stored anywhere
        // to restore — `ModerateReview::redact()` overwrites it in place.
        // An honest gap, the same shape as `LiftEnforcementStep` not
        // restoring a suspended `plan`.
    }
}
