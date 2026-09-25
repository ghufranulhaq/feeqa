<?php

namespace App\Support;

use App\Models\User;

/**
 * FR-001-16: consumers get 30 days of inactivity before their session
 * expires; staff get 12 hours.
 */
class SessionLifetime
{
    public const CONSUMER_MINUTES = 60 * 24 * 30;

    public const STAFF_MINUTES = 60 * 12;

    public static function minutesFor(User $user): int
    {
        return $user->isStaff() ? self::STAFF_MINUTES : self::CONSUMER_MINUTES;
    }
}
