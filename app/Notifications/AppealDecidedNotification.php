<?php

namespace App\Notifications;

use App\Models\Appeal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-006-19: "the outcome (upheld/overturned) and reason are sent to the
 * appellant" — whichever way the appeal went.
 */
class AppealDecidedNotification extends Notification
{
    public function __construct(private readonly Appeal $appeal) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('The outcome of your appeal')
            ->line("Your appeal was {$this->appeal->status->value}.")
            ->line($this->appeal->decision_reason ?? '');
    }
}
