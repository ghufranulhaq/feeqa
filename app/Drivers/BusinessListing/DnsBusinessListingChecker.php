<?php

namespace App\Drivers\BusinessListing;

use App\Drivers\BusinessListing\Contracts\BusinessListingChecker;
use App\Models\Business;

/**
 * BUSINESS_LISTING_CHECKER=dns (production): FR-002-10's three checks.
 * "Not adult/illegal" is a keyword blocklist, not content classification —
 * a human moderator still reviews anything that slips through (spec 006).
 */
class DnsBusinessListingChecker implements BusinessListingChecker
{
    public function check(Business $business): ListingCheckResult
    {
        $domain = $business->primary_domain;

        if ($domain !== null && ! checkdnsrr($domain, 'A') && ! checkdnsrr($domain, 'AAAA')) {
            return new ListingCheckResult(approved: false, reason: 'domain_does_not_resolve');
        }

        $haystack = strtolower($business->name.' '.($domain ?? ''));

        foreach (config('platform.business_listing.blocklist_domains', []) as $blocked) {
            if ($domain !== null && $blocked !== '' && str_contains($domain, strtolower($blocked))) {
                return new ListingCheckResult(approved: false, reason: 'blocklisted_domain');
            }
        }

        foreach (config('platform.business_listing.blocklist_keywords', []) as $keyword) {
            if ($keyword !== '' && str_contains($haystack, strtolower($keyword))) {
                return new ListingCheckResult(approved: false, reason: 'blocklisted_keyword');
            }
        }

        return new ListingCheckResult(approved: true);
    }
}
