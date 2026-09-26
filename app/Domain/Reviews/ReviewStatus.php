<?php

namespace App\Domain\Reviews;

/**
 * FR-003-11: the outcome of automated screening (006). `Held` awaits
 * human moderation (006's console, not built yet); `Rejected` carries a
 * reason shown to the author.
 */
enum ReviewStatus: string
{
    case Published = 'published';
    case Held = 'held';
    case Rejected = 'rejected';
}
