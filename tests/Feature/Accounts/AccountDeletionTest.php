<?php

use App\Actions\Accounts\EraseUserAccount;
use App\Models\Consent;
use App\Models\DataExport;
use App\Models\User;
use App\Models\UserProvider;
use App\Notifications\AccountDeletionScheduledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('schedules deletion instead of deleting immediately, and sends a confirmation email (FR-001-20)', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->not->toBeNull();
    expect($user->fresh()->deletion_requested_at)->not->toBeNull();
    Notification::assertSentTo($user, AccountDeletionScheduledNotification::class);
});

it('lets a passwordless/social-only account (no password) delete without confirming one', function () {
    $user = User::factory()->create(['password' => null]);

    $this->actingAs($user)
        ->delete('/settings/profile', [])
        ->assertRedirect('/');

    expect($user->fresh()->deletion_requested_at)->not->toBeNull();
});

it('still requires the correct password when one is set', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->deletion_requested_at)->toBeNull();
});

it('hides the public profile immediately once deletion is requested (FR-001-20)', function () {
    $user = User::factory()->create();

    $this->get(route('reviewers.show', $user))->assertOk();

    $user->forceFill(['deletion_requested_at' => now()])->save();

    $this->get(route('reviewers.show', $user))->assertNotFound();
});

it('signs a pending-deletion account back out on their next request', function () {
    $user = User::factory()->create();
    $user->forceFill(['deletion_requested_at' => now()])->save();

    $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));

    $this->assertGuest();
});

it('leaves an account alone before the 30-day window has passed', function () {
    $user = User::factory()->create(['name' => 'Jordan Rivers']);
    $user->forceFill(['deletion_requested_at' => now()->subDays(10)])->save();

    $this->artisan('accounts:erase-pending-deletions');

    expect($user->fresh()->name)->toBe('Jordan Rivers');
});

it('pseudonymises the account once 30 days have passed', function () {
    Storage::fake('public');
    $user = User::factory()->create(['name' => 'Jordan Rivers', 'avatar_path' => 'avatars/x.jpg']);
    Storage::disk('public')->put('avatars/x.jpg', 'fake-image-bytes');
    UserProvider::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => '123']);
    Consent::create([
        'user_id' => $user->id, 'terms_version' => 'v1', 'privacy_version' => 'v1',
        'marketing_opt_in' => false, 'consented_at' => now(),
    ]);
    DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => now()->timestamp]);
    $user->forceFill(['deletion_requested_at' => now()->subDays(31)])->save();

    $this->artisan('accounts:erase-pending-deletions');

    $fresh = $user->fresh();
    expect($fresh->name)->toBe('Deleted user');
    expect($fresh->email)->toBe("deleted-{$user->id}@erased.invalid");
    expect($fresh->password)->toBeNull();
    expect($fresh->country)->toBeNull();
    expect($fresh->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('avatars/x.jpg');
    expect(UserProvider::where('user_id', $user->id)->exists())->toBeFalse();
    expect(Consent::where('user_id', $user->id)->exists())->toBeFalse();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});

it('deletes stored export files when erasing the account', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    Storage::disk('local')->put('exports/test.zip', 'zip-bytes');
    DataExport::create([
        'user_id' => $user->id, 'status' => 'ready', 'file_path' => 'exports/test.zip',
        'requested_at' => now(), 'ready_at' => now(), 'expires_at' => now()->addDays(7),
    ]);

    (new EraseUserAccount)->handle($user);

    Storage::disk('local')->assertMissing('exports/test.zip');
    expect(DataExport::where('user_id', $user->id)->exists())->toBeFalse();
});
