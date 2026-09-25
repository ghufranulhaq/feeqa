<?php

use App\Actions\Accounts\RequestDataExport;
use App\Domain\Businesses\BusinessRole;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\DataExport;
use App\Models\User;
use App\Notifications\DataExportReadyNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

it('builds a downloadable JSON export within the same request cycle (sync queue in tests) (FR-001-19)', function () {
    Notification::fake();
    $user = User::factory()->create(['country' => 'GB']);

    $export = (new RequestDataExport)->handle($user);

    expect($export->fresh()->status)->toBe('ready');
    expect($export->fresh()->file_path)->not->toBeNull();
    Storage::disk('local')->assertExists($export->fresh()->file_path);
    Notification::assertSentTo($user, DataExportReadyNotification::class);
});

it('includes profile data and consents in the export archive', function () {
    $user = User::factory()->create(['name' => 'Jordan Rivers', 'country' => 'GB']);
    $user->consents()->create([
        'terms_version' => '2026-01-01',
        'privacy_version' => '2026-01-01',
        'marketing_opt_in' => true,
        'consented_at' => now(),
    ]);

    $export = (new RequestDataExport)->handle($user);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->fresh()->file_path));
    $json = json_decode($zip->getFromName('data.json'), true);
    $zip->close();

    expect($json['account']['profile']['name'])->toBe('Jordan Rivers');
    expect($json['account']['profile']['country'])->toBe('GB');
    expect($json['account']['consents'])->toHaveCount(1);
    expect($json['account']['consents'][0]['marketing_opt_in'])->toBeTrue();
});

it('includes business memberships and invitations sent/received (spec §6: data inventory)', function () {
    $this->seed(BusinessRolesSeeder::class);
    $business = Business::create(['name' => 'Acme Travel']);
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $owner->assignRole(BusinessRole::Owner->value);
    BusinessInvitation::issue($business, $owner, 'invitee@example.com', BusinessRole::Analyst);

    $export = (new RequestDataExport)->handle($owner);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->fresh()->file_path));
    $json = json_decode($zip->getFromName('data.json'), true);
    $zip->close();

    expect($json['account']['business_memberships'])->toHaveCount(1);
    expect($json['account']['business_memberships'][0]['role'])->toBe(BusinessRole::Owner->value);
    expect($json['account']['business_invitations_sent'])->toHaveCount(1);
    expect($json['account']['business_invitations_sent'][0]['invited_email'])->toBe('invitee@example.com');
});

it('never contains the password hash anywhere in the export', function () {
    $user = User::factory()->create();

    $export = (new RequestDataExport)->handle($user);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->fresh()->file_path));
    $json = $zip->getFromName('data.json');
    $zip->close();

    expect($json)->not->toContain($user->password);
});

it('returns the pending export instead of starting a second one within 24 hours (edge case)', function () {
    $user = User::factory()->create();

    $first = (new RequestDataExport)->handle($user);
    $second = (new RequestDataExport)->handle($user);

    expect($second->id)->toBe($first->id);
    expect(DataExport::where('user_id', $user->id)->count())->toBe(1);
});

it('starts a new export when the last one was requested over 24 hours ago', function () {
    $user = User::factory()->create();
    $first = (new RequestDataExport)->handle($user);
    $first->forceFill(['requested_at' => now()->subDays(2)])->save();

    $second = (new RequestDataExport)->handle($user);

    expect($second->id)->not->toBe($first->id);
    expect(DataExport::where('user_id', $user->id)->count())->toBe(2);
});

it('lets the owner download via the signed link', function () {
    $user = User::factory()->create();
    $export = (new RequestDataExport)->handle($user);

    $url = URL::temporarySignedRoute('data-exports.download', $export->fresh()->expires_at, ['dataExport' => $export->id]);

    $this->actingAs($user)->get($url)->assertOk();
});

it('rejects a download by someone other than the owner', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $export = (new RequestDataExport)->handle($user);

    $url = URL::temporarySignedRoute('data-exports.download', $export->fresh()->expires_at, ['dataExport' => $export->id]);

    $this->actingAs($other)->get($url)->assertForbidden();
});

it('rejects a download once the signed link has expired', function () {
    $user = User::factory()->create();
    $export = (new RequestDataExport)->handle($user);

    $url = URL::temporarySignedRoute('data-exports.download', now()->subMinute(), ['dataExport' => $export->id]);

    $this->actingAs($user)->get($url)->assertForbidden();
});
