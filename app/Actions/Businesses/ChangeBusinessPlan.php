<?php

namespace App\Actions\Businesses;

use App\Actions\Invitations\ReleasePlanLimitedInvitations;
use App\Domain\Businesses\BusinessPermission;
use App\Domain\Businesses\BusinessPlan;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-005-20's "or an upgrade is recorded" half. 017 (Plans & Billing) owns
 * pricing, checkout, and downgrade timing — this is the minimal, honest
 * stand-in spec 005's own held invitations need today: change the
 * Business's plan and release whatever now fits immediately, rather than
 * waiting for the next day's ReleaseInvitationsHeldByPlanLimit sweep. Same
 * "real mechanism, no UI yet" relationship StripeBillingDriver has with a
 * real charge.
 */
class ChangeBusinessPlan
{
    public function __construct(private readonly ReleasePlanLimitedInvitations $release) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, BusinessPlan $plan): Business
    {
        if (! $business->userCan($actor, BusinessPermission::ManageBilling)) {
            throw new AuthorizationException('You cannot change the plan for this business.');
        }

        $business->update(['plan' => $plan]);

        $this->release->handle($business);

        return $business;
    }
}
