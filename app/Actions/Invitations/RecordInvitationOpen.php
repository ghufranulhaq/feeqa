<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;

/**
 * FR-005-11: the 1-pixel open tracker. There is no delivery webhook (plan
 * D14 — no real mail infrastructure), so an open is the earliest signal we
 * ever get that the message actually arrived — it backfills `delivered_at`
 * too rather than leaving it permanently null. Never moves a terminal or
 * already-further-along invitation backwards.
 */
class RecordInvitationOpen
{
    public function handle(ReviewInvitation $invitation): ReviewInvitation
    {
        if (! in_array($invitation->status, [InvitationStatus::Sent, InvitationStatus::Delivered], true)) {
            return $invitation;
        }

        $invitation->update([
            'delivered_at' => $invitation->delivered_at ?? now(),
            'opened_at' => $invitation->opened_at ?? now(),
            'status' => InvitationStatus::Opened,
        ]);

        return $invitation;
    }
}
