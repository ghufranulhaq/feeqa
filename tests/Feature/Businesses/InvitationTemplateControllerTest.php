<?php

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\InvitationTemplate;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets an Owner create a template over HTTP (FR-005-13)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $response = $this->actingAs($owner)->post(route('business.invitation-templates.store', $business), [
        'locale' => 'en-GB',
        'subject' => 'Tell us about your trip',
        'body' => "Hi {recipient_name},\n\n{review_link}\n\n{unsubscribe_link}",
    ]);

    $response->assertOk()->assertJson(['locale' => 'en-GB', 'is_active' => true]);
    expect(InvitationTemplate::where('business_id', $business->id)->count())->toBe(1);
});

it('rejects a template with an incentive over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    $this->actingAs($owner)->post(route('business.invitation-templates.store', $business), [
        'locale' => 'en-GB',
        'subject' => 'Tell us about your trip',
        'body' => "Get a free gift for your review\n\n{review_link}\n\n{unsubscribe_link}",
    ])->assertSessionHasErrors('body');

    expect(InvitationTemplate::where('business_id', $business->id)->count())->toBe(0);
});

it('forbids a Responder from managing templates over HTTP (edge case table)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->claimed()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);

    $this->actingAs($responder)->post(route('business.invitation-templates.store', $business), [
        'locale' => 'en-GB',
        'subject' => 'Tell us about your trip',
        'body' => "Hi {recipient_name},\n\n{review_link}\n\n{unsubscribe_link}",
    ])->assertForbidden();
});

it('requires authentication', function () {
    $business = Business::factory()->claimed()->create();

    $this->post(route('business.invitation-templates.store', $business), [])
        ->assertRedirect(route('login'));
});
