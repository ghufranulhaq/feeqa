<?php

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('always returns an empty mentions collection until spec 003 exists (FR-002-27)', function () {
    $business = Business::factory()->create();

    expect($business->mentions())->toBeInstanceOf(Collection::class)
        ->and($business->mentions())->toBeEmpty();
});

it('shows the "Mentioned in reviews" section on the profile page, separate from "coming soon" (FR-002-27)', function () {
    $business = Business::factory()->create();

    $this->get(route('businesses.show', $business->slug))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('mentions', [])
            ->missing('pending_features.7')
        );
});
