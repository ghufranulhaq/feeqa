<?php

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves a valid token and records a click, pre-filling business/location/products/reference (FR-005-10)', function () {
    $invitation = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Sent,
        'reference' => 'BK-555000',
        'product_skus' => ['SKU-1', 'SKU-2'],
    ]);

    $response = $this->get(route('review-invitations.show', $invitation->token));

    $response->assertOk()->assertJson([
        'expired' => false,
        'business_id' => $invitation->business_id,
        'reference' => 'BK-555000',
        'product_skus' => ['SKU-1', 'SKU-2'],
    ]);
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Clicked)
        ->and($invitation->fresh()->clicked_at)->not->toBeNull()
        ->and($invitation->fresh()->opened_at)->not->toBeNull();
});

it('resolves an expired invitation to the expired state instead of the review-form data (edge case table)', function () {
    $invitation = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Sent,
        'expires_at' => now()->subDay(),
    ]);

    $response = $this->get(route('review-invitations.show', $invitation->token));

    $response->assertOk()->assertJson(['expired' => true]);
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Sent);
});

it('returns 404 for an unknown token', function () {
    $this->get(route('review-invitations.show', 'not-a-real-token'))->assertNotFound();
});

it('records an open via the tracking pixel and returns a GIF image', function () {
    $invitation = ReviewInvitation::factory()->create(['status' => InvitationStatus::Sent]);

    $response = $this->get(route('review-invitations.open-pixel', $invitation->token));

    $response->assertOk()->assertHeader('Content-Type', 'image/gif');
    expect($invitation->fresh()->status)->toBe(InvitationStatus::Opened)
        ->and($invitation->fresh()->opened_at)->not->toBeNull();
});

it('still returns a pixel for an unknown token, revealing nothing', function () {
    $this->get(route('review-invitations.open-pixel', 'not-a-real-token'))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');
});
