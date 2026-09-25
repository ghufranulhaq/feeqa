<?php

use App\Http\Controllers\Auth\SocialLoginController;
use App\Models\User;
use App\Models\UserProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteTwoUser;

beforeEach(function () {
    config(['services.google.client_id' => 'test-google-client-id']);
});

it('only lists providers that have a client_id configured (plan D6)', function () {
    config(['services.google.client_id' => 'x', 'services.facebook.client_id' => null, 'services.apple.client_id' => null]);

    expect(SocialLoginController::available())->toBe(['google']);
});

it('404s for a provider name that is not supported or not configured', function () {
    $this->get('/login/not-a-real-provider/redirect')->assertNotFound();
});

it('creates a new account for a first-time verified social sign-in (FR-001-01, FR-001-06)', function () {
    Socialite::fake('google', SocialiteTwoUser::fake([
        'id' => 'google-123',
        'email' => 'new-social@example.com',
        'email_verified' => true,
    ]));

    $this->get('/login/google/callback')->assertRedirect(route('signup.complete'));

    expect(User::where('email', 'new-social@example.com')->exists())->toBeFalse();
    expect(session('pending_signup_email'))->toBe('new-social@example.com');
    expect(session('pending_signup_email_verified'))->toBeTrue();
});

it('finishes creating the account and links the provider via complete-profile', function () {
    Socialite::fake('google', SocialiteTwoUser::fake([
        'id' => 'google-123',
        'email' => 'new-social@example.com',
        'email_verified' => true,
    ]));

    $this->get('/login/google/callback');

    $this->post('/signup/complete', [
        'name' => 'Jordan Rivers',
        'country' => 'GB',
        'over_18' => true,
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'new-social@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->email_verified_at)->not->toBeNull();
    expect(UserProvider::where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();
});

it('links to an existing account by verified email instead of creating a duplicate (FR-001-06)', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    Socialite::fake('google', SocialiteTwoUser::fake([
        'id' => 'google-123',
        'email' => 'existing@example.com',
        'email_verified' => true,
    ]));

    $this->get('/login/google/callback')->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
    expect(UserProvider::where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();
});

it('signs straight in on a second visit via the already-linked provider', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    UserProvider::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-123']);

    Socialite::fake('google', SocialiteTwoUser::fake([
        'id' => 'google-123',
        'email' => 'existing@example.com',
        'email_verified' => true,
    ]));

    $this->get('/login/google/callback')->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('refuses to link an unverified provider email onto somebody else\'s existing account (edge case)', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    Socialite::fake('google', SocialiteTwoUser::fake([
        'id' => 'google-999',
        'email' => 'existing@example.com',
        'email_verified' => false,
    ]));

    $this->get('/login/google/callback')->assertRedirect(route('login'));

    $this->assertGuest();
    expect(UserProvider::where('provider_user_id', 'google-999')->exists())->toBeFalse();
});

it('treats an unverified provider email on a brand new sign-up as needing email verification (edge case)', function () {
    Socialite::fake('google', SocialiteTwoUser::fake([
        'id' => 'google-999',
        'email' => 'unverified-new@example.com',
        'email_verified' => false,
    ]));

    $this->get('/login/google/callback')->assertRedirect(route('signup.complete'));

    expect(session('pending_signup_email_verified'))->toBeFalse();

    $this->post('/signup/complete', [
        'name' => 'Jordan Rivers',
        'country' => 'GB',
        'over_18' => true,
    ]);

    $user = User::where('email', 'unverified-new@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
});

it('treats facebook emails as always verified', function () {
    config(['services.facebook.client_id' => 'test-facebook-client-id']);

    Socialite::fake('facebook', SocialiteTwoUser::fake([
        'id' => 'fb-1',
        'email' => 'fb-user@example.com',
    ]));

    $this->get('/login/facebook/callback')->assertRedirect(route('signup.complete'));

    expect(session('pending_signup_email_verified'))->toBeTrue();
});

it('creates a new account for a first-time sign-in via Apple (spec 001 §6: all five methods)', function () {
    config(['services.apple.client_id' => 'test-apple-client-id']);

    Socialite::fake('apple', SocialiteTwoUser::fake([
        'id' => 'apple-1',
        'email' => 'apple-user@example.com',
        'email_verified' => true,
    ]));

    $this->get('/login/apple/callback')->assertRedirect(route('signup.complete'));

    expect(session('pending_signup_email'))->toBe('apple-user@example.com');
    expect(session('pending_signup_email_verified'))->toBeTrue();
});

it('treats an unverified Apple email the same way as an unverified Google one', function () {
    config(['services.apple.client_id' => 'test-apple-client-id']);

    Socialite::fake('apple', SocialiteTwoUser::fake([
        'id' => 'apple-2',
        'email' => 'apple-unverified@example.com',
        'email_verified' => false,
    ]));

    $this->get('/login/apple/callback')->assertRedirect(route('signup.complete'));

    expect(session('pending_signup_email_verified'))->toBeFalse();
});
