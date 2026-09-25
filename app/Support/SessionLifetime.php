<?php

namespace App\Support;

use App\Models\User;

/**
 * FR-001-16: consumers get 30 days of inactivity before their session
 * expires; staff get 12 hours. Staff accounts don't exist yet (spec 001
 * task T17 adds them) — until then every user is a consumer, so this
 * always returns the consumer value. T17 adds the staff-role branch here.
 */
class SessionLifetime
{
    public const CONSUMER_MINUTES = 60 * 24 * 30;

    public const STAFF_MINUTES = 60 * 12;

    public static function minutesFor(User $user): int
    {
        return self::CONSUMER_MINUTES;
    }
}
