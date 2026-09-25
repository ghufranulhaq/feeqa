<?php

namespace App\Notifications;

use App\Models\BusinessInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessInvitationNotification extends Notification
{
    public function __construct(private readonly BusinessInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->invitation->business;
        $role = $this->invitation->businessRole()->value;

        return (new MailMessage)
            ->subject("You've been invited to join {$business->name} on feeqa")
            ->line("You've been invited to join **{$business->name}** as **{$role}**.")
            ->action('Accept invitation', route('business-invitations.show', $this->invitation->token))
            ->line('This invitation expires in 7 days.');
    }
}
