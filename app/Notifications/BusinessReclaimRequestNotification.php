<?php

namespace App\Notifications;

use App\Models\BusinessClaim;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-002-14: sent to every current Owner when someone else proves control
 * of the business's domain and asks to join as an Owner too. No console
 * UI exists yet to act on this from (same situation as spec 001's business
 * invitations) — App\Actions\Businesses\RespondToBusinessReclaim is the
 * real, callable action.
 */
class BusinessReclaimRequestNotification extends Notification
{
    public function __construct(private readonly BusinessClaim $claim) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->claim->business;
        $claimant = $this->claim->claimant;

        return (new MailMessage)
            ->subject("{$claimant->name} wants to join {$business->name} as an Owner")
            ->line("{$claimant->name} has verified control of {$business->name}'s domain and is asking to become an Owner too.")
            ->line('You have 7 days to approve or reject this. With no response, it goes to staff review.');
    }
}
