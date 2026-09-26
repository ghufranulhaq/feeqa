<?php

namespace App\Notifications;

use App\Models\BusinessClaim;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-002-11(a). Sent to the claim's `target` address (on the business's
 * own domain), not necessarily the claimant's platform login email.
 */
class BusinessClaimCodeNotification extends Notification
{
    public function __construct(private readonly BusinessClaim $claim) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->claim->business;

        return (new MailMessage)
            ->subject("Verification code to claim {$business->name}")
            ->line("Someone is claiming **{$business->name}** on feeqa using this address.")
            ->line("Your verification code is: {$this->claim->verification_code}")
            ->line('This code expires in 30 minutes.')
            ->line("If you didn't request this, you can ignore this email.");
    }
}
