<?php

namespace App\Drivers\BusinessClaiming;

use App\Drivers\BusinessClaiming\Contracts\DomainClaimChecker;
use App\Models\BusinessClaim;

/**
 * BUSINESS_CLAIM_DOMAIN_CHECKER=fake (default locally and in the demo):
 * approves every DNS TXT/HTML claim without a real network lookup.
 */
class FakeDomainClaimChecker implements DomainClaimChecker
{
    public function check(BusinessClaim $claim): bool
    {
        return true;
    }
}
