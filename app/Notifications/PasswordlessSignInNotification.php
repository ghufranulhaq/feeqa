<?php

namespace App\Notifications;

use App\Models\PasswordlessCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordlessSignInNotification extends Notification
{
    public function __construct(private readonly PasswordlessCode $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in code')
            ->line("Your code is: {$this->code->code}")
            ->action('Sign in', route('login.passwordless.token', $this->code->token))
            ->line('This code and link expire in 15 minutes.')
            ->line("If you didn't request this, you can ignore this email.");
    }
}
