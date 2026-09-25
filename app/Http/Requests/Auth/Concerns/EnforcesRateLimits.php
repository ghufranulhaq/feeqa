<?php

namespace App\Http\Requests\Auth\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * FR-001-17: sign-in, code verification, and password reset are all
 * rate-limited to 5 failed attempts / 15 minutes **per account and per
 * IP** — two independent counters, either one tripping blocks the request.
 * Only failed attempts count: call hit() on failure, clear() on success.
 * The cool-down (rather than a CAPTCHA) is the chosen mitigation past the
 * limit — see plan D6.
 */
trait EnforcesRateLimits
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900; // 15 minutes

    /**
     * @throws ValidationException
     */
    protected function ensureNotRateLimited(string $scope, string $identifier, string $field = 'email'): void
    {
        foreach ($this->rateLimitKeys($scope, $identifier) as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $seconds = RateLimiter::availableIn($key);

                throw ValidationException::withMessages([
                    $field => "Too many attempts. Please try again in {$seconds} seconds.",
                ]);
            }
        }
    }

    protected function hitRateLimit(string $scope, string $identifier): void
    {
        foreach ($this->rateLimitKeys($scope, $identifier) as $key) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
        }
    }

    protected function clearRateLimit(string $scope, string $identifier): void
    {
        foreach ($this->rateLimitKeys($scope, $identifier) as $key) {
            RateLimiter::clear($key);
        }
    }

    /**
     * @return list<string>
     */
    private function rateLimitKeys(string $scope, string $identifier): array
    {
        return [
            $scope.'|account|'.Str::transliterate(Str::lower($identifier)),
            $scope.'|ip|'.RequestFacade::ip(),
        ];
    }
}
