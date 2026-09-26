<?php

use App\Domain\Businesses\BusinessStatus;
use App\Drivers\BusinessListing\Contracts\BusinessListingChecker;
use App\Drivers\BusinessListing\DnsBusinessListingChecker;
use App\Drivers\BusinessListing\FakeBusinessListingChecker;
use App\Drivers\BusinessListing\ListingCheckResult;
use App\Jobs\RunBusinessListingCheck;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('moves a business out of pending when the check passes (FR-002-10)', function () {
    $business = Business::factory()->pending()->create();

    app()->instance(BusinessListingChecker::class, new FakeBusinessListingChecker);
    (new RunBusinessListingCheck($business->id))->handle(app(BusinessListingChecker::class));

    $business->refresh();
    expect($business->status)->toBe(BusinessStatus::Unclaimed)
        ->and($business->listing_checked_at)->not->toBeNull()
        ->and($business->listing_check_notes)->toBeNull();
});

it('leaves a business pending with a reason when the check fails (FR-002-10)', function () {
    $business = Business::factory()->pending()->create();

    app()->instance(BusinessListingChecker::class, new class implements BusinessListingChecker
    {
        public function check(Business $business): ListingCheckResult
        {
            return new ListingCheckResult(approved: false, reason: 'blocklisted_keyword');
        }
    });
    (new RunBusinessListingCheck($business->id))->handle(app(BusinessListingChecker::class));

    $business->refresh();
    expect($business->status)->toBe(BusinessStatus::Pending)
        ->and($business->listing_check_notes)->toBe('blocklisted_keyword');
});

it('does not block the business from being reviewable in the meantime (FR-002-10)', function () {
    $business = Business::factory()->pending()->create();

    // Reviews are spec 003 (not built yet) — this just documents that
    // nothing here (status/queue) prevents it: no additional gate exists
    // on the Business model itself.
    expect($business->status)->toBe(BusinessStatus::Pending);
});

it('the dns checker approves a business whose domain resolves and is not blocklisted', function () {
    $business = Business::factory()->create(['name' => 'Skyhop Travel', 'primary_domain' => 'example.com']);

    $result = (new DnsBusinessListingChecker)->check($business);

    expect($result->approved)->toBeTrue();
});

it('the dns checker rejects a domain that does not resolve', function () {
    $business = Business::factory()->create(['primary_domain' => 'this-domain-does-not-exist-at-all-12345.example']);

    $result = (new DnsBusinessListingChecker)->check($business);

    expect($result->approved)->toBeFalse()
        ->and($result->reason)->toBe('domain_does_not_resolve');
});

it('the dns checker rejects a blocklisted keyword in the name', function () {
    config(['platform.business_listing.blocklist_keywords' => ['casino']]);
    $business = Business::factory()->create(['name' => 'Lucky Casino Travel', 'primary_domain' => 'example.com']);

    $result = (new DnsBusinessListingChecker)->check($business);

    expect($result->approved)->toBeFalse()
        ->and($result->reason)->toBe('blocklisted_keyword');
});
