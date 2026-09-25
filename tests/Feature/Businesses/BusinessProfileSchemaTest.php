<?php

use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\EmployeeSizeBand;
use App\Models\Business;
use App\Models\Category;

it('auto-generates a unique slug when none is given (FR-002-01)', function () {
    $first = Business::create(['name' => 'Acme Travel']);
    $second = Business::create(['name' => 'Acme Travel']);

    expect($first->slug)->toBe('acme-travel')
        ->and($second->slug)->toBe('acme-travel-2')
        ->and($second->slug)->not->toBe($first->slug);
});

it('keeps an explicitly given slug', function () {
    $business = Business::create(['name' => 'Acme Travel', 'slug' => 'acme']);

    expect($business->slug)->toBe('acme');
});

it('defaults status to unclaimed and employee_size_band to unknown', function () {
    $business = Business::create(['name' => 'Acme Travel']);

    expect($business->status)->toBe(BusinessStatus::Unclaimed)
        ->and($business->employee_size_band)->toBe(EmployeeSizeBand::Unknown);
});

it('casts JSON profile fields to arrays', function () {
    $business = Business::create([
        'name' => 'Acme Travel',
        'additional_domains' => ['acme.co.uk'],
        'address' => ['city' => 'London', 'country' => 'GB'],
        'social_links' => ['twitter' => 'https://twitter.com/acme'],
    ]);
    $business->refresh();

    expect($business->additional_domains)->toBe(['acme.co.uk'])
        ->and($business->address)->toBe(['city' => 'London', 'country' => 'GB'])
        ->and($business->social_links)->toBe(['twitter' => 'https://twitter.com/acme']);
});

it('relates a business to a primary and secondary categories (FR-002-03)', function () {
    $primary = Category::factory()->create();
    $secondary1 = Category::factory()->create();
    $secondary2 = Category::factory()->create();

    $business = Business::factory()->create(['primary_category_id' => $primary->id]);
    $business->secondaryCategories()->attach([$secondary1->id, $secondary2->id]);

    expect($business->primaryCategory->is($primary))->toBeTrue()
        ->and($business->secondaryCategories->pluck('id')->sort()->values()->all())
        ->toBe([$secondary1->id, $secondary2->id]);
});

it('produces a usable business via the factory', function () {
    $business = Business::factory()->create();

    expect($business->slug)->not->toBeEmpty()
        ->and($business->primary_domain)->not->toBeEmpty()
        ->and($business->country)->not->toBeEmpty()
        ->and($business->status)->toBe(BusinessStatus::Unclaimed);
});

it('sets claimed_at via the claimed() factory state', function () {
    $business = Business::factory()->claimed()->create();

    expect($business->status)->toBe(BusinessStatus::Claimed)
        ->and($business->claimed_at)->not->toBeNull();
});
