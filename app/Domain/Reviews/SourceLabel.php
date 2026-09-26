<?php

namespace App\Domain\Reviews;

/**
 * FR-003-14, FR-003-15: set by the system from how the review arrived,
 * never editable. `Invited` and `Redirected` both need the invitation/
 * generic-link system (005) to actually be reachable — every review is
 * `Organic` until then.
 */
enum SourceLabel: string
{
    case Invited = 'invited';
    case Redirected = 'redirected';
    case Organic = 'organic';
}
