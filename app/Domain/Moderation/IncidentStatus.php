<?php

namespace App\Domain\Moderation;

/**
 * FR-006-06, FR-006-11: an incident's own lifecycle in the moderation
 * console's incidents queue.
 */
enum IncidentStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
