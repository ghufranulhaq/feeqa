<?php

use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function bccControllerOwner(Business $business): User
{
    $owner = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);

    return $owner;
}

it('lets an Owner import a BCC email over HTTP (FR-005-01 bcc)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $owner = bccControllerOwner($business);
    $eml = "From: Orders <orders@{$business->primary_domain}>\n".
        "To: jane@example.com\n".
        "Subject: Your booking SK-778899\n".
        "Authentication-Results: mx.feeqa.test; spf=pass smtp.mailfrom=orders@{$business->primary_domain}; dkim=pass header.d={$business->primary_domain}\n".
        "\n".
        'Reference: SK-778899';
    $file = UploadedFile::fake()->createWithContent('message.eml', $eml);

    $response = $this->actingAs($owner)->post(route('business.bcc-invitations.import-email', $business), [
        'file' => $file,
    ]);

    $response->assertOk()->assertJson(['outcome' => 'imported']);
});

it('forbids a Responder from importing BCC email over HTTP', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $responder = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $responder->assignRole(BusinessRole::Responder->value);
    $file = UploadedFile::fake()->createWithContent('message.eml', "From: a@b.example\nTo: c@d.example\nSubject: x\n\nbody");

    $this->actingAs($responder)->post(route('business.bcc-invitations.import-email', $business), [
        'file' => $file,
    ])->assertForbidden();
});

it('lets an Owner rotate the BCC address over HTTP (FR-005-06)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $owner = bccControllerOwner($business);
    $originalAddress = $business->bcc_address;

    $response = $this->actingAs($owner)->post(route('business.bcc-invitations.rotate-address', $business));

    $response->assertOk();
    expect($response->json('bcc_address'))->not->toBe($originalAddress);
});

it('lets an Owner update BCC settings over HTTP (FR-005-07)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::factory()->create();
    $owner = bccControllerOwner($business);

    $response = $this->actingAs($owner)->patch(route('business.bcc-invitations.update-settings', $business), [
        'registered_senders' => ['bookings@partner-otas.example'],
        'reference_pattern' => '/BK-\d{4}/',
    ]);

    $response->assertOk()->assertJson([
        'registered_senders' => ['bookings@partner-otas.example'],
        'reference_pattern' => '/BK-\d{4}/',
    ]);
});
