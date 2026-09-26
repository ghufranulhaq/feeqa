<?php

use App\Domain\Businesses\BusinessRole;
use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets an Owner submit transaction records over HTTP (FR-004-12)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $response = $this->actingAs($owner)->post(route('business.transaction-records.store', $business), [
        'records' => [[
            'reference_hash' => TransactionRecordHash::reference('ABC-1'),
            'email_hash' => TransactionRecordHash::email('jane@example.com'),
            'transaction_date' => now()->subDays(2)->toDateString(),
        ]],
    ]);

    $response->assertOk()->assertJson(['stored' => 1]);
    expect(BusinessTransactionRecord::where('business_id', $business->id)->count())->toBe(1);
});

it('forbids a Responder from submitting transaction records over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);

    $this->actingAs($responder)->post(route('business.transaction-records.store', $business), [
        'records' => [[
            'reference_hash' => TransactionRecordHash::reference('ABC-1'),
            'email_hash' => TransactionRecordHash::email('jane@example.com'),
            'transaction_date' => now()->subDays(2)->toDateString(),
        ]],
    ])->assertForbidden();
});

it('requires authentication', function () {
    $business = Business::factory()->claimed()->create();

    $this->post(route('business.transaction-records.store', $business), ['records' => []])
        ->assertRedirect(route('login'));
});
