<?php

namespace App\Domain\Moderation;

/**
 * FR-006-01: the Platform publishes separate, versioned guidelines for
 * each audience. `EnforcementPolicy` (FR-006-21(b)) reuses the same
 * `GuidelineVersion` table/model rather than a parallel one — the ladder
 * text is "versioned like guidelines" (tasks.md T9), not a different
 * kind of document.
 */
enum GuidelineAudience: string
{
    case Reviewer = 'reviewer';
    case Business = 'business';
    case EnforcementPolicy = 'enforcement_policy';
}
