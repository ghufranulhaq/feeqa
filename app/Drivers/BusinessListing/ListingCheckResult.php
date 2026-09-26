<?php

namespace App\Drivers\BusinessListing;

final class ListingCheckResult
{
    public function __construct(
        public readonly bool $approved,
        public readonly ?string $reason = null,
    ) {}
}
