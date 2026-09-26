<?php

namespace App\Domain\Verification;

use RuntimeException;

/**
 * FR-004-12: keyed HMAC hashes over a transaction reference and a customer
 * email, never reversible to the plaintext. Same key and normalise-then-key
 * shape as ProofFingerprint, so there is one HMAC secret to manage for the
 * whole verification domain rather than a separate one per hash type.
 */
final class TransactionRecordHash
{
    public static function reference(string $reference): string
    {
        return hash_hmac('sha256', self::normalizeReference($reference), self::key());
    }

    public static function email(string $email): string
    {
        return hash_hmac('sha256', self::normalizeEmail($email), self::key());
    }

    /**
     * A hash produced by this class is always 64 lowercase hex characters
     * (sha256 HMAC digest). Anything else in an "email_hash" field can only
     * be a plaintext address someone forgot to hash (FR-004-12).
     */
    public static function looksHashed(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $value);
    }

    private static function normalizeReference(string $reference): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $reference) ?? '');
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
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
