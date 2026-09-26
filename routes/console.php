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
