<?php

namespace App\Domain\Invitations;

use App\Domain\Businesses\BusinessPlan;
use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Support\Carbon;

/**
 * FR-005-20. A calendar month is a placeholder for 017's real
 * billing-period anchor — there is no subscription start date to anchor
 * to yet, so every Business resets on the 1st. `used()` excludes rows
 * already held under `queued_reason = 'plan_limit'`: they never consumed
 * a slot, so counting them here would double-charge a Business against
 * its own backlog once CreateInvitation or ReleasePlanLimitedInvitations
 * releases them.
 */
class MonthlyInvitationLimit
{
    public static function limitFor(BusinessPlan $plan): ?int
    {
        return config('platform.invitations.plan_limits.monthly_invitations.'.$plan->value);
    }

    public static function periodStart(): Carbon
    {
        return now()->startOfMonth();
    }

    public static function used(Business $business): int
    {
        return ReviewInvitation::where('business_id', $business->id)
            ->where('created_at', '>=', self::periodStart())
            ->where(function ($query) {
                $query->whereNull('queued_reason')->orWhere('queued_reason', '!=', 'plan_limit');
            })
            ->count();
    }

    public static function reached(Business $business): bool
    {
        $limit = self::limitFor($business->plan);

        return $limit !== null && self::used($business) >= $limit;
    }
}
