<?php

namespace App\Domain\Reviews;

use Illuminate\Support\Carbon;

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

    /**
     * FR-003-18: "opens on the milestone day."
     */
    public function windowOpensAt(Carbon $publishedAt): Carbon
    {
        return $publishedAt->copy()->startOfDay()->addDays($this->daysAfterPublication());
    }

    /**
     * FR-003-18: "closes when the next window opens. The 1-year window
     * stays open for 90 days" (there is no window after it to close it).
     */
    public function windowClosesAt(Carbon $publishedAt): Carbon
    {
        $next = $this->next();

        return $next !== null
            ? $next->windowOpensAt($publishedAt)
            : $this->windowOpensAt($publishedAt)->addDays(90);
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Day30 => self::Month6,
            self::Month6 => self::Year1,
            self::Year1 => null,
        };
    }
}
