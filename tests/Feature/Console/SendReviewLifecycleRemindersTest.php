<?php

use App\Domain\Reviews\LifecycleMilestone;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewLifecycleReminder;
use App\Models\User;
use App\Notifications\ReviewLifecycleReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

afterEach(fn () => $this->travelBack());

it('sends a reminder by email and in-app the day a milestone window opens (FR-003-19)', function () {
    Notification::fake();

    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'published_at' => $publishedAt]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    $this->artisan('reviews:send-lifecycle-reminders')->assertExitCode(0);

    Notification::assertSentTo($author, ReviewLifecycleReminderNotification::class);
    expect(ReviewLifecycleReminder::where('review_id', $review->id)->where('milestone', LifecycleMilestone::Day30)->exists())->toBeTrue();
});

it('does not send a reminder on a day the window does not open', function () {
    Notification::fake();

    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $business = Business::factory()->create();
    $author = User::factory()->create();
    Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'published_at' => $publishedAt]);
    $this->travelTo($publishedAt->copy()->addDays(15));

    $this->artisan('reviews:send-lifecycle-reminders');

    Notification::assertNothingSent();
});

it('never sends the same reminder twice, even if run again the same day', function () {
    Notification::fake();

    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $business = Business::factory()->create();
    $author = User::factory()->create();
    Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'published_at' => $publishedAt]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    $this->artisan('reviews:send-lifecycle-reminders');
    $this->artisan('reviews:send-lifecycle-reminders');

    Notification::assertSentToTimes($author, ReviewLifecycleReminderNotification::class, 1);
});

it('respects an opted-out author: no notification sent, but the reminder is still marked sent (FR-003-19)', function () {
    Notification::fake();

    $publishedAt = Carbon::parse('2026-01-01')->startOfDay();
    $business = Business::factory()->create();
    $author = User::factory()->create(['lifecycle_reminders_opted_out_at' => now()]);
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id, 'published_at' => $publishedAt]);
    $this->travelTo($publishedAt->copy()->addDays(30));

    $this->artisan('reviews:send-lifecycle-reminders');

    Notification::assertNothingSent();
    expect(ReviewLifecycleReminder::where('review_id', $review->id)->exists())->toBeTrue();
});
