<?php

namespace App\Domain\Invitations;

/**
 * FR-005-08: the category default send delay
 * (`specs/002-business-profiles/travel-content.md` §4), used unless the
 * Business has its own configured delay. Airlines and Travel Agencies/OTAs
 * both send 1 day after the travel date when one is known; without one,
 * an Airline still waits only 1 day (from the trigger), while an Agency
 * waits 3 days (from the booking) — the difference is in what the
 * fallback anchor represents, not in this method's return value alone,
 * so the caller adds the returned number of days to the travel date if
 * known, or to "now" (the trigger/booking moment) if not. Airports have
 * no transaction-invitation default (spec.md §4: link/QR only) and, like
 * every other category, fall back to the plain FR-005-08 default.
 */
final class DefaultSendDelay
{
    public const PLAIN_DEFAULT_DAYS = 7;

    private const AIRLINE_SLUGS = ['airlines'];

    private const AGENCY_SLUGS = ['travel-agencies-otas', 'online-travel-agencies', 'high-street-tour-agencies'];

    /**
     * @param  list<string>  $categorySlugs  the Business's primary category and its ancestors, leaf to root
     */
    public static function days(array $categorySlugs, bool $hasTravelDate): int
    {
        if (array_intersect($categorySlugs, self::AIRLINE_SLUGS) !== []) {
            return 1;
        }

        if (array_intersect($categorySlugs, self::AGENCY_SLUGS) !== []) {
            return $hasTravelDate ? 1 : 3;
        }

        return self::PLAIN_DEFAULT_DAYS;
    }
}
