<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a user set their country (FR-001-05, FR-001-08)', function () {
    $user = User::factory()->create(['country' => null]);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'country' => 'GB',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->country)->toBe('GB');
});

it('rejects a country code that is not ISO 3166-1 alpha-2', function () {
    $user = User::factory()->create(['country' => 'GB']);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'country' => 'ZZ',
    ])->assertSessionHasErrors('country');

    expect($user->fresh()->country)->toBe('GB');
});

it('leaves the existing country alone when the field is left out of the request', function () {
    $user = User::factory()->create(['country' => 'FR']);

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->country)->toBe('FR');
});

it('rejects a locale the platform does not ship', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => $user->name,
        'email' => $user->email,
        'locale' => 'xx-XX',
    ])->assertSessionHasErrors('locale');
});

it('still enforces the display name rules on profile updates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => '',
        'email' => $user->email,
    ])->assertSessionHasErrors('name');
});
