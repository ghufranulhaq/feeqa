<?php

namespace App\Actions\Invitations;

use App\Domain\Businesses\BusinessPermission;
use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-005-12: a Business can cancel an invitation before it's sent (e.g.
 * the order was cancelled). Counted for the neutrality report (FR-005-18)
 * by simply being a real, queryable `cancelled` row — no separate tally
 * to keep in sync.
 */
class CancelInvitation
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(ReviewInvitation $invitation, User $actor, ?string $reason = null): ReviewInvitation
    {
        if (! $invitation->business->userCan($actor, BusinessPermission::SendInvitations)) {
            throw new AuthorizationException('You cannot cancel invitations for this business.');
        }

        if ($invitation->status !== InvitationStatus::Queued) {
            throw ValidationException::withMessages([
                'invitation' => 'Only a queued invitation, not yet sent, can be cancelled.',
            ]);
        }

        $invitation->update([
            'status' => InvitationStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        return $invitation;
    }
}
