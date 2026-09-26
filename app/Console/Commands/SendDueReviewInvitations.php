<?php

namespace App\Console\Commands;

use App\Domain\Invitations\InvitationStatus;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;
use App\Notifications\ReviewInvitationNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * FR-005-08, FR-005-09: `queued` → `sent` through the configured mailer
 * (Mailpit locally/demo). Re-checks suppression at send time, not only at
 * `CreateInvitation`'s own creation-time check — a recipient can unsubscribe
 * or hard-bounce after an invitation was already queued for them. A
 * newly-suppressed invitation is never sent, just moved straight to
 * `suppressed` instead (never silently dropped). Scheduled daily — see
 * routes/console.php.
 */
#[Signature('review-invitations:send-due')]
#[Description('Send every queued review invitation whose scheduled time has arrived')]
class SendDueReviewInvitations extends Command
{
    public function handle(): int
    {
        $sent = 0;
        $suppressed = 0;

        ReviewInvitation::where('status', InvitationStatus::Queued)
            ->where('scheduled_at', '<=', now())
            ->with('business')
            ->chunkById(200, function ($invitations) use (&$sent, &$suppressed) {
                foreach ($invitations as $invitation) {
                    if (InvitationSuppression::suppresses($invitation->business_id, $invitation->recipient_email_hash)) {
                        $invitation->update(['status' => InvitationStatus::Suppressed]);
                        $suppressed++;

                        continue;
                    }

                    Notification::route('mail', $invitation->recipient_email)
                        ->notify(new ReviewInvitationNotification($invitation));

                    $invitation->update(['status' => InvitationStatus::Sent, 'sent_at' => now()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} invitation(s), suppressed {$suppressed} at send time.");

        return self::SUCCESS;
    }
}
