<?php

namespace App\Domain\Verification;

/**
 * FR-004-19: the reviewer's three choices when a business requests
 * verification of their review.
 */
enum ConsumerVerificationResponse: string
{
    case VerifyPrivately = 'verify_privately';
    case VerifyAndShare = 'verify_and_share';
    case Ignore = 'ignore';
}
