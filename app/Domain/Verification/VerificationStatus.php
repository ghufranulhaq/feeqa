<?php

namespace App\Domain\Verification;

/**
 * FR-004-06, FR-004-08: `Pending` covers both "not decided yet" and "in
 * the staff queue" — a row is in the queue exactly when it is `Pending`
 * and has no `decided_at`.
 */
enum VerificationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
