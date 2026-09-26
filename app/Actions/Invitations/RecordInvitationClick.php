<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;

/**
 * FR-005-11: the redirecting click-through link. A click can arrive
 * before any open-pixel hit (image blocking is common), so this backfills
 * `delivered_at`/`opened_at` too rather than requiring `RecordInvitationOpen`
 * to have run first. Never moves a terminal invitation backwards.
 */
class RecordInvitationClick
{
    public function handle(ReviewInvitation $invitation): ReviewInvitation
    {
        if (! in_array($invitation->status, [InvitationStatus::Sent, InvitationStatus::Delivered, InvitationStatus::Opened], true)) {
            return $invitation;
        }

        $invitation->update([
            'delivered_at' => $invitation->delivered_at ?? now(),
            'opened_at' => $invitation->opened_at ?? now(),
            'clicked_at' => now(),
            'status' => InvitationStatus::Clicked,
        ]);

        return $invitation;
    }
}
