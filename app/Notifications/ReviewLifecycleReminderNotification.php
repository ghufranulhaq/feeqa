<?php

namespace App\Notifications;

use App\Domain\Reviews\LifecycleMilestone;
use App\Models\Review;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * FR-003-19: "unless they have opted out" is checked by the caller
 * (`SendReviewLifecycleReminders`) before this is ever dispatched.
 */
class ReviewLifecycleReminderNotification extends Notification
{
    public function __construct(
        private readonly Review $review,
        private readonly LifecycleMilestone $milestone,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $business = $this->review->business;

        return (new MailMessage)
            ->subject("Still happy with {$business->name}? Add an update.")
            ->line("It's been a while since your review of **{$business->name}**.")
            ->action('Add an update', route('businesses.reviews.show', [$business->slug, $this->review->id]))
            ->line('Adding an update helps other readers see how your experience held up.');
    }

    /**
     * @return array{review_id: int, business_name: string, milestone: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'review_id' => $this->review->id,
            'business_name' => $this->review->business->name,
            'milestone' => $this->milestone->value,
        ];
    }
}
