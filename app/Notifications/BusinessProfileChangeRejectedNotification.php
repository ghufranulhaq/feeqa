<?php

namespace App\Notifications;

use App\Models\BusinessProfileChangeRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Constitution P4: "Every moderation action against a user gives them a
 * statement of reasons." A formal appeal flow belongs to spec 006
 * (Moderation, Integrity & Transparency) — this is the reasons half only.
 */
class BusinessProfileChangeRejectedNotification extends Notification
{
    public function __construct(private readonly BusinessProfileChangeRequest $changeRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->changeRequest->business;
        $fields = implode(', ', array_keys($this->changeRequest->changes));

        $message = (new MailMessage)
            ->subject("Your profile change for {$business->name} wasn't approved")
            ->line("Your requested change to **{$fields}** on **{$business->name}** wasn't approved.");

        if ($this->changeRequest->review_notes) {
            $message->line("Reason: {$this->changeRequest->review_notes}");
        }

        return $message;
    }
}
