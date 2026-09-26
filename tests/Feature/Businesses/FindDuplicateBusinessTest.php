<?php

use App\Actions\Businesses\FindDuplicateBusiness;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('finds an existing business by exact normalised domain (FR-002-09)', function () {
    $existing = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);

    expect((new FindDuplicateBusiness)->byDomain('skyhop-travel.com')->is($existing))->toBeTrue();
});

it('finds nothing for a domain nobody has (FR-002-09)', function () {
    expect((new FindDuplicateBusiness)->byDomain('nobody-has-this.example'))->toBeNull();
});

it('finds a fuzzy name+city match in the same country (FR-002-09)', function () {
    $existing = Business::factory()->create(['name' => 'Skyhop Travel', 'country' => 'GB', 'city' => 'London']);

    $match = (new FindDuplicateBusiness)->byNameAndCity('Skyhop Trvl', 'GB', 'London');

    expect($match?->is($existing))->toBeTrue();
});

it('does not match the same name in a different city (FR-002-09)', function () {
    Business::factory()->create(['name' => 'Skyhop Travel', 'country' => 'GB', 'city' => 'London']);

    expect((new FindDuplicateBusiness)->byNameAndCity('Skyhop Travel', 'GB', 'Manchester'))->toBeNull();
});

it('does not match an unrelated name in the same city', function () {
    Business::factory()->create(['name' => 'Skyhop Travel', 'country' => 'GB', 'city' => 'London']);

    expect((new FindDuplicateBusiness)->byNameAndCity('Acme Airlines', 'GB', 'London'))->toBeNull();
});
