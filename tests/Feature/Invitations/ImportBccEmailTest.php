<?php

use App\Actions\Invitations\ImportBccEmail;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Invitations\BccImportOutcome;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function bccImportOwner(Business $business): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole(BusinessRole::Owner->value);

    return $user;
}

function bccEml(string $domain, string $to, string $subject, string $body, ?string $cc = null): string
{
    $ccLine = $cc !== null ? "Cc: {$cc}\n" : '';

    return "From: Orders <orders@{$domain}>\n".
        "To: {$to}\n".
        $ccLine.
        "Subject: {$subject}\n".
        "Authentication-Results: mx.feeqa.test; spf=pass smtp.mailfrom=orders@{$domain}; dkim=pass header.d={$domain}\n".
        "\n".
        $body;
}

it('imports an aligned transactional email as a Bcc invitation (FR-005-01 bcc, FR-005-02)', function () {
    $business = Business::factory()->create();
    $owner = bccImportOwner($business);
    $eml = bccEml($business->primary_domain, '"Jane Doe" <jane@example.com>', 'Your booking SK-123456', 'Reference: SK-123456');
    $file = UploadedFile::fake()->createWithContent('message.eml', $eml);

    $result = app(ImportBccEmail::class)->handle($business, $owner, $file);

    expect($result->outcome)->toBe(BccImportOutcome::Imported)
        ->and($result->invitation)->not->toBeNull()
        ->and($result->invitation->method)->toBe(InvitationMethod::Bcc)
        ->and($result->invitation->method->isTransactionLinked())->toBeTrue()
        ->and($result->invitation->method->sourceLabel())->toBe(SourceLabel::Invited)
        ->and($result->invitation->recipient_name)->toBe('Jane Doe');
});

it('discards the email body and never persists it anywhere (spec.md §6 acceptance criterion, FR-005-07)', function () {
    $business = Business::factory()->create();
    $owner = bccImportOwner($business);
    $marker = 'UNIQUE-BODY-MARKER-'.str()->random(16);
    $eml = bccEml(
        $business->primary_domain,
        '"Jane Doe" <jane@example.com>',
        'Your booking SK-123456',
        "Reference: SK-123456\n\n{$marker}",
    );
    $file = UploadedFile::fake()->createWithContent('message.eml', $eml);

    app(ImportBccEmail::class)->handle($business, $owner, $file);

    $invitation = ReviewInvitation::sole();

    expect($invitation->recipient_name)->not->toContain($marker)
        ->and($invitation->recipient_email)->not->toContain($marker)
        ->and($invitation->reference)->not->toContain($marker)
        ->and(collect($invitation->getAttributes())->implode(''))->not->toContain($marker);
});

it('drops an email that fails SPF/DKIM alignment and counts it in diagnostics (FR-005-06)', function () {
    $business = Business::factory()->create();
    $owner = bccImportOwner($business);
    $eml = "From: Orders <orders@unrelated-domain.example>\n".
        "To: jane@example.com\n".
        "Subject: Your booking SK-123456\n".
        "\n".
        'Reference: SK-123456';
    $file = UploadedFile::fake()->createWithContent('message.eml', $eml);

    $result = app(ImportBccEmail::class)->handle($business, $owner, $file);

    expect($result->outcome)->toBe(BccImportOutcome::FailedAlignment)
        ->and($result->invitation)->toBeNull()
        ->and($business->fresh()->bcc_failed_alignment_count)->toBe(1)
        ->and(ReviewInvitation::count())->toBe(0);
});

it('accepts alignment through a registered sender address even off-domain (FR-005-06)', function () {
    $business = Business::factory()->create(['bcc_registered_senders' => ['bookings@partner-otas.example']]);
    $owner = bccImportOwner($business);
    $eml = "From: Bookings <bookings@partner-otas.example>\n".
        "To: jane@example.com\n".
        "Subject: Your booking SK-654321\n".
        "Authentication-Results: mx.feeqa.test; spf=pass smtp.mailfrom=bookings@partner-otas.example; dkim=pass header.d=partner-otas.example\n".
        "\n".
        'Reference: SK-654321';
    $file = UploadedFile::fake()->createWithContent('message.eml', $eml);

    $result = app(ImportBccEmail::class)->handle($business, $owner, $file);

    expect($result->outcome)->toBe(BccImportOutcome::Imported);
});

it('drops a non-transactional email and counts it in diagnostics (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = bccImportOwner($business);
    $eml = bccEml($business->primary_domain, 'jane@example.com', 'Our monthly newsletter', 'Check out our latest offers!');
    $file = UploadedFile::fake()->createWithContent('message.eml', $eml);

    $result = app(ImportBccEmail::class)->handle($business, $owner, $file);

    expect($result->outcome)->toBe(BccImportOutcome::NoReferenceMatch)
        ->and($result->invitation)->toBeNull()
        ->and($business->fresh()->bcc_no_reference_match_count)->toBe(1);
});

it('invites only the first To: recipient and ignores Cc (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = bccImportOwner($business);
    $eml = bccEml(
        $business->primary_domain,
        'jane@example.com, john@example.com',
        'Your booking SK-111222',
        'Reference: SK-111222',
        cc: 'someone-else@example.com',
    );
    $file = UploadedFile::fake()->createWithContent('message.eml', $eml);

    $result = app(ImportBccEmail::class)->handle($business, $owner, $file);

    expect($result->outcome)->toBe(BccImportOutcome::Imported)
        ->and(ReviewInvitation::count())->toBe(1);
});

it('rejects a Responder importing BCC email (needs ManageIntegrations)', function () {
    $business = Business::factory()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);
    $file = UploadedFile::fake()->createWithContent('message.eml', bccEml($business->primary_domain, 'jane@example.com', 'SK-123456', 'SK-123456'));

    app(ImportBccEmail::class)->handle($business, $responder, $file);
})->throws(AuthorizationException::class);

it('rejects an empty file', function () {
    $business = Business::factory()->create();
    $owner = bccImportOwner($business);
    $file = UploadedFile::fake()->createWithContent('message.eml', '');

    app(ImportBccEmail::class)->handle($business, $owner, $file);
})->throws(ValidationException::class);
