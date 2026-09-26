<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR-001-20.
Schedule::command('accounts:erase-pending-deletions')->daily();

// FR-002-14.
Schedule::command('business-claims:escalate-stale-reclaims')->daily();

// FR-003-19.
Schedule::command('reviews:send-lifecycle-reminders')->daily();

// FR-004-23, constitution §5.6.
Schedule::command('verification:delete-expired-proofs')->daily();

// FR-005-08, FR-005-09.
Schedule::command('review-invitations:send-due')->hourly();

// FR-005-08.
Schedule::command('review-invitations:send-reminders')->daily();

// FR-005-10, FR-005-11.
Schedule::command('review-invitations:expire')->daily();

// FR-005-20.
Schedule::command('review-invitations:release-plan-limited')->daily();

// FR-005-18.
Schedule::command('review-invitations:neutrality-report')->daily();
