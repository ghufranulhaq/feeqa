<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when someone tries to register with an email that already has an
 * account (spec 001 edge case: "do not reveal that the account exists").
 * The registration response itself stays generic; this is where the actual
 * "you already have an account" information goes — to the account's own
 * inbox, not the visitor who typed the email.
 */
class SignInInsteadNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sign in to your account')
            ->line('Someone just tried to create a new account using this email address.')
            ->line('You already have an account here — no need to create another one.')
            ->action('Sign in', route('login'))
            ->line("If this wasn't you, you can safely ignore this email.");
    }
}
