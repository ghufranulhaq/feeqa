<?php

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\BusinessVerificationRequest;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets a Responder request verification over HTTP (FR-004-18)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);
    $review = Review::factory()->for($business)->create();

    $this->actingAs($responder)
        ->post(route('business.reviews.verification-request.store', [$business, $review]))
        ->assertRedirect();

    expect(BusinessVerificationRequest::where('review_id', $review->id)->exists())->toBeTrue();
});

it('forbids an Analyst from requesting verification over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $analyst = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $analyst->assignRole(BusinessRole::Analyst->value);
    $review = Review::factory()->for($business)->create();

    $this->actingAs($analyst)
        ->post(route('business.reviews.verification-request.store', [$business, $review]))
        ->assertForbidden();
});
