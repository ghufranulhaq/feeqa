<?php

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;
use App\Notifications\ReviewInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends exactly one reminder to a sent, unopened invitation after the configured delay (FR-005-08)', function () {
    Notification::fake();
    $invitation = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Sent,
        'sent_at' => now()->subDays(4),
    ]);

    $this->artisan('review-invitations:send-reminders');
    $this->artisan('review-invitations:send-reminders');

    expect($invitation->fresh()->reminder_sent_at)->not->toBeNull();
    Notification::assertSentOnDemandTimes(ReviewInvitationNotification::class, 1);
});

it('does not remind an invitation the recipient already opened', function () {
    Notification::fake();
    ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Opened,
        'sent_at' => now()->subDays(4),
        'opened_at' => now()->subDays(3),
    ]);

    $this->artisan('review-invitations:send-reminders');

    Notification::assertNothingSent();
});

it('does not remind an invitation before the configured delay has passed', function () {
    Notification::fake();
    ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Sent,
        'sent_at' => now()->subDay(),
    ]);

    $this->artisan('review-invitations:send-reminders');

    Notification::assertNothingSent();
});
