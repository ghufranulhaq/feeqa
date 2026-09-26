<?php

use App\Actions\Businesses\RenameBusinessSlug;
use App\Models\Business;
use App\Models\BusinessSlugRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects a slug already used by another business (FR-002-02)', function () {
    Business::factory()->create(['slug' => 'taken']);
    $business = Business::factory()->create(['slug' => 'original']);

    (new RenameBusinessSlug)->handle($business, 'taken');
})->throws(InvalidArgumentException::class);

it('is a no-op when the slug does not actually change', function () {
    $business = Business::factory()->create(['slug' => 'same']);

    (new RenameBusinessSlug)->handle($business, 'same');

    expect(BusinessSlugRedirect::count())->toBe(0);
});

it('drops a stale redirect if the freed-up slug is reused (FR-002-02)', function () {
    $business = Business::factory()->create(['slug' => 'old-name']);
    (new RenameBusinessSlug)->handle($business, 'new-name');
    expect(BusinessSlugRedirect::where('old_slug', 'old-name')->exists())->toBeTrue();

    $other = Business::factory()->create(['slug' => 'unrelated']);
    (new RenameBusinessSlug)->handle($other, 'old-name');

    expect(BusinessSlugRedirect::where('old_slug', 'old-name')->exists())->toBeFalse()
        ->and($other->fresh()->slug)->toBe('old-name');
});

it('chains redirects across two renames', function () {
    $business = Business::factory()->create(['slug' => 'first']);
    (new RenameBusinessSlug)->handle($business, 'second');
    (new RenameBusinessSlug)->handle($business->fresh(), 'third');

    expect(BusinessSlugRedirect::where('old_slug', 'first')->first()->business_id)->toBe($business->id)
        ->and(BusinessSlugRedirect::where('old_slug', 'second')->first()->business_id)->toBe($business->id);

    $this->get('/business/first')->assertRedirect(route('businesses.show', 'third'));
    $this->get('/business/second')->assertRedirect(route('businesses.show', 'third'));
});
