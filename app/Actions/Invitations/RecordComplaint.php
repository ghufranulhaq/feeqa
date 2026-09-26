<?php

namespace App\Actions\Invitations;

use App\Domain\Invitations\InvitationStatus;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;

/**
 * FR-005-17: a spam complaint is a stronger signal than a per-Business
 * unsubscribe click would be — the recipient didn't just decline this
 * Business, they reported the message — so it suppresses platform-wide,
 * same as `RecordBounce`.
 */
class RecordComplaint
{
    public function handle(ReviewInvitation $invitation): ReviewInvitation
    {
        InvitationSuppression::firstOrCreate([
            'business_id' => null,
            'recipient_email_hash' => $invitation->recipient_email_hash,
        ], [
            'reason' => 'spam_complaint',
        ]);

        if (! $invitation->status->isTerminal()) {
            $invitation->update(['status' => InvitationStatus::Complained, 'complained_at' => now()]);
        }

        return $invitation;
    }
}
