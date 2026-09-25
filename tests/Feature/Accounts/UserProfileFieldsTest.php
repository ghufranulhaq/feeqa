<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores the country, locale, and optional avatar every account needs (FR-001-05)', function () {
    $user = User::factory()->create([
        'country' => 'GB',
        'locale' => 'en-GB',
        'avatar_path' => null,
    ]);

    expect($user->fresh())
        ->country->toBe('GB')
        ->locale->toBe('en-GB')
        ->avatar_path->toBeNull();
});

it('allows country and avatar to start empty for accounts created before onboarding completes', function () {
    $user = User::factory()->create(['country' => null, 'avatar_path' => null]);

    expect($user->fresh()->country)->toBeNull();
});

it('records the 18+ confirmation as a timestamp (FR-001-03)', function () {
    $user = User::factory()->create();

    expect($user->fresh()->date_of_birth_confirmed_at)->toBeInstanceOf(Carbon\Carbon::class);
});
