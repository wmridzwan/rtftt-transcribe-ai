<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * "Backup before migrate" hook (P7-007 §6.6/§9.4 — the exact command the
 * P7-008 runbook hook invokes). Backs up the configured default
 * datastore (sqlite pre-cutover, pgsql post-cutover) and reports with
 * pinned exit codes: 0 = fresh manifest-valid set; 1 = anything else
 * (migration must not proceed). Golden output lines are asserted in
 * tests; the wording is part of the P7-008 interface.
 */
#[Signature('backup:pre-migrate')]
#[Description('Pre-migration backup hook: fresh verified backup or refuse (P7-007).')]
class BackupPreMigrate extends Command
{
    public function handle(): int
    {
        $driver = (string) config('database.default', 'sqlite') === 'pgsql' ? 'pgsql' : 'sqlite';
        $exit = $this->call('backup:run', ['--driver' => $driver]);

        if ($exit !== 0) {
            $this->error('PRE-MIGRATE BACKUP REFUSED: no fresh verified backup set; migration must not proceed.');

            return self::FAILURE;
        }

        $this->info('PRE-MIGRATE BACKUP OK: fresh verified backup set present; migration may proceed.');

        return self::SUCCESS;
    }
}
