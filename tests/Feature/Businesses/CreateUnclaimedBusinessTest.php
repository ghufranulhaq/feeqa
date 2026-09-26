<?php

use App\Actions\Businesses\CreateUnclaimedBusiness;
use App\Domain\Businesses\BusinessStatus;
use App\Jobs\RunBusinessListingCheck;
use App\Models\Business;
use App\Models\Category;
use App\Support\Businesses\DuplicateBusinessException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('creates an unclaimed business from a domain, guessing its name (FR-002-08)', function () {
    Queue::fake();
    $category = Category::factory()->create();

    $business = app(CreateUnclaimedBusiness::class)->handle([
        'domain' => 'https://www.skyhop-travel.com/about',
        'name' => null,
        'country' => null,
        'city' => null,
        'category_id' => $category->id,
    ]);

    expect($business->name)->toBe('Skyhop Travel')
        ->and($business->primary_domain)->toBe('skyhop-travel.com')
        ->and($business->status)->toBe(BusinessStatus::Pending)
        ->and($business->primary_category_id)->toBe($category->id);

    Queue::assertPushed(RunBusinessListingCheck::class);
});

it('creates an unclaimed business from name + country + city (FR-002-08)', function () {
    Queue::fake();

    $business = app(CreateUnclaimedBusiness::class)->handle([
        'domain' => null,
        'name' => 'Skyhop Travel',
        'country' => 'GB',
        'city' => 'London',
        'category_id' => null,
    ]);

    expect($business->name)->toBe('Skyhop Travel')
        ->and($business->country)->toBe('GB')
        ->and($business->city)->toBe('London')
        ->and($business->primary_domain)->toBeNull()
        ->and($business->status)->toBe(BusinessStatus::Pending);
});

it('defaults to "Other / Uncategorised" when no category is given (FR-002-35)', function () {
    Queue::fake();
    Category::factory()->create(['slug' => Category::OTHER_UNCATEGORISED_SLUG, 'is_system' => true]);

    $business = app(CreateUnclaimedBusiness::class)->handle([
        'domain' => 'no-category.example', 'name' => null, 'country' => null, 'city' => null, 'category_id' => null,
    ]);

    expect($business->primaryCategory->slug)->toBe(Category::OTHER_UNCATEGORISED_SLUG);
});

it('throws with the existing business when the domain is already listed (FR-002-09)', function () {
    Queue::fake();
    $existing = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);

    try {
        app(CreateUnclaimedBusiness::class)->handle([
            'domain' => 'https://skyhop-travel.com', 'name' => null, 'country' => null, 'city' => null, 'category_id' => null,
        ]);
        $this->fail('Expected a DuplicateBusinessException.');
    } catch (DuplicateBusinessException $e) {
        expect($e->existing->is($existing))->toBeTrue();
    }

    Queue::assertNothingPushed();
});

it('throws with the existing business for a fuzzy name+city duplicate (FR-002-09)', function () {
    Queue::fake();
    $existing = Business::factory()->create(['name' => 'Skyhop Travel', 'country' => 'GB', 'city' => 'London']);

    try {
        app(CreateUnclaimedBusiness::class)->handle([
            'domain' => null, 'name' => 'Skyhop Trvl', 'country' => 'GB', 'city' => 'London', 'category_id' => null,
        ]);
        $this->fail('Expected a DuplicateBusinessException.');
    } catch (DuplicateBusinessException $e) {
        expect($e->existing->is($existing))->toBeTrue();
    }
});

it('treats a different subdomain as a different business, not a duplicate (edge cases table)', function () {
    Queue::fake();
    Business::factory()->create(['primary_domain' => 'brand.com']);

    $business = app(CreateUnclaimedBusiness::class)->handle([
        'domain' => 'shop.brand.com', 'name' => null, 'country' => null, 'city' => null, 'category_id' => null,
    ]);

    expect($business->primary_domain)->toBe('shop.brand.com');
});
