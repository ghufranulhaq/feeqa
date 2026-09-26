<?php

use App\Actions\Businesses\RenameBusinessSlug;
use App\Models\Business;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('shows an unclaimed business with the FR-002-05 notice', function () {
    $business = Business::factory()->create(['name' => 'Skyhop Travel', 'description' => 'A regional airline.']);

    $this->get(route('businesses.show', $business->slug))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('public/business-profile')
            ->where('business.name', 'Skyhop Travel')
            ->where('business.is_claimed', false)
            ->where('business.description', 'A regional airline.')
        );
});

it('shows the claimed label and date for a claimed business (FR-002-04)', function () {
    $business = Business::factory()->claimed()->create();

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('business.is_claimed', true)
            ->where('business.claimed_at', $business->claimed_at->toDateString())
        );
});

it('shows primary and secondary categories (FR-002-04)', function () {
    $primary = Category::factory()->create(['name' => ['en-GB' => 'Airlines']]);
    $secondary = Category::factory()->create(['name' => ['en-GB' => 'Travel Agencies & OTAs']]);
    $business = Business::factory()->create(['primary_category_id' => $primary->id]);
    $business->secondaryCategories()->attach($secondary->id);

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('business.primary_category.name', 'Airlines')
            ->where('business.secondary_categories.0.name', 'Travel Agencies & OTAs')
        );
});

it('lists what a later spec still owns, instead of faking the data (FR-002-04)', function () {
    $business = Business::factory()->create();

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('pending_features', 8)
            ->where('pending_features.0.key', 'review_score')
        );
});

it('404s for a slug that never existed', function () {
    $this->get('/business/does-not-exist')->assertNotFound();
});

it('redirects permanently from an old slug after a rename (FR-002-02)', function () {
    $business = Business::factory()->create(['slug' => 'old-name']);

    (new RenameBusinessSlug)->handle($business, 'new-name');

    $this->get('/business/old-name')
        ->assertRedirect(route('businesses.show', 'new-name'))
        ->assertStatus(301);

    $this->get(route('businesses.show', 'new-name'))->assertOk();
});

it('is reachable without being signed in', function () {
    $business = Business::factory()->create();

    $this->get(route('businesses.show', $business->slug))->assertOk();
});
