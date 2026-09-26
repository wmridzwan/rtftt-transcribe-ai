<?php

return [

    /*
     * Backup/restore foundation (P7-007, D7-07 daily posture, D7-03
     * node-local storage). SQLite-era mechanism executes now; the
     * PostgreSQL-native path stays dormant until P7-002 (activation
     * condition enforced in code, not just docs).
     */
    'target' => env('RTFTT_BACKUP_TARGET', storage_path('backups')),

    // Retained backup generations (daily sets). Pruning never deletes the
    // last good set and never orphans the manifest chain.
    'generations' => (int) env('RTFTT_BACKUP_GENERATIONS', 7),

    // A backup set older than this (hours) counts as stale for the
    // missed-run signal in `deployment:verify` (daily cadence + margin).
    'stale_after_hours' => 26,

];
