<?php

namespace App\Actions\Invitations;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Invitations\InvitationMethod;
use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * FR-005-01 (manual): a single entry form on the business dashboard. The
 * simplest of this spec's methods — no batch parsing, no external
 * channel — so it's the first one built on top of T3's shared engine.
 */
class RequestManualInvitation
{
    public function __construct(private readonly CreateInvitation $createInvitation) {}

    /**
     * @param  array{recipient_email: string, recipient_name?: ?string, locale?: string, reference?: ?string}  $data
     *
     * @throws AuthorizationException
     */
    public function handle(Business $business, User $actor, array $data, ?Location $location = null): ReviewInvitation
    {
        if (! $business->userCan($actor, BusinessPermission::SendInvitations)) {
            throw new AuthorizationException('You cannot send invitations for this business.');
        }

        return $this->createInvitation->handle($business, InvitationMethod::Manual, $data, $location, $actor);
    }
}
