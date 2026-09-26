<?php

namespace App\Drivers\BusinessListing;

use App\Drivers\BusinessListing\Contracts\BusinessListingChecker;
use App\Models\Business;

/**
 * BUSINESS_LISTING_CHECKER=fake (default locally and in the demo): every
 * newly-created business passes immediately, with no real DNS lookup.
 */
class FakeBusinessListingChecker implements BusinessListingChecker
{
    public function check(Business $business): ListingCheckResult
    {
        return new ListingCheckResult(approved: true);
    }
}
