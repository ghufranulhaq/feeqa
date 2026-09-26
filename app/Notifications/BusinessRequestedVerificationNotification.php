<?php

namespace App\Notifications;

use App\Models\BusinessVerificationRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-004-19: the reviewer is notified when a business requests
 * verification of one of their reviews.
 */
class BusinessRequestedVerificationNotification extends Notification
{
    public function __construct(private readonly BusinessVerificationRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->request->business;

        return (new MailMessage)
            ->subject("{$business->name} has asked you to verify your review")
            ->line("**{$business->name}** has asked you to verify your review is genuine.")
            ->line('You can verify it privately, verify it and share your reference number with the business, or ignore the request — ignoring it will never hide, remove, or down-rank your review.');
    }
}
