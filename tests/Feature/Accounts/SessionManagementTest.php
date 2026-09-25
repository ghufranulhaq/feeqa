<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('logs the user out once they exceed their idle limit (FR-001-16)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['last_activity_at' => now()->subDays(31)->toIso8601String()])
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('stays signed in within the idle limit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['last_activity_at' => now()->subMinutes(5)->toIso8601String()])
        ->get('/dashboard')
        ->assertOk();

    $this->assertAuthenticatedAs($user);
});

it('lists only the authenticated user\'s own sessions (FR-001-16)', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    DB::table('sessions')->insert([
        ['id' => 'session-mine', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/100', 'payload' => 'x', 'last_activity' => now()->timestamp],
        ['id' => 'session-other', 'user_id' => $other->id, 'ip_address' => '127.0.0.2', 'user_agent' => 'Mozilla/5.0', 'payload' => 'x', 'last_activity' => now()->timestamp],
    ]);

    $response = $this->actingAs($user)->get('/settings/sessions');

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('settings/sessions')
        ->has('sessions', 1)
        ->where('sessions.0.id', 'session-mine')
    );
});

it('lets a user revoke one of their own sessions', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'session-to-revoke', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0', 'payload' => 'x', 'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)->delete('/settings/sessions/session-to-revoke');

    expect(DB::table('sessions')->where('id', 'session-to-revoke')->exists())->toBeFalse();
});

it('does not let a user revoke someone else\'s session', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'not-mine', 'user_id' => $other->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Mozilla/5.0', 'payload' => 'x', 'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)->delete('/settings/sessions/not-mine');

    expect(DB::table('sessions')->where('id', 'not-mine')->exists())->toBeTrue();
});
