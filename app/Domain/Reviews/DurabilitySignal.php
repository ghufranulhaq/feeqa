<?php

namespace App\Domain\Reviews;

/**
 * FR-003-22: derived by comparing the current rating (latest published
 * lifecycle update, or the original rating) with the review's original
 * rating. Feeds analytics (015) and the Trust Index "repeat satisfaction"
 * input (008).
 */
enum DurabilitySignal: string
{
    case Improved = 'improved';
    case Unchanged = 'unchanged';
    case Declined = 'declined';

    public static function fromRatings(int $original, int $current): self
    {
        return match (true) {
            $current > $original => self::Improved,
            $current < $original => self::Declined,
            default => self::Unchanged,
        };
    }
}
