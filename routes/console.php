<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ═══════════════════════════════════════════════════
// SCHEDULED TASKS
// ═══════════════════════════════════════════════════
//
// One job does everything: bill schedules, every notification the app sends,
// subscription expiry and clearing notifications older than 15 days.
//
// Server cron entry:
//   * * * * * cd /path/to/kennedy && php artisan schedule:run >> /dev/null 2>&1

Schedule::command('saverr:cron')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

// Subscriptions also run hourly so a lapsed trial / paid term (or a billing
// grace period that ran out) moves the user to Free promptly. RevenueCat's
// webhook normally gets there first; this is the safety net.
Schedule::command('saverr:cron --only=subscriptions')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
