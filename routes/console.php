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
