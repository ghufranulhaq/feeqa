<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-003-32: sent to every member of the Business a published review
 * optionally tags (e.g. the agency that sold a ticket for an airline
 * review). 007's per-member notification preferences aren't built yet —
 * every member is notified today.
 */
class ReviewTaggedNotification extends Notification
{
    public function __construct(private readonly Review $review) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reviewedBusiness = $this->review->business;
        $taggedBusiness = $this->review->taggedBusiness;

        return (new MailMessage)
            ->subject("{$taggedBusiness->name} was mentioned in a review of {$reviewedBusiness->name}")
            ->line("A review of {$reviewedBusiness->name} mentions {$taggedBusiness->name}.")
            ->action('View the review', route('businesses.reviews.show', [$reviewedBusiness->slug, $this->review->id]));
    }
}
