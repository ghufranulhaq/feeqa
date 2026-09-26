<?php

use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\InvitationStatus;
use App\Domain\Reviews\SourceLabel;
use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\InvitationSuppression;
use App\Models\InvitationTemplate;
use App\Models\ReviewInvitation;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('stores the recipient email and reference encrypted at rest (FR-005-05)', function () {
    $invitation = ReviewInvitation::factory()->withReference('SK-448812')->create([
        'recipient_email' => 'consumer@example.com',
    ]);

    $rawEmail = DB::table('review_invitations')->where('id', $invitation->id)->value('recipient_email');
    $rawReference = DB::table('review_invitations')->where('id', $invitation->id)->value('reference');

    expect($rawEmail)->not->toBe('consumer@example.com')
        ->and($rawReference)->not->toBe('SK-448812')
        ->and($invitation->fresh()->recipient_email)->toBe('consumer@example.com')
        ->and($invitation->fresh()->reference)->toBe('SK-448812');
});

it('keeps a lookup hash alongside the encrypted email and reference (FR-005-09, FR-005-13)', function () {
    $invitation = ReviewInvitation::factory()->withReference('SK-448812')->create([
        'recipient_email' => 'consumer@example.com',
    ]);

    expect($invitation->recipient_email_hash)->toBe(TransactionRecordHash::email('consumer@example.com'))
        ->and($invitation->reference_hash)->toBe(TransactionRecordHash::reference('SK-448812'));
});

it('casts method and status to their enums', function () {
    $invitation = ReviewInvitation::factory()->bcc()->create();

    expect($invitation->method)->toBe(InvitationMethod::Bcc)
        ->and($invitation->status)->toBe(InvitationStatus::Queued)
        ->and($invitation->method->isTransactionLinked())->toBeTrue()
        ->and($invitation->method->sourceLabel())->toBe(SourceLabel::Invited);
});

it('reports the link method as never transaction-linked and Redirected (FR-005-04)', function () {
    $invitation = ReviewInvitation::factory()->link()->create();

    expect($invitation->method->isTransactionLinked())->toBeFalse()
        ->and($invitation->method->sourceLabel())->toBe(SourceLabel::Redirected);
});

it('reports csv and manual as Invited but never transaction-linked (FR-005-03)', function () {
    $csv = ReviewInvitation::factory()->csv()->create();
    $manual = ReviewInvitation::factory()->create();

    expect($csv->method->isTransactionLinked())->toBeFalse()
        ->and($csv->method->sourceLabel())->toBe(SourceLabel::Invited)
        ->and($manual->method->isTransactionLinked())->toBeFalse()
        ->and($manual->method->sourceLabel())->toBe(SourceLabel::Invited);
});

it('knows whether it has expired', function () {
    $expired = ReviewInvitation::factory()->expired()->create();
    $live = ReviewInvitation::factory()->create();

    expect($expired->isExpired())->toBeTrue()
        ->and($live->isExpired())->toBeFalse();
});

it('enforces one template per business per locale (FR-005-13)', function () {
    $business = Business::factory()->create();
    InvitationTemplate::factory()->for($business)->create(['locale' => 'en-GB']);

    expect(fn () => InvitationTemplate::factory()->for($business)->create(['locale' => 'en-GB']))
        ->toThrow(QueryException::class);
});

it('records a global suppression with a null business_id (FR-005-15)', function () {
    $suppression = InvitationSuppression::factory()->global()->create();

    expect($suppression->business_id)->toBeNull()
        ->and($suppression->reason)->toBe('unsubscribed_global');
});

it('records a per-business suppression tied to a business (FR-005-15)', function () {
    $business = Business::factory()->create();
    $suppression = InvitationSuppression::factory()->for($business)->create();

    expect($suppression->business_id)->toBe($business->id)
        ->and($suppression->reason)->toBe('unsubscribed_business');
});
