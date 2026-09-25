<?php

namespace App\Domain\Businesses;

/**
 * FR-002-30: the industry lifecycle. Only meaningful for a top-level
 * category (parent_id null) — a plain sub-category just has the FR-002-18
 * `launched` boolean and no state machine of its own.
 */
enum CategoryState: string
{
    case Draft = 'draft';
    case Launched = 'launched';
    case Paused = 'paused';
}
