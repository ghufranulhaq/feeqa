<?php

use App\Actions\Invitations\CreateInvitation;
use App\Actions\Invitations\RecordBounce;
use App\Actions\Invitations\RecordComplaint;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('suppresses a hard bounce platform-wide (FR-005-17)', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Sent]);

    app(RecordBounce::class)->handle($invitation);

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Bounced)
        ->and($invitation->fresh()->bounced_at)->not->toBeNull()
        ->and(InvitationSuppression::whereNull('business_id')
            ->where('recipient_email_hash', $invitation->recipient_email_hash)
            ->where('reason', 'hard_bounce')
            ->exists())->toBeTrue();
});

it('suppresses a spam complaint platform-wide (FR-005-17)', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Delivered]);

    app(RecordComplaint::class)->handle($invitation);

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Complained)
        ->and(InvitationSuppression::whereNull('business_id')
            ->where('recipient_email_hash', $invitation->recipient_email_hash)
            ->where('reason', 'spam_complaint')
            ->exists())->toBeTrue();
});

it('blocks a future invitation from any Business once a bounce is recorded', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Sent]);
    app(RecordBounce::class)->handle($invitation);
    $otherBusiness = Business::factory()->create();

    $second = app(CreateInvitation::class)->handle(
        $otherBusiness,
        InvitationMethod::Manual,
        ['recipient_email' => $invitation->recipient_email],
    );

    expect($second->status)->toBe(InvitationStatus::Suppressed);
});
