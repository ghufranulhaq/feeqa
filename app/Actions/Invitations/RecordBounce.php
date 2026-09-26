<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\InvitationStatus;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;

/**
 * FR-005-17: a hard bounce means the mailbox itself doesn't exist, which
 * is true everywhere, not just for this Business — so the resulting
 * suppression is platform-wide (`business_id` null), same as a recipient's
 * own global unsubscribe.
 */
class RecordBounce
{
    public function handle(ReviewInvitation $invitation): ReviewInvitation
    {
        InvitationSuppression::firstOrCreate([
            'business_id' => null,
            'recipient_email_hash' => $invitation->recipient_email_hash,
        ], [
            'reason' => 'hard_bounce',
        ]);

        if (! $invitation->status->isTerminal()) {
            $invitation->update(['status' => InvitationStatus::Bounced, 'bounced_at' => now()]);
        }

        return $invitation;
    }
}
