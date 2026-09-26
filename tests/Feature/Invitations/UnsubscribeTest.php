<?php

use App\Actions\Invitations\Unsubscribe;
use App\Domain\Invitations\InvitationStatus;
use App\Models\Business;
use App\Models\InvitationSuppression;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('unsubscribes from one Business only, leaving other businesses free to invite (FR-005-15)', function () {
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->create(['status' => InvitationStatus::Sent]);

    app(Unsubscribe::class)->handle($invitation->token, global: false);

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Unsubscribed)
        ->and(InvitationSuppression::where('business_id', $business->id)
            ->where('recipient_email_hash', $invitation->recipient_email_hash)
            ->exists())->toBeTrue()
        ->and(InvitationSuppression::whereNull('business_id')
            ->where('recipient_email_hash', $invitation->recipient_email_hash)
            ->exists())->toBeFalse();
});

it('unsubscribes platform-wide when asked (FR-005-15)', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Sent]);

    app(Unsubscribe::class)->handle($invitation->token, global: true);

    expect(InvitationSuppression::whereNull('business_id')
        ->where('recipient_email_hash', $invitation->recipient_email_hash)
        ->exists())->toBeTrue();
});

it('is a no-op to click the same unsubscribe link twice', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Sent]);

    app(Unsubscribe::class)->handle($invitation->token);
    app(Unsubscribe::class)->handle($invitation->token);

    expect(InvitationSuppression::where('recipient_email_hash', $invitation->recipient_email_hash)->count())->toBe(1);
});

it('does not overwrite an already-terminal invitation status', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Reviewed]);

    app(Unsubscribe::class)->handle($invitation->token);

    expect($invitation->fresh()->status)->toBe(InvitationStatus::Reviewed);
});
