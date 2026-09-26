<?php

use App\Domain\Invitations\InvitationMethod;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives every Business a stable, unique review link token needing no invitation row (FR-005-04)', function () {
    $a = Business::factory()->create();
    $b = Business::factory()->create();

    expect($a->review_link_token)->not->toBeNull()
        ->and($a->review_link_token)->not->toBe($b->review_link_token)
        ->and($a->reviewLinkUrl())->toContain($a->slug)
        ->and($a->reviewLinkUrl())->toContain($a->review_link_token)
        ->and($a->fresh()->review_link_token)->toBe($a->review_link_token);
});

it('labels the link method Redirected and never transaction-linked (FR-005-04)', function () {
    expect(InvitationMethod::Link->sourceLabel())->toBe(SourceLabel::Redirected)
        ->and(InvitationMethod::Link->isTransactionLinked())->toBeFalse();
});
