<?php

use App\Models\Consent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records consent with the current terms/privacy versions at registration (FR-001-21)', function () {
    config(['legal.terms_version' => '2099-01-01', 'legal.privacy_version' => '2099-02-02']);

    $this->post('/register', [
        'name' => 'Jordan Rivers',
        'email' => 'jordan@example.com',
        'password' => 'a-fine-password',
        'password_confirmation' => 'a-fine-password',
        'over_18' => true,
        'marketing_opt_in' => true,
    ]);

    $user = User::where('email', 'jordan@example.com')->firstOrFail();
    $consent = $user->consents()->firstOrFail();

    expect($consent->terms_version)->toBe('2099-01-01');
    expect($consent->privacy_version)->toBe('2099-02-02');
    expect($consent->marketing_opt_in)->toBeTrue();
    expect($consent->consented_at)->not->toBeNull();
});

it('defaults marketing opt-in to false when not checked', function () {
    $this->post('/register', [
        'name' => 'Jordan Rivers',
        'email' => 'jordan@example.com',
        'password' => 'a-fine-password',
        'password_confirmation' => 'a-fine-password',
        'over_18' => true,
    ]);

    $user = User::where('email', 'jordan@example.com')->firstOrFail();

    expect($user->consents()->firstOrFail()->marketing_opt_in)->toBeFalse();
});

it('needs reconsent when there is no consent on record', function () {
    $user = User::factory()->create();

    expect($user->needsReconsent())->toBeTrue();
});

it('does not need reconsent when the latest consent matches current versions', function () {
    $user = User::factory()->create();
    config(['legal.terms_version' => '2026-01-01', 'legal.privacy_version' => '2026-01-01']);

    Consent::create([
        'user_id' => $user->id,
        'terms_version' => '2026-01-01',
        'privacy_version' => '2026-01-01',
        'marketing_opt_in' => false,
        'consented_at' => now(),
    ]);

    expect($user->fresh()->needsReconsent())->toBeFalse();
});

it('needs reconsent once the terms version moves on (FR-001-21)', function () {
    $user = User::factory()->create();

    Consent::create([
        'user_id' => $user->id,
        'terms_version' => '2020-01-01',
        'privacy_version' => config('legal.privacy_version'),
        'marketing_opt_in' => false,
        'consented_at' => now(),
    ]);

    expect($user->fresh()->needsReconsent())->toBeTrue();
});
