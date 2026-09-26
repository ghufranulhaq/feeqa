<?php

namespace App\Domain\Verification;

/**
 * FR-004-06: "the merchant matches the Business (name, domain, or a
 * registered alias) with high confidence." `additional_domains` is this
 * Business's registered-alias list (FR-002-09).
 */
final class MerchantMatch
{
    private const SIMILARITY_THRESHOLD = 85;

    /**
     * @param  list<string>  $candidates  business name, primary domain, and additional domains
     */
    public static function matches(string $extractedMerchant, array $candidates): bool
    {
        $normalized = self::normalize($extractedMerchant);

        if ($normalized === '') {
            return false;
        }

        foreach ($candidates as $candidate) {
            $candidateNormalized = self::normalize($candidate);

            if ($candidateNormalized === '') {
                continue;
            }

            if (str_contains($normalized, $candidateNormalized) || str_contains($candidateNormalized, $normalized)) {
                return true;
            }

            similar_text($normalized, $candidateNormalized, $percent);

            if ($percent >= self::SIMILARITY_THRESHOLD) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = mb_strtolower(trim($value));
        $value = preg_replace('/^www\./', '', $value) ?? $value;

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }
}
