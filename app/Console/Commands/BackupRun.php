<?php

namespace App\Console\Commands;

use App\Backup\BackupManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily backup run (P7-007 foundation, D7-07).
 *
 * `--driver` is required and explicit (no silent inference in
 * production): `sqlite` snapshots the file store; `pgsql` runs
 * `pg_dump -Fc` with role credentials via PGPASSWORD env (fails
 * loudly when tooling/connection is missing). Exit 0 on a
 * manifest-valid set with passing integrity; exit 1 otherwise. Failed
 * runs quarantine the partial set and never present it as valid.
 */
#[Signature('backup:run {--driver= : Backup driver: sqlite or pgsql (pg_dump custom format)}')]
#[Description('Run the daily backup set with integrity verification (P7-007).')]
class BackupRun extends Command
{
    public function handle(BackupManager $manager): int
    {
        $driver = (string) $this->option('driver');

        if ($driver === '') {
            $this->error('Backup driver is required: --driver=sqlite or --driver=pgsql.');

            return self::FAILURE;
        }

        $result = $manager->run($driver);

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        if (! $result['ok']) {
            $this->warn('Backup FAILED'.($result['set'] !== null ? ' (set '.$result['set'].' quarantined).' : '.'));

            return self::FAILURE;
        }

        $manifest = $result['manifest'] ?? [];

        $this->info('Backup complete: '.$result['set']);
        $this->line('Manifest root: '.($manifest['root'] ?? 'n/a'));
        $this->line('Media files: '.count($manifest['media']['files'] ?? []));

        return self::SUCCESS;
    }
}
