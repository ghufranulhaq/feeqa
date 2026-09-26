<?php

namespace App\Domain\Reviews;

/**
 * FR-003-17: the three follow-up points a published review can receive,
 * counted from its publication date.
 */
enum LifecycleMilestone: string
{
    case Day30 = 'day_30';
    case Month6 = 'month_6';
    case Year1 = 'year_1';

    public function daysAfterPublication(): int
    {
        return match ($this) {
            self::Day30 => 30,
            self::Month6 => 180,
            self::Year1 => 365,
        };
    }
}
