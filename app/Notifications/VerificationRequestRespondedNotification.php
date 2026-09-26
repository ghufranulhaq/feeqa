<?php

namespace App\Notifications;

use App\Domain\Verification\ConsumerVerificationResponse;
use App\Models\BusinessVerificationRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-004-19: notifies the business of the reviewer's response. Never
 * reveals more than the reviewer consented to — the reference number is
 * included only when they chose to share it.
 */
class VerificationRequestRespondedNotification extends Notification
{
    public function __construct(private readonly BusinessVerificationRequest $request) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $review = $this->request->review;

        $message = (new MailMessage)->subject('A reviewer responded to your verification request');

        return match ($this->request->consumer_response) {
            ConsumerVerificationResponse::Ignore => $message->line(
                'The reviewer chose not to respond to your verification request. Their review stays exactly as published.'
            ),
            ConsumerVerificationResponse::VerifyAndShare => $message
                ->line('The reviewer verified their review and agreed to share their reference number with you.')
                ->line("Reference number: {$this->request->shared_reference_number}"),
            default => $message->line("The reviewer verified their review of \"{$review->title}\" privately. No further details were shared with you."),
        };
    }
}
