<?php

namespace App\Console\Commands;

use App\Domain\Reviews\LifecycleMilestone;
use App\Models\Review;
use App\Models\ReviewLifecycleReminder;
use App\Notifications\ReviewLifecycleReminderNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * FR-003-19: "When a milestone window opens, the system must send the
 * author one reminder, by email and in-app, unless they have opted out."
 * Scheduled daily — see routes/console.php.
 */
#[Signature('reviews:send-lifecycle-reminders')]
#[Description('Send the author a reminder for every review whose lifecycle milestone window opens today')]
class SendReviewLifecycleReminders extends Command
{
    public function handle(): int
    {
        $sent = 0;

        $reviews = Review::published()->whereNotNull('published_at')->with('reviewer', 'business')->get();

        foreach ($reviews as $review) {
            foreach (LifecycleMilestone::cases() as $milestone) {
                if (! $milestone->windowOpensAt($review->published_at)->isToday()) {
                    continue;
                }

                $alreadySent = ReviewLifecycleReminder::where('review_id', $review->id)
                    ->where('milestone', $milestone)
                    ->exists();

                if ($alreadySent) {
                    continue;
                }

                ReviewLifecycleReminder::create([
                    'review_id' => $review->id,
                    'milestone' => $milestone,
                    'sent_at' => now(),
                ]);

                if (! $review->reviewer->hasOptedOutOfLifecycleReminders()) {
                    $review->reviewer->notify(new ReviewLifecycleReminderNotification($review, $milestone));
                }

                $sent++;
            }
        }

        $this->info("Sent {$sent} lifecycle reminder(s).");

        return self::SUCCESS;
    }
}
