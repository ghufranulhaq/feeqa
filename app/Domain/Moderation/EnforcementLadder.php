<?php

namespace App\Domain\Moderation;

/**
 * FR-006-14, FR-006-15: which of the two ladders an `EnforcementAction`
 * belongs to — determined by its subject type (Business or User) and
 * checked against `EnforcementStep::sequenceFor()`.
 */
enum EnforcementLadder: string
{
    case Business = 'business';
    case Reviewer = 'reviewer';
}
