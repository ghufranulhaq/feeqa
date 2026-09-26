<?php

namespace App\Rules;

use App\Domain\Businesses\DomainNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * FR-002-03 (≤ 1,500 characters) and the edge cases table ("Description
 * with a URL to a different domain" is rejected). Empty is allowed —
 * that's just an absent/blank value, which this never even sees (see
 * App\Rules\BusinessName's docblock for why a non-implicit rule is
 * skipped for a blank value).
 */
class BusinessDescription implements ValidationRule
{
    public function __construct(private readonly ?string $ownDomain) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (mb_strlen($value) > 1500) {
            $fail('The :attribute may not be longer than 1500 characters.');

            return;
        }

        foreach ($this->urlsIn($value) as $host) {
            if ($this->ownDomain === null || $host !== $this->ownDomain) {
                $fail('The :attribute cannot link to a different website.');

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function urlsIn(string $text): array
    {
        $hosts = [];

        if (preg_match_all('/(https?:\/\/\S+|www\.\S+)/i', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $host = DomainNormalizer::normalize($match);

                if ($host !== null) {
                    $hosts[] = $host;
                }
            }
        }

        if (preg_match_all('/\b([a-z0-9-]+\.(?:com|net|org|io|co\.uk|de|fr|es|app|dev|travel))\b/i', $text, $matches)) {
            foreach ($matches[1] as $match) {
                $host = DomainNormalizer::normalize($match);

                if ($host !== null) {
                    $hosts[] = $host;
                }
            }
        }

        return array_unique($hosts);
    }
}
