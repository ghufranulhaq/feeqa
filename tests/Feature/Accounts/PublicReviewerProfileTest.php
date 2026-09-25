<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('shows the display name, country, and member-since date (FR-001-07)', function () {
    $user = User::factory()->create(['name' => 'Jordan Rivers', 'country' => 'GB']);

    $this->get(route('reviewers.show', $user))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/reviewer-profile')
            ->where('reviewer.name', 'Jordan Rivers')
            ->where('reviewer.country', 'GB')
            ->where('reviewer.reviews_count', 0)
            ->where('reviewer.reviews', [])
        );
});

it('never exposes the email address or proof data on the public profile', function () {
    $user = User::factory()->create(['email' => 'private@example.com']);

    $response = $this->get(route('reviewers.show', $user));

    $response->assertOk();
    $response->assertDontSee('private@example.com', escape: false);

    $props = $response->viewData('page')['props']['reviewer'];
    expect($props)->not->toHaveKey('email');
    expect(array_keys($props))->toBe(['id', 'name', 'avatar_path', 'country', 'member_since', 'reviews_count', 'reviews']);
});

it('is reachable without being signed in', function () {
    $user = User::factory()->create();

    $this->get(route('reviewers.show', $user))->assertOk();
});
