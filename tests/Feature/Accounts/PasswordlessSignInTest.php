<?php

use App\Models\PasswordlessCode;
use App\Models\User;
use App\Notifications\PasswordlessSignInNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('emails a code and a magic link without revealing whether the account exists (FR-001-01)', function () {
    Notification::fake();

    $this->post('/login/passwordless', ['email' => 'new-person@example.com'])
        ->assertRedirect(route('login.passwordless.verify', ['email' => 'new-person@example.com']));

    Notification::assertSentOnDemand(PasswordlessSignInNotification::class);
    expect(PasswordlessCode::where('email', 'new-person@example.com')->exists())->toBeTrue();
});

it('signs an existing user in via the 6-digit code', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    $code = PasswordlessCode::issueFor('known@example.com');

    $this->post('/login/passwordless/verify', [
        'email' => 'known@example.com',
        'code' => $code->code,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('signs an existing user in via the magic link', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    $code = PasswordlessCode::issueFor('known@example.com');

    $this->get(route('login.passwordless.token', $code->token))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('verifies email as a side effect of proving inbox control', function () {
    $user = User::factory()->unverified()->create(['email' => 'known@example.com']);
    $code = PasswordlessCode::issueFor('known@example.com');

    $this->post('/login/passwordless/verify', [
        'email' => 'known@example.com',
        'code' => $code->code,
    ]);

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('rejects an invalid code', function () {
    User::factory()->create(['email' => 'known@example.com']);
    PasswordlessCode::issueFor('known@example.com');

    $this->post('/login/passwordless/verify', [
        'email' => 'known@example.com',
        'code' => '000000',
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('rejects a code that has already been used', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    $code = PasswordlessCode::issueFor('known@example.com');
    $code->consume();

    $this->post('/login/passwordless/verify', [
        'email' => 'known@example.com',
        'code' => $code->code,
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('rejects an expired code', function () {
    $user = User::factory()->create(['email' => 'known@example.com']);
    $code = PasswordlessCode::issueFor('known@example.com');
    $code->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->post('/login/passwordless/verify', [
        'email' => 'known@example.com',
        'code' => $code->code,
    ])->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('hands a brand new email off to complete-profile instead of creating the account immediately', function () {
    $code = PasswordlessCode::issueFor('brand-new@example.com');

    $this->post('/login/passwordless/verify', [
        'email' => 'brand-new@example.com',
        'code' => $code->code,
    ])->assertRedirect(route('signup.complete'));

    $this->assertGuest();
    expect(User::where('email', 'brand-new@example.com')->exists())->toBeFalse();
});

it('completes sign-up with a display name, country, and 18+ confirmation', function () {
    PasswordlessCode::issueFor('brand-new@example.com');
    session(['pending_signup_email' => 'brand-new@example.com']);

    $this->post('/signup/complete', [
        'name' => 'Jordan Rivers',
        'country' => 'GB',
        'over_18' => true,
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'brand-new@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Jordan Rivers');
    expect($user->country)->toBe('GB');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->consents()->exists())->toBeTrue();
});

it('refuses complete-profile without a pending verified email in session', function () {
    $this->get('/signup/complete')->assertRedirect(route('login'));
    $this->post('/signup/complete', ['name' => 'X', 'country' => 'GB', 'over_18' => true])
        ->assertRedirect(route('login'));
});
