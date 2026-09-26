<?php

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('unsubscribes over HTTP with no sign-in required (FR-005-15)', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Sent]);

    $response = $this->post(route('invitations.unsubscribe', $invitation->token));

    $response->assertOk()->assertJson(['status' => 'unsubscribed']);
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Unsubscribed);
});

it('returns 404 for an unknown unsubscribe token', function () {
    $this->post(route('invitations.unsubscribe', 'not-a-real-token'))->assertNotFound();
});
