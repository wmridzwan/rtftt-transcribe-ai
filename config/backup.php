<?php

return [

    /*
     * Backup/restore foundation (P7-007, D7-07 daily posture, D7-03
     * node-local storage). SQLite-era mechanism executes now; the
     * PostgreSQL-native path (pg_dump custom format) executes when the
     * pgsql driver is requested and client tooling is present, failing
     * loudly otherwise (pre-Linux remediation, BLOCKER-B).
     */
    'target' => env('RTFTT_BACKUP_TARGET', storage_path('backups')),

    // pg_dump binary for the pgsql driver. PATH resolution by default;
    // absolute path recommended on production hosts.
    'pg_dump' => env('RTFTT_PG_DUMP_PATH', 'pg_dump'),

    // Retained backup generations (daily sets). Pruning never deletes the
    // last good set and never orphans the manifest chain.
    'generations' => (int) env('RTFTT_BACKUP_GENERATIONS', 7),

    // A backup set older than this (hours) counts as stale for the
    // missed-run signal in `deployment:verify` (daily cadence + margin).
    'stale_after_hours' => 26,

];
