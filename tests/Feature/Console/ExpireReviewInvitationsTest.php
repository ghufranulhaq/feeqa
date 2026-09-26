<?php

use App\Domain\Invitations\InvitationStatus;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('expires a non-terminal invitation past its expiry (FR-005-10, FR-005-11)', function () {
    $stale = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Sent,
        'expires_at' => now()->subDay(),
    ]);
    $fresh = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Sent,
        'expires_at' => now()->addDay(),
    ]);

    $this->artisan('review-invitations:expire')->assertExitCode(0);

    expect($stale->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($fresh->fresh()->status)->toBe(InvitationStatus::Sent);
});

it('leaves an already-terminal invitation alone, even past expiry', function () {
    $reviewed = ReviewInvitation::factory()->create([
        'status' => InvitationStatus::Reviewed,
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('review-invitations:expire');

    expect($reviewed->fresh()->status)->toBe(InvitationStatus::Reviewed);
});
