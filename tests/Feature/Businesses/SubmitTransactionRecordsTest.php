<?php

use App\Actions\Businesses\SubmitTransactionRecords;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function transactionRecordsMemberWithRole(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

function transactionRecordRow(?string $reference = null): array
{
    return [
        'reference_hash' => TransactionRecordHash::reference($reference ?? uniqid('REF')),
        'email_hash' => TransactionRecordHash::email('jane@example.com'),
        'transaction_date' => now()->subDays(5)->toDateString(),
    ];
}

it('upserts transaction records for an Owner (FR-004-12)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = transactionRecordsMemberWithRole($business, BusinessRole::Owner);

    $count = (new SubmitTransactionRecords)->handle($business, $owner, [transactionRecordRow('ABC-1')]);

    expect($count)->toBe(1)
        ->and(BusinessTransactionRecord::where('business_id', $business->id)->count())->toBe(1);
});

it('upserts (not duplicates) a record resubmitted with the same reference hash', function () {
    $business = Business::factory()->claimed()->create();
    $owner = transactionRecordsMemberWithRole($business, BusinessRole::Owner);
    $row = transactionRecordRow('ABC-1');

    (new SubmitTransactionRecords)->handle($business, $owner, [$row]);
    (new SubmitTransactionRecords)->handle($business, $owner, [$row]);

    expect(BusinessTransactionRecord::where('business_id', $business->id)->count())->toBe(1);
});

it('rejects a batch containing a plaintext email instead of a hash', function () {
    $business = Business::factory()->claimed()->create();
    $owner = transactionRecordsMemberWithRole($business, BusinessRole::Owner);
    $row = transactionRecordRow('ABC-1');
    $row['email_hash'] = 'jane@example.com';

    (new SubmitTransactionRecords)->handle($business, $owner, [$row]);
})->throws(ValidationException::class);

it('rejects a batch larger than the per-request limit', function () {
    config(['platform.verification.transaction_records.max_per_request' => 2]);
    $business = Business::factory()->claimed()->create();
    $owner = transactionRecordsMemberWithRole($business, BusinessRole::Owner);
    $rows = array_map(fn ($i) => transactionRecordRow('REF-'.$i), range(1, 3));

    (new SubmitTransactionRecords)->handle($business, $owner, $rows);
})->throws(ValidationException::class);

it('rejects a batch that would exceed the daily limit', function () {
    config(['platform.verification.transaction_records.max_per_day' => 2]);
    $business = Business::factory()->claimed()->create();
    $owner = transactionRecordsMemberWithRole($business, BusinessRole::Owner);
    (new SubmitTransactionRecords)->handle($business, $owner, [transactionRecordRow('ABC-1'), transactionRecordRow('ABC-2')]);

    (new SubmitTransactionRecords)->handle($business, $owner, [transactionRecordRow('ABC-3')]);
})->throws(ValidationException::class);

it('rejects a Responder submitting transaction records (needs Manage Integrations)', function () {
    $business = Business::factory()->claimed()->create();
    $responder = transactionRecordsMemberWithRole($business, BusinessRole::Responder);

    (new SubmitTransactionRecords)->handle($business, $responder, [transactionRecordRow('ABC-1')]);
})->throws(AuthorizationException::class);

it('rejects a user with no role on the business', function () {
    $business = Business::factory()->claimed()->create();
    $stranger = User::factory()->create();

    (new SubmitTransactionRecords)->handle($business, $stranger, [transactionRecordRow('ABC-1')]);
})->throws(AuthorizationException::class);
