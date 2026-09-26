<?php

namespace App\Console\Commands;

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;
use App\Notifications\ReviewInvitationNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * FR-005-08: "reminder (0 or 1), sent 3-7 days after an unopened
 * invitation." `reminder_sent_at` is what makes this exactly one — once
 * set, the invitation never matches this query again. Scheduled daily —
 * see routes/console.php.
 */
#[Signature('review-invitations:send-reminders')]
#[Description('Send one reminder for every sent, unopened invitation that is still eligible')]
class SendReviewInvitationReminders extends Command
{
    public function handle(): int
    {
        $remindAfterDays = (int) config('platform.invitations.reminder_after_days');
        $reminded = 0;

        ReviewInvitation::whereIn('status', [InvitationStatus::Sent, InvitationStatus::Delivered])
            ->whereNull('reminder_sent_at')
            ->where('sent_at', '<=', now()->subDays($remindAfterDays))
            ->chunkById(200, function ($invitations) use (&$reminded) {
                foreach ($invitations as $invitation) {
                    Notification::route('mail', $invitation->recipient_email)
                        ->notify(new ReviewInvitationNotification($invitation, isReminder: true));

                    $invitation->update(['reminder_sent_at' => now()]);
                    $reminded++;
                }
            });

        $this->info("Sent {$reminded} reminder(s).");

        return self::SUCCESS;
    }
}
