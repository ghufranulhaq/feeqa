<?php

use App\Models\Business;
use App\Models\Location;
use App\Models\ReviewDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires sign-in to save a draft (FR-003-10 edge case: unauthenticated user)', function () {
    $business = Business::factory()->create();

    $this->post(route('businesses.review-draft.store', $business), ['payload' => ['title' => 'Great trip']])
        ->assertRedirect(route('login'));

    expect(ReviewDraft::count())->toBe(0);
});

it('saves a draft over HTTP and restores it (FR-003-10)', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.review-draft.store', $business), ['payload' => ['title' => 'Great trip']])
        ->assertOk();

    $this->actingAs($user)
        ->get(route('businesses.review-draft.show', $business))
        ->assertOk()
        ->assertJson(['payload' => ['title' => 'Great trip']]);
});

it('scopes the draft to a location when one is given', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();

    $this->actingAs($user)->post(route('businesses.review-draft.store', $business), [
        'location_id' => $location->id,
        'payload' => ['title' => 'Location draft'],
    ])->assertOk();

    $this->actingAs($user)
        ->get(route('businesses.review-draft.show', $business))
        ->assertJson(['payload' => null]);

    $this->actingAs($user)
        ->get(route('businesses.review-draft.show', $business).'?location_id='.$location->id)
        ->assertJson(['payload' => ['title' => 'Location draft']]);
});

it('rejects a location_id that does not belong to the business', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create();
    $otherBusinessLocation = Location::factory()->create();

    $this->actingAs($user)
        ->post(route('businesses.review-draft.store', $business), [
            'location_id' => $otherBusinessLocation->id,
            'payload' => ['title' => 'Great trip'],
        ])
        ->assertInvalid(['location_id']);
});
