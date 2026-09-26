<?php

use App\Actions\Businesses\RespondToBusinessReclaim;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\ClaimStatus;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\User;
use App\Notifications\BusinessClaimRejectedNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function awaitingReclaim(): array
{
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $claimant = User::factory()->create();
    $claim = BusinessClaim::create([
        'business_id' => $business->id,
        'user_id' => $claimant->id,
        'method' => 'email',
        'status' => ClaimStatus::AwaitingOwnerResponse,
        'owner_response_deadline' => now()->addDays(7),
    ]);

    return [$business, $owner, $claimant, $claim];
}

it('lets an Owner approve a re-claim, adding the claimant as an Owner too (FR-002-14)', function () {
    [$business, $owner, $claimant, $claim] = awaitingReclaim();

    (new RespondToBusinessReclaim)->handle($claim, $owner, approve: true);

    expect($business->hasBusinessRole($claimant, BusinessRole::Owner))->toBeTrue()
        ->and($claim->fresh()->status)->toBe(ClaimStatus::Approved);
});

it('lets an Owner reject a re-claim and notifies the claimant', function () {
    Notification::fake();
    [, $owner, $claimant, $claim] = awaitingReclaim();

    (new RespondToBusinessReclaim)->handle($claim, $owner, approve: false);

    expect($claim->fresh()->status)->toBe(ClaimStatus::Rejected);
    Notification::assertSentTo($claimant, BusinessClaimRejectedNotification::class);
});

it('rejects a non-Owner responding', function () {
    [, , , $claim] = awaitingReclaim();
    $notOwner = User::factory()->create();

    (new RespondToBusinessReclaim)->handle($claim, $notOwner, approve: true);
})->throws(AuthorizationException::class);

it('rejects responding to a claim that is not awaiting a response', function () {
    [, $owner, , $claim] = awaitingReclaim();
    $claim->forceFill(['status' => ClaimStatus::Approved])->save();

    (new RespondToBusinessReclaim)->handle($claim->fresh(), $owner, approve: true);
})->throws(ValidationException::class);
