<?php

// Scheduled tasks, run by crunz. The only crontab entry needed is:
//   * * * * * /path/to/smart-connect/cron.sh
// crunz evaluates the schedule below every minute and runs whatever is due.

use Crunz\Schedule;

$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
$timezone = date_default_timezone_get();

$schedule = new Schedule();

// Housekeeping — prune API payload dirs (vlsm-vl / vlsm-eid / vlsm-covid19 /
// vlsm-reference), track-api JSON, temp files, cache and stale DB rows
$schedule->run("{$php} {$root}/bin/console housekeeping")
    ->dailyAt('02:00')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Housekeeping — remove old temp files, API payloads and stale DB rows');

// Send queued mails from the temp_mail table
$schedule->run("{$php} {$root}/bin/console send-mail")
    ->everyFiveMinutes()
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Send queued mails');

// Rebuild the dash_form_*_current snapshot tables (last 12 months). Gated by
// dash_global_config.daily_snapshot_rebuild (OFF by default) — the command
// exits quietly unless that flag is set to "yes". All three test types are
// rebuilt because the app's "current tables" toggle switches vl, eid and
// covid19 together; restrict with e.g. --tests=vl if that ever changes.
$schedule->run("{$php} {$root}/bin/console rebuild-snapshots")
    ->dailyAt('01:00')
    ->timezone($timezone)
    ->preventOverlapping()
    ->description('Rebuild dashboard *_current snapshot tables');

return $schedule;
