<?php

namespace App\Domain\Moderation;

/**
 * FR-006-18, FR-006-19: an appeal's own lifecycle, independent of the
 * decision it targets.
 */
enum AppealStatus: string
{
    case Pending = 'pending';
    case Upheld = 'upheld';
    case Overturned = 'overturned';
}
