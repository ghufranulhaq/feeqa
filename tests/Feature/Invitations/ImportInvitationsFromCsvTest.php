<?php

use App\Actions\Invitations\ImportInvitationsFromCsv;
use App\Domain\Businesses\BusinessRole;
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

function csvImportOwner(Business $business): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole(BusinessRole::Owner->value);

    return $user;
}

it('imports valid rows as Invited invitations (FR-005-01, FR-005-03)', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $csv = "recipient_email,recipient_name,reference\nfirst@example.com,First,ORDER-1\nsecond@example.com,Second,ORDER-2\n";
    $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

    $result = app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);

    expect($result->created)->toHaveCount(2)
        ->and($result->errors)->toBe([])
        ->and($result->created->every(fn (ReviewInvitation $invitation) => $invitation->method === InvitationMethod::Csv))->toBeTrue()
        ->and($result->created->every(fn (ReviewInvitation $invitation) => $invitation->method->sourceLabel() === SourceLabel::Invited))->toBeTrue();
});

it('rejects a malformed email but keeps processing the rest of the file (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $csv = "recipient_email\nfirst@example.com\nnot-an-email\nsecond@example.com\n";
    $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

    $result = app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);

    expect($result->created)->toHaveCount(2)
        ->and($result->errors)->toBe([['row' => 3, 'email' => 'not-an-email', 'reason' => 'malformed_email']]);
});

it('collapses duplicate rows for the same recipient into one invitation (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $csv = "recipient_email\nconsumer@example.com\nconsumer@example.com\n";
    $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

    $result = app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);

    expect($result->created)->toHaveCount(1)
        ->and($result->collapsed)->toBe(1)
        ->and($result->errors)->toBe([])
        ->and(ReviewInvitation::where('business_id', $business->id)->count())->toBe(1);
});

it('rejects an empty file', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $file = UploadedFile::fake()->createWithContent('customers.csv', '');

    app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);
})->throws(ValidationException::class);

it('rejects a file that is not UTF-8 encoded (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $content = "recipient_email\n".mb_convert_encoding('café@example.com', 'ISO-8859-1', 'UTF-8')."\n";
    $file = UploadedFile::fake()->createWithContent('customers.csv', $content);

    app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);
})->throws(ValidationException::class);

it('rejects a file missing the required recipient_email column (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $file = UploadedFile::fake()->createWithContent('customers.csv', "name,reference\nAlice,ORDER-1\n");

    app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);
})->throws(ValidationException::class);

it('rejects a file over 50,000 rows (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $rows = "recipient_email\n";
    for ($i = 0; $i < 50_001; $i++) {
        $rows .= "person{$i}@example.com\n";
    }
    $file = UploadedFile::fake()->createWithContent('customers.csv', $rows);

    app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);
})->throws(ValidationException::class);

it('rejects a file over 20 MB (edge case table)', function () {
    $business = Business::factory()->create();
    $owner = csvImportOwner($business);
    $file = UploadedFile::fake()->create('customers.csv', 20 * 1024 + 1);

    app(ImportInvitationsFromCsv::class)->handle($business, $owner, $file);
})->throws(ValidationException::class);

it('rejects a Responder uploading a CSV (needs SendInvitations, edge case table)', function () {
    $business = Business::factory()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);
    $file = UploadedFile::fake()->createWithContent('customers.csv', "recipient_email\nfirst@example.com\n");

    app(ImportInvitationsFromCsv::class)->handle($business, $responder, $file);
})->throws(AuthorizationException::class);
