<?php

namespace App\Notifications;

use App\Models\BusinessClaim;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessClaimRejectedNotification extends Notification
{
    public function __construct(private readonly BusinessClaim $claim) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->claim->business;

        $message = (new MailMessage)
            ->subject("Your claim on {$business->name} wasn't approved")
            ->line("Your request to claim **{$business->name}** wasn't approved.");

        if ($this->claim->review_notes) {
            $message->line("Reason: {$this->claim->review_notes}");
        }

        return $message;
    }
}
