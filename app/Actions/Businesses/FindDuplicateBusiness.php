<?php

namespace App\Actions\Businesses;

use App\Models\Business;

/**
 * FR-002-09: duplicate detection by normalised domain, or by fuzzy
 * name+city match when there's no domain.
 */
class FindDuplicateBusiness
{
    private const NAME_SIMILARITY_THRESHOLD = 0.4;

    public function byDomain(string $normalizedDomain): ?Business
    {
        return Business::where('primary_domain', $normalizedDomain)->first();
    }

    public function byNameAndCity(string $name, string $country, string $city): ?Business
    {
        return Business::query()
            ->where('country', $country)
            ->whereRaw('lower(city) = lower(?)', [$city])
            ->whereRaw('similarity(lower(name), lower(?)) > ?', [$name, self::NAME_SIMILARITY_THRESHOLD])
            ->orderByRaw('similarity(lower(name), lower(?)) DESC', [$name])
            ->first();
    }
}
