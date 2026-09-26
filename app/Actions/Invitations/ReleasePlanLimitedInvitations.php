<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\MonthlyInvitationLimit;
use App\Models\Business;
use App\Models\ReviewInvitation;

/**
 * FR-005-20: releases invitations CreateInvitation queued under
 * `queued_reason = 'plan_limit'` once the Business has headroom again —
 * a new billing period started (the scheduled sweep, see
 * ReleaseInvitationsHeldByPlanLimit), or ChangeBusinessPlan recorded an
 * upgrade. Released oldest-first, up to whatever headroom now exists, so
 * a Business that upgraded to a limit it's still near doesn't get its
 * entire backlog back at once.
 */
class ReleasePlanLimitedInvitations
{
    public function handle(Business $business): int
    {
        $limit = MonthlyInvitationLimit::limitFor($business->plan);
        $remaining = $limit === null ? PHP_INT_MAX : max(0, $limit - MonthlyInvitationLimit::used($business));

        if ($remaining === 0) {
            return 0;
        }

        $ids = ReviewInvitation::where('business_id', $business->id)
            ->where('queued_reason', 'plan_limit')
            ->oldest('created_at')
            ->limit($remaining)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        ReviewInvitation::whereIn('id', $ids)->update(['queued_reason' => null]);

        return $ids->count();
    }
}
