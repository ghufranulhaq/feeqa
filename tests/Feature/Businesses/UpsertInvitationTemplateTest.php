<?php

use App\Actions\Businesses\UpsertInvitationTemplate;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\InvitationTemplate;
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

function invitationTemplateMemberWithRole(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

const TEMPLATE_BODY = "Hi {recipient_name},\n\nWe'd value your honest feedback.\n\n{review_link}\n\n{unsubscribe_link}";

it('creates a template for an Owner (FR-005-13)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = invitationTemplateMemberWithRole($business, BusinessRole::Owner);

    $template = (new UpsertInvitationTemplate)->handle($business, $owner, 'en-GB', 'Tell us about your trip', TEMPLATE_BODY);

    expect($template->is_active)->toBeTrue()
        ->and(InvitationTemplate::where('business_id', $business->id)->where('locale', 'en-GB')->count())->toBe(1);
});

it('updates the existing template for the same business and locale rather than duplicating it', function () {
    $business = Business::factory()->claimed()->create();
    $owner = invitationTemplateMemberWithRole($business, BusinessRole::Owner);

    (new UpsertInvitationTemplate)->handle($business, $owner, 'en-GB', 'First subject', TEMPLATE_BODY);
    (new UpsertInvitationTemplate)->handle($business, $owner, 'en-GB', 'Second subject', TEMPLATE_BODY);

    expect(InvitationTemplate::where('business_id', $business->id)->where('locale', 'en-GB')->count())->toBe(1)
        ->and(InvitationTemplate::where('business_id', $business->id)->first()->subject)->toBe('Second subject');
});

it('rejects a template mentioning an incentive', function () {
    $business = Business::factory()->claimed()->create();
    $owner = invitationTemplateMemberWithRole($business, BusinessRole::Owner);

    (new UpsertInvitationTemplate)->handle($business, $owner, 'en-GB', 'Subject', "Get a discount for your review\n\n{review_link}\n\n{unsubscribe_link}");
})->throws(ValidationException::class);

it('saves an unrecognised-locale template inactive and pending staff review (edge case table)', function () {
    $business = Business::factory()->claimed()->create();
    $owner = invitationTemplateMemberWithRole($business, BusinessRole::Owner);

    $template = (new UpsertInvitationTemplate)->handle($business, $owner, 'pt', 'Assunto', TEMPLATE_BODY);

    expect($template->is_active)->toBeFalse();
});

it('rejects a Responder managing templates (needs SendInvitations, edge case table)', function () {
    $business = Business::factory()->claimed()->create();
    $responder = invitationTemplateMemberWithRole($business, BusinessRole::Responder);

    (new UpsertInvitationTemplate)->handle($business, $responder, 'en-GB', 'Subject', TEMPLATE_BODY);
})->throws(AuthorizationException::class);

it('allows a link to the business\'s own registered domain', function () {
    $business = Business::factory()->claimed()->create(['primary_domain' => 'acme-travel.com']);
    $owner = invitationTemplateMemberWithRole($business, BusinessRole::Owner);

    $template = (new UpsertInvitationTemplate)->handle(
        $business,
        $owner,
        'en-GB',
        'Subject',
        "See our other trips at https://acme-travel.com/trips\n\n{review_link}\n\n{unsubscribe_link}",
    );

    expect($template->is_active)->toBeTrue();
});
