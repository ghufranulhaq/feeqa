<?php

namespace App\Drivers\BusinessListing\Contracts;

use App\Drivers\BusinessListing\ListingCheckResult;
use App\Models\Business;

/**
 * FR-002-10: "domain resolves, not on the blocklist, not adult/illegal",
 * run once against a newly-created unclaimed Business before it becomes
 * searchable.
 */
interface BusinessListingChecker
{
    public function check(Business $business): ListingCheckResult;
}
