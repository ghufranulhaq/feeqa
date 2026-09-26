<?php

namespace App\Domain\Moderation;

use App\Models\ComplianceLogEntry;
use App\Models\EnforcementAction;
use App\Models\Flag;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-18's 30-day clock and FR-006-19's "different staff member" check
 * both need to know when a decision was made and who made it — and that
 * lookup differs by what's being appealed, since only `EnforcementAction`
 * and `Flag` carry their own decision columns. `Review` has neither
 * (`ModerateReview` never added one), so its decision is read back from
 * the compliance log entry `ModerateReview` itself already writes — the
 * same record `StatementOfReasonsNotification` relies on existing.
 */
class AppealableDecision
{
    /**
     * @var list<string>
     */
    private const REVIEW_DECISION_ACTIONS = ['review_remove', 'review_mark_not_genuine', 'review_redact'];

    /**
     * @throws ValidationException
     */
    public static function decidedAt(Model $appealable): ?Carbon
    {
        return match (true) {
            $appealable instanceof EnforcementAction => $appealable->applied_at,
            $appealable instanceof Flag => $appealable->decided_at,
            $appealable instanceof Review => self::reviewDecisionLogEntry($appealable)?->occurred_at,
            default => throw ValidationException::withMessages(['appealable' => 'This decision type cannot be appealed.']),
        };
    }

    /**
     * @throws ValidationException
     */
    public static function deciderId(Model $appealable): ?int
    {
        return match (true) {
            $appealable instanceof EnforcementAction => $appealable->applied_by,
            $appealable instanceof Flag => $appealable->decided_by,
            $appealable instanceof Review => self::reviewDecisionLogEntry($appealable)?->staff_id,
            default => throw ValidationException::withMessages(['appealable' => 'This decision type cannot be appealed.']),
        };
    }

    /**
     * Which `ModerateReview` verb produced the review's current state —
     * `DecideAppeal` needs this to know whether "overturned" means
     * republishing the review or (for `redact`) nothing it can actually
     * undo.
     */
    public static function reviewDecisionAction(Review $review): ?string
    {
        return self::reviewDecisionLogEntry($review)?->action;
    }

    private static function reviewDecisionLogEntry(Review $review): ?ComplianceLogEntry
    {
        return ComplianceLogEntry::query()
            ->where('target_type', $review->getMorphClass())
            ->where('target_id', $review->id)
            ->whereIn('action', self::REVIEW_DECISION_ACTIONS)
            ->latest('occurred_at')
            ->first();
    }
}
