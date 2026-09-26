<?php

namespace App\Domain\Businesses;

/**
 * FR-002-09: "normalised domain (lowercase, `www.` removed, IDN converted
 * to punycode)". Deliberately strips only a leading "www." and nothing
 * else — a different subdomain (`shop.brand.com`) is left alone, because
 * the edge cases table treats it as a different business by default.
 */
class DomainNormalizer
{
    public static function normalize(string $input): ?string
    {
        $trimmed = trim($input);

        if ($trimmed === '') {
            return null;
        }

        $host = str_contains($trimmed, '://')
            ? parse_url($trimmed, PHP_URL_HOST)
            : parse_url('https://'.$trimmed, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower($host);

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (is_string($ascii) && $ascii !== '') {
                $host = $ascii;
            }
        }

        return $host === '' ? null : $host;
    }
}
