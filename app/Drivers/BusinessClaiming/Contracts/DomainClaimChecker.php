<?php

namespace App\Drivers\BusinessClaiming\Contracts;

use App\Models\BusinessClaim;

/**
 * FR-002-11 methods (b) DNS TXT and (c) HTML meta tag/file: proves the
 * claimant controls the business's domain by checking for
 * `verification_token` there.
 */
interface DomainClaimChecker
{
    public function check(BusinessClaim $claim): bool;
}
