<?php

namespace App\Domain\Verification;

/**
 * FR-004-18 through FR-004-20.
 */
enum VerificationRequestStatus: string
{
    case Pending = 'pending';
    case Responded = 'responded';
    case Ignored = 'ignored';
}
