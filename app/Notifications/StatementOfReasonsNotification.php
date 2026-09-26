<?php

namespace App\Notifications;

use App\Domain\Moderation\ReasonCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-006-13: "what was affected, reason code, the guideline section,
 * whether automation was used, and how to appeal" — sent for every staff
 * (or automated) action against a user's content or account.
 */
class StatementOfReasonsNotification extends Notification
{
    public function __construct(
        private readonly string $whatWasAffected,
        private readonly ReasonCode $reasonCode,
        private readonly ?int $guidelineVersion,
        private readonly bool $automated,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('A moderation decision affecting your account')
            ->line("We took an action affecting: {$this->whatWasAffected}.")
            ->line("Reason: {$this->reasonCode->value}.");

        if ($this->guidelineVersion !== null) {
            $message->line("This follows version {$this->guidelineVersion} of our guidelines.");
        }

        $message->line($this->automated
            ? 'This decision was made automatically by our screening system.'
            : 'This decision was made by a member of our moderation team.');

        // FR-006-18: `SubmitAppeal` (T7) exists, but there's still no
        // page or route for a reviewer to call it themselves (this spec's
        // console/flag-button/appeal-form UI isn't built) — an honest
        // placeholder instruction rather than a link to a page that isn't
        // there, same gap 005 T7 documented for its own token resolution.
        $message->line('If you believe this is a mistake, reply to this email to appeal.');

        return $message;
    }
}
