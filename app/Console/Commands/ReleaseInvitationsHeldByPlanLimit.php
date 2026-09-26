<?php

namespace App\Console\Commands;

use App\Actions\Invitations\ReleasePlanLimitedInvitations;
use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-005-20's "released ... once the next period starts": a new calendar
 * month gives every Business fresh headroom (MonthlyInvitationLimit
 * resets on the 1st), so this just re-checks every Business that's still
 * holding a `plan_limit` invitation and releases what now fits.
 * ChangeBusinessPlan calls the same release immediately for an upgrade —
 * this sweep is the "otherwise, catch it within a day" backstop.
 * Scheduled daily — see routes/console.php.
 */
#[Signature('review-invitations:release-plan-limited')]
#[Description('Release invitations held under queued_reason=plan_limit once headroom exists')]
class ReleaseInvitationsHeldByPlanLimit extends Command
{
    public function handle(ReleasePlanLimitedInvitations $release): int
    {
        $released = 0;

        $businessIds = ReviewInvitation::where('queued_reason', 'plan_limit')->distinct()->pluck('business_id');

        foreach (Business::whereIn('id', $businessIds)->cursor() as $business) {
            $released += $release->handle($business);
        }

        $this->info("Released {$released} invitation(s) previously held by a plan limit.");

        return self::SUCCESS;
    }
}
