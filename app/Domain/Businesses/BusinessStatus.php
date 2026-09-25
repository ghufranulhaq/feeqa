<?php

namespace App\Domain\Businesses;

/**
 * FR-002-01 (unclaimed/claimed/suspended/closed) plus the transient
 * FR-002-10 state a newly-created business sits in before its automated
 * check passes and it becomes searchable.
 */
enum BusinessStatus: string
{
    case Pending = 'pending';
    case Unclaimed = 'unclaimed';
    case Claimed = 'claimed';
    case Suspended = 'suspended';
    case Closed = 'closed';
}
