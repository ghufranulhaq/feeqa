<?php

namespace App\Domain\Businesses;

/**
 * FR-002-07.
 */
enum ProfileChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
