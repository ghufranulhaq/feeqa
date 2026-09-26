<?php

namespace App\Support\Businesses;

use App\Models\Business;
use RuntimeException;

/**
 * FR-002-09: "If a match is found, it must suggest the existing profile."
 * Carries that profile so the controller can point the consumer at it
 * instead of creating a second listing for the same business.
 */
class DuplicateBusinessException extends RuntimeException
{
    public function __construct(public readonly Business $existing)
    {
        parent::__construct("A business matching this one already exists: {$existing->slug}.");
    }
}
