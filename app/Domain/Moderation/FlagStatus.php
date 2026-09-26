<?php

namespace App\Domain\Moderation;

/**
 * FR-006-07 through FR-006-10: a flag's own lifecycle, independent of the
 * flagged content's own status. `Blurred` is a flag-level fact — a piece
 * of content is treated as blurred wherever any of its flags carries this
 * status (see `Review::isBlurred()`), so a second flag never needs to
 * "un-blur" the first one.
 */
enum FlagStatus: string
{
    case Open = 'open';
    case Blurred = 'blurred';
    case Upheld = 'upheld';
    case Rejected = 'rejected';
}
