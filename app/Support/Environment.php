<?php

namespace App\Support;

use RuntimeException;

/**
 * The single place that answers "is this demo?" and "are demo relaxations
 * allowed?" (constitution §5.6, plan D26). Relaxations are allowed in
 * local, testing, and demo — never in production, whatever else is
 * configured. Nothing here reads a raw .env toggle for the things that
 * matter (password length, staff IP allow-listing): they are computed from
 * the environment so production can't be weakened by a stray .env value.
 */
class Environment
{
    public static function isDemo(): bool
    {
        return app()->environment('demo');
    }

    public static function isProduction(): bool
    {
        return app()->environment('production');
    }

    public static function demoRelaxationsAllowed(): bool
    {
        return ! self::isProduction();
    }

    /**
     * Minimum password length (plan D6): 12 in production, 6 elsewhere.
     */
    public static function passwordMinLength(): int
    {
        return self::isProduction() ? 12 : 6;
    }

    /**
     * Staff console IP allow-listing is enforced only in production (plan D7).
     */
    public static function staffIpAllowlistEnforced(): bool
    {
        return self::isProduction();
    }

    /**
     * Refuses to boot in production if a demo-only `fake` driver is
     * configured (constitution §5.6, plan D26). Call from a service
     * provider's boot() method.
     *
     * @throws RuntimeException
     */
    public static function assertProductionIsSafe(): void
    {
        if (! self::isProduction()) {
            return;
        }

        $fakeDrivers = array_keys(array_filter([
            'AI_DRIVER' => config('platform.ai.driver'),
            'VERIFICATION_EXTRACTOR' => config('platform.verification.extractor'),
            'TRANSCRIPTION_DRIVER' => config('platform.transcription.driver'),
            'MALWARE_SCANNER' => config('platform.malware.scanner'),
            'BILLING_DRIVER' => config('platform.billing.driver'),
        ], fn (?string $driver) => $driver === 'fake'));

        if ($fakeDrivers !== []) {
            throw new RuntimeException(
                'Refusing to boot in production with demo-only "fake" driver(s) configured: '
                .implode(', ', $fakeDrivers)
                .'. See constitution §5.6.'
            );
        }
    }
}
