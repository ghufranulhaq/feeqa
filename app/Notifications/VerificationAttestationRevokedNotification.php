<?php

namespace App\Notifications;

use App\Models\VerificationAttestation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerificationAttestationRevokedNotification extends Notification
{
    public function __construct(private readonly VerificationAttestation $attestation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->attestation->business;

        return (new MailMessage)
            ->subject('Your Verified Experience badge was removed')
            ->line("The Verified Experience badge on your review of **{$business->name}** was removed.")
            ->line("Reason: {$this->attestation->revoked_reason_code}");
    }
}
