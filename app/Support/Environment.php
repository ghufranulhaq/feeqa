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
     * Whether registration/password-reset must reject known-breached
     * passwords (FR-001-04). Always true in production, whatever `.env`
     * says — elsewhere it follows `platform.security.check_breached_passwords`
     * so offline development and automated tests can turn off the network
     * call to the public "Have I Been Pwned" API (plan D6).
     */
    public static function checkBreachedPasswords(): bool
    {
        if (self::isProduction()) {
            return true;
        }

        return (bool) config('platform.security.check_breached_passwords', true);
    }

    /**
     * Whether a lifecycle update (003) can be added to a review the
     * moment its next milestone is unused, skipping the real 30-day/
     * 6-month/1-year window math (constitution §5.6). Always false in
     * production, whatever `.env` says — elsewhere it follows
     * `platform.reviews.lifecycle_updates.always_open_windows`, off by
     * default so every date-math test keeps testing real windows.
     */
    public static function lifecycleUpdateWindowsAlwaysOpen(): bool
    {
        if (self::isProduction()) {
            return false;
        }

        return (bool) config('platform.reviews.lifecycle_updates.always_open_windows', false);
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
