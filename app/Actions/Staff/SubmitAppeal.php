<?php

namespace App\Actions\Staff;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Moderation\AppealableDecision;
use App\Domain\Moderation\AppealStatus;
use App\Models\Appeal;
use App\Models\Business;
use App\Models\EnforcementAction;
use App\Models\Flag;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * FR-006-18: the affected party's one shot at contesting a decision,
 * within 30 days unless staff records an override for a late exception
 * (edge cases table). "Affected party" differs by what's appealed: the
 * reviewer for their own review, the flag's filer for their own flag, and
 * the enforcement subject (a Business member or the blocked reviewer) for
 * a ladder step.
 */
class SubmitAppeal
{
    private const STATEMENT_MAX_LENGTH = 2000;

    /**
     * @param  list<string>|null  $evidencePaths
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $appellant, Model $appealable, string $statement, ?array $evidencePaths = null, bool $staffOverride = false): Appeal
    {
        if ($statement === '' || mb_strlen($statement) > self::STATEMENT_MAX_LENGTH) {
            throw ValidationException::withMessages(['statement' => 'A statement is required and must be 2,000 characters or fewer.']);
        }

        if (! $this->isAffectedParty($appellant, $appealable)) {
            throw new AuthorizationException('You are not the affected party for this decision.');
        }

        if (Appeal::query()
            ->where('appealable_type', $appealable->getMorphClass())
            ->where('appealable_id', $appealable->getKey())
            ->where('appellant_id', $appellant->id)
            ->exists()
        ) {
            throw ValidationException::withMessages(['appeal' => 'You have already appealed this decision.']);
        }

        $decidedAt = AppealableDecision::decidedAt($appealable);

        if ($decidedAt === null) {
            throw ValidationException::withMessages(['appealable' => 'No decision was found to appeal.']);
        }

        // Edge cases table: "Appeal after 30 days: reject as late, except
        // for staff override."
        if (! $staffOverride && $decidedAt->copy()->addDays(30)->isPast()) {
            throw ValidationException::withMessages(['appeal' => 'This decision is more than 30 days old and can no longer be appealed.']);
        }

        return Appeal::create([
            'appealable_type' => $appealable->getMorphClass(),
            'appealable_id' => $appealable->getKey(),
            'appellant_id' => $appellant->id,
            'statement' => $statement,
            'evidence_paths' => $evidencePaths,
            'status' => AppealStatus::Pending,
        ]);
    }

    private function isAffectedParty(User $appellant, Model $appealable): bool
    {
        return match (true) {
            $appealable instanceof Review => $appealable->reviewer_id === $appellant->id,
            $appealable instanceof Flag => $this->isFlagAppellant($appellant, $appealable),
            $appealable instanceof EnforcementAction => $this->isEnforcementAppellant($appellant, $appealable),
            default => false,
        };
    }

    private function isFlagAppellant(User $appellant, Flag $flag): bool
    {
        // Scenario 3: a business appeals its own flag being rejected —
        // reuses `FlagReviews`, the same permission `CreateFlag` (T4)
        // already gates filing the flag on.
        if ($flag->is_business_flag) {
            return $flag->business !== null && $flag->business->userCan($appellant, BusinessPermission::FlagReviews);
        }

        return $flag->reporter_id === $appellant->id;
    }

    private function isEnforcementAppellant(User $appellant, EnforcementAction $action): bool
    {
        $subject = $action->subject;

        if ($subject instanceof User) {
            return $subject->id === $appellant->id;
        }

        // No appeals-specific business permission exists yet, so any
        // member may appeal on the business's behalf — the same
        // generosity `ApplyEnforcementStep` already uses when it notifies
        // every owner rather than just one.
        return $subject instanceof Business && $subject->hasMembership($appellant);
    }
}
