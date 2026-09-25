<?php

use App\Domain\Staff\StaffRole;
use App\Models\User;
use App\Support\SessionLifetime;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('gives staff a 12-hour idle limit instead of 30 days (FR-001-16)', function () {
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    expect(SessionLifetime::minutesFor($staff))->toBe(SessionLifetime::STAFF_MINUTES);
    expect(SessionLifetime::minutesFor($staff))->toBe(60 * 12);
});

it('keeps the 30-day limit for a non-staff user', function () {
    $consumer = User::factory()->create();

    expect(SessionLifetime::minutesFor($consumer))->toBe(SessionLifetime::CONSUMER_MINUTES);
});

it('logs a staff member out after their shorter idle limit', function () {
    $staff = User::factory()->create();
    $staff->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    $this->actingAs($staff)
        ->withSession(['last_activity_at' => now()->subHours(13)->toIso8601String()])
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
