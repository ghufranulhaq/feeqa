<?php

namespace App\Actions\Businesses;

use App\Models\Business;

/**
 * FR-003-30, FR-008-07: every event that should eventually change a
 * Business's Review Score or Trust Index calls this. Deliberately a
 * no-op today — spec 008 owns the real formula and storage, and neither
 * exists yet — same "built now, filled later" pattern as spec 002 T13's
 * `Business::mentions()` hook. Resolved through the container (rather
 * than constructor-injected into every caller) so a test can swap it out
 * to observe which Business each trigger point actually recalculates
 * for, without changing any caller's constructor.
 */
class RecalculateBusinessScore
{
    public function handle(Business $business): void
    {
        // Intentionally empty until spec 008 exists.
    }
}
