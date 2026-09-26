<?php

namespace App\Notifications;

use App\Models\Flag;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-006-07: "non-signed-in reporters must give an email to receive the
 * outcome" — this is that outcome, sent to signed-in and guest reporters
 * alike once `DecideFlag` resolves their flag.
 */
class FlagDecidedNotification extends Notification
{
    public function __construct(private readonly Flag $flag) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('The content you flagged has been reviewed')
            ->line("Your flag ({$this->flag->reason_code->value}) was {$this->flag->status->value}.")
            ->line($this->flag->decision_reason ?? '');
    }
}
