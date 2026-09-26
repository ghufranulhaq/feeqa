<?php

use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;
use App\Notifications\ReviewInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('sends every queued invitation whose scheduled time has arrived (FR-005-08, FR-005-09)', function () {
    Notification::fake();
    $due = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Queued,
        'scheduled_at' => now()->subMinute(),
    ]);
    $notYetDue = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Queued,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->artisan('review-invitations:send-due')->assertExitCode(0);

    expect($due->fresh()->status)->toBe(InvitationStatus::Sent)
        ->and($due->fresh()->sent_at)->not->toBeNull()
        ->and($notYetDue->fresh()->status)->toBe(InvitationStatus::Queued);
    Notification::assertSentOnDemand(ReviewInvitationNotification::class);
});

it('does not send a due invitation still held by the plan limit (FR-005-20)', function () {
    Notification::fake();
    $held = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Queued,
        'scheduled_at' => now()->subMinute(),
        'queued_reason' => 'plan_limit',
    ]);

    $this->artisan('review-invitations:send-due');

    expect($held->fresh()->status)->toBe(InvitationStatus::Queued);
    Notification::assertNothingSent();
});

it('suppresses a due invitation instead of sending if the recipient was suppressed after it was queued', function () {
    Notification::fake();
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->create([
        'status' => InvitationStatus::Queued,
        'scheduled_at' => now()->subMinute(),
    ]);
    InvitationSuppression::create([
        'business_id' => null,
        'recipient_email_hash' => $invitation->recipient_email_hash,
        'reason' => 'unsubscribed',
    ]);

    $this->artisan('review-invitations:send-due');

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Suppressed);
    Notification::assertNothingSent();
});
