<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * P5-004C: recover demonstrably stale `translating` translation attempts.
 * Token-fenced, so it can never fail a newer attempt. Runs above the job
 * timeout (see worker/TRANSLATION-OPERATIONS.md for the timeout reconciliation).
 */
Schedule::command('translation:recover-stale-attempts')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * P7-003: recover demonstrably stale `running` transcription attempts.
 * Claim-fenced, so it can never fail a newer attempt. Mirrors the
 * translation schedule: every minute (far above the job timeout plus the
 * 60-second stale margin, so a legitimately long inference is never
 * mistaken for stale) with overlap protection.
 */
Schedule::command('transcription:recover-stale-attempts')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * P7-006: daily ClamAV health (reachability + signature freshness) so
 * missed signature updates alert through the runbook path. Informational
 * when the scanner is disabled; never blocks unrelated schedules.
 */
Schedule::command('clamav:health')->daily()->withoutOverlapping();

/*
 * P7-007: daily backup set with per-run integrity verification, backing
 * up the configured default datastore (sqlite pre-cutover, pgsql
 * post-cutover via pg_dump). The driver resolves from database.default
 * at schedule registration so cutover needs no schedule edit; explicit
 * --driver stays required at the command layer. Missed runs surface
 * via the deployment:verify stale check.
 */
Schedule::command('backup:run', ['--driver' => config('database.default') === 'pgsql' ? 'pgsql' : 'sqlite'])->daily()->withoutOverlapping();

/*
 * P7-011: daily retention purge (D7-06, 30-day clock + 24h staging rule).
 * Idempotent and re-entry safe; withoutOverlapping fences scheduler
 * re-entry while per-object claims keep the operation itself safe.
 */
Schedule::command('retention:purge')->daily()->withoutOverlapping();
