<?php

use App\Models\User;
use App\Notifications\SignInInsteadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('does not reveal that an account already exists (edge case table)', function () {
    Notification::fake();
    $existing = User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post('/register', [
        'name' => 'Someone Else',
        'email' => 'taken@example.com',
        'password' => 'a-fine-password',
        'password_confirmation' => 'a-fine-password',
        'over_18' => true,
    ]);

    $response->assertSessionDoesntHaveErrors('email');
    $this->assertGuest();
    expect(User::count())->toBe(1);
    Notification::assertSentTo($existing, SignInInsteadNotification::class);
});

it('does not log the visitor in as the existing account', function () {
    Notification::fake();
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post('/register', [
        'name' => 'Someone Else',
        'email' => 'taken@example.com',
        'password' => 'a-fine-password',
        'password_confirmation' => 'a-fine-password',
        'over_18' => true,
    ]);

    $this->assertGuest();
});

it('still creates the account for a genuinely new email', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Brand New',
        'email' => 'brand-new@example.com',
        'password' => 'a-fine-password',
        'password_confirmation' => 'a-fine-password',
        'over_18' => true,
    ]);

    $this->assertAuthenticated();
    expect(User::where('email', 'brand-new@example.com')->exists())->toBeTrue();
    Notification::assertNothingSent();
});
