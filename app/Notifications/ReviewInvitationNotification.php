<?php

namespace App\Notifications;

use App\Actions\Invitations\ResolveInvitationTemplate;
use App\Domain\Invitations\RenderInvitationTemplate;
use App\Models\ReviewInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-005-08, FR-005-13: on-demand (routed straight to the invitation's own
 * recipient email, `SendDueReviewInvitations`'s only caller) rather than
 * `->notify()` on a `User`, since almost every recipient has no Platform
 * account. The body is the Business's own neutral template, rendered
 * through `RenderInvitationTemplate` — never anything this class invents.
 */
class ReviewInvitationNotification extends Notification
{
    public function __construct(
        private readonly ReviewInvitation $invitation,
        private readonly bool $isReminder = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resolved = app(ResolveInvitationTemplate::class)->handle(
            $this->invitation->business,
            $this->invitation->locale,
        );

        $rendered = RenderInvitationTemplate::render(
            $resolved['subject'],
            $resolved['body'],
            route('review-invitations.show', $this->invitation->token),
            route('invitations.unsubscribe', $this->invitation->token),
        );

        $message = (new MailMessage)->subject(
            $this->isReminder ? "Reminder: {$rendered['subject']}" : $rendered['subject']
        );

        // FR-005-08's "reply-to" and "sender name" — the platform's own
        // From address never changes (deliverability/DMARC), but a
        // Business's chosen reply-to routes replies back to them.
        if ($resolved['reply_to'] !== null) {
            $message->replyTo($resolved['reply_to'], $resolved['sender_name']);
        }

        foreach (explode("\n", $rendered['body']) as $line) {
            $message->line($line);
        }

        return $message;
    }
}
