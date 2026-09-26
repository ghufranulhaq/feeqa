<?php

namespace App\Notifications;

use App\Models\ReviewVerification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationProofRejectedNotification extends Notification
{
    public function __construct(private readonly ReviewVerification $verification) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->verification->review->business;

        $message = (new MailMessage)
            ->subject("Your verification proof for {$business->name} wasn't approved")
            ->line("Your proof of experience for **{$business->name}** wasn't approved.");

        if ($this->verification->decision_reason_code) {
            $message->line("Reason: {$this->verification->decision_reason_code}");
        }

        return $message;
    }
}
