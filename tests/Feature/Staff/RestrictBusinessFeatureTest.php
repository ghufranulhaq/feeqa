<?php

use App\Actions\Moderation\CreateFlag;
use App\Actions\Staff\RestrictBusinessFeature;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\RestrictableFeature;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\Review;
use App\Models\User;
use App\Notifications\StatementOfReasonsNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function restrictionOwner(Business $business): User
{
    (new BusinessRolesSeeder)->run();
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole(BusinessRole::Owner->value);

    return $user;
}

it('restricts a feature and notifies business owners (FR-006-14 step 4)', function () {
    Notification::fake();
    $business = Business::factory()->create();
    $owner = restrictionOwner($business);
    $staff = User::factory()->create(['staff_role' => StaffRole::Moderator->value]);

    $result = (new RestrictBusinessFeature)->handle($staff, $business, RestrictableFeature::Flagging, ReasonCode::NotGenuine);

    expect($result->hasFeatureRestricted(RestrictableFeature::Flagging))->toBeTrue();
    Notification::assertSentTo($owner, StatementOfReasonsNotification::class);
    expect(ComplianceLogEntry::where('target_id', $business->id)->where('action', 'business_feature_restricted')->exists())->toBeTrue();
});

it('is idempotent when the feature is already restricted', function () {
    $business = Business::factory()->create(['restricted_features' => [RestrictableFeature::Flagging->value]]);
    restrictionOwner($business);
    $staff = User::factory()->create(['staff_role' => StaffRole::Moderator->value]);

    $result = (new RestrictBusinessFeature)->handle($staff, $business, RestrictableFeature::Flagging, ReasonCode::NotGenuine);

    expect($result->restricted_features)->toBe([RestrictableFeature::Flagging->value]);
});

it('rejects a non-staff user restricting a feature', function () {
    $business = Business::factory()->create();

    (new RestrictBusinessFeature)->handle(User::factory()->create(), $business, RestrictableFeature::Flagging, ReasonCode::NotGenuine);
})->throws(AuthorizationException::class);

it('blocks a business flag once flagging is restricted', function () {
    $business = Business::factory()->create(['restricted_features' => [RestrictableFeature::Flagging->value]]);
    $review = Review::factory()->for($business)->create();
    $owner = restrictionOwner($business);

    (new CreateFlag)->handle($review, ReasonCode::NotGenuine, reporter: $owner, actingBusiness: $business);
})->throws(AuthorizationException::class);
