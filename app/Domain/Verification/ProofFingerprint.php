<?php

namespace App\Domain\Verification;

use RuntimeException;

/**
 * FR-004-10: a keyed hash (HMAC) of normalised `business_id + reference`,
 * folding in a document's perceptual hash for `document_proof`. Never
 * reversible to the reference itself, which is the point — the stored
 * fingerprint proves reuse without exposing what was proved.
 */
final class ProofFingerprint
{
    public static function forReference(int $businessId, string $reference): string
    {
        return hash_hmac('sha256', self::normalize($businessId, $reference), self::key());
    }

    public static function forDocument(int $businessId, string $reference, string $perceptualHash): string
    {
        return hash_hmac('sha256', self::normalize($businessId, $reference).':'.$perceptualHash, self::key());
    }

    private static function normalize(int $businessId, string $reference): string
    {
        $cleaned = preg_replace('/[^A-Za-z0-9]/', '', $reference) ?? '';

        return $businessId.':'.mb_strtoupper($cleaned);
    }

    private static function key(): string
    {
        $key = config('platform.verification.fingerprint_key');

        if (! $key) {
            throw new RuntimeException('VERIFICATION_FINGERPRINT_KEY is not configured.');
        }

        return $key;
    }
}
