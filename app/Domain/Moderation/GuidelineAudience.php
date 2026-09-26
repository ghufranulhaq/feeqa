<?php

namespace App\Domain\Moderation;

/**
 * FR-006-01: the Platform publishes separate, versioned guidelines for
 * each audience.
 */
enum GuidelineAudience: string
{
    case Reviewer = 'reviewer';
    case Business = 'business';
}
