<?php

use App\Models\PasswordlessCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The array cache driver (forced for tests) persists across tests in
    // the same run — rate-limit counters must not leak between them.
    Cache::flush();
});

it('locks out login after 5 failed attempts for the same account (FR-001-17)', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    // The 6th attempt is blocked even with the CORRECT password.
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('locks out login after 5 failed attempts from the same IP across different accounts', function () {
    $users = User::factory()->count(5)->create();

    foreach ($users as $user) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $sixth = User::factory()->create();

    $this->post('/login', ['email' => $sixth->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('does not count a successful login toward the limit', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 4; $i++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('locks out passwordless code verification after 5 wrong codes', function () {
    User::factory()->create(['email' => 'known@example.com']);
    PasswordlessCode::issueFor('known@example.com');

    for ($i = 0; $i < 5; $i++) {
        $this->post('/login/passwordless/verify', ['email' => 'known@example.com', 'code' => '000000']);
    }

    $realCode = PasswordlessCode::issueFor('known@example.com');

    $this->post('/login/passwordless/verify', ['email' => 'known@example.com', 'code' => $realCode->code])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

it('locks out repeated password reset link requests for the same account', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post('/forgot-password', ['email' => $user->email]);
    }

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHasErrors('email');
});
