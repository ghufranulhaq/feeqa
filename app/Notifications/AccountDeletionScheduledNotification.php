<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-001-20, scenario 5: "they get a confirmation email."
 */
class AccountDeletionScheduledNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account is scheduled for deletion')
            ->line('Your public content is no longer visible.')
            ->line('Your personal data will be permanently erased within 30 days.')
            ->line("If this wasn't you, contact support right away.");
    }
}
