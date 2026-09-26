<?php

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('requires sign-in to see or use the create-business form (FR-002-08)', function () {
    $this->get(route('businesses.create'))->assertRedirect(route('login'));
    $this->post(route('businesses.store'), [])->assertRedirect(route('login'));
});

it('shows the form with categories, including drafts and "Other / Uncategorised" (FR-002-35)', function () {
    Category::factory()->create(['name' => ['en-GB' => 'A Draft Industry'], 'launched' => false]);
    Category::factory()->create(['slug' => Category::OTHER_UNCATEGORISED_SLUG, 'name' => ['en-GB' => 'Other / Uncategorised'], 'is_system' => true]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('businesses.create'))->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('public/business-create'));

    $categoryNames = collect($response->viewData('page')['props']['categories'])->pluck('name');
    expect($categoryNames)->toContain('A Draft Industry')
        ->and($categoryNames)->toContain('Other / Uncategorised');
});

it('creates a business from a domain and redirects to its profile', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.store'), ['domain' => 'skyhop-travel.com'])
        ->assertRedirect(route('businesses.show', 'skyhop-travel'));

    expect(Business::where('primary_domain', 'skyhop-travel.com')->exists())->toBeTrue();
});

it('creates a business from name + country + city', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('businesses.store'), [
        'name' => 'Skyhop Travel',
        'country' => 'GB',
        'city' => 'London',
    ])->assertRedirect();

    expect(Business::where('name', 'Skyhop Travel')->where('city', 'London')->exists())->toBeTrue();
});

it('rejects giving neither a domain nor a name+country+city', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('businesses.store'), [])
        ->assertSessionHasErrors(['name', 'country', 'city']);
});

it('rejects giving both a domain and a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('businesses.store'), [
        'domain' => 'skyhop-travel.com',
        'name' => 'Skyhop Travel',
        'country' => 'GB',
        'city' => 'London',
    ])->assertSessionHasErrors(['domain']);
});

it('redirects back with a suggestion when a duplicate is found (FR-002-09)', function () {
    Queue::fake();
    $existing = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->from(route('businesses.create'))
        ->post(route('businesses.store'), ['domain' => 'skyhop-travel.com']);

    $response->assertRedirect(route('businesses.create'))
        ->assertSessionHasErrors('duplicate');
    expect(session('duplicate_suggestion'))->toBe(['slug' => $existing->slug, 'name' => $existing->name]);
});
