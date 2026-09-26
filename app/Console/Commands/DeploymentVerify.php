<?php

namespace App\Console\Commands;

use App\Backup\BackupManager;
use App\Deployment\DatastorePosture;
use App\Deployment\ProductionConfigGuard;
use App\Deployment\ProductionPostureChecks;
use App\Deployment\TargetHostEvidence;
use App\Storage\StorageTopology;
use App\Transcription\TranscriptionQueueConfig;
use App\Translation\TranslationQueueConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Deployment verification (P7-008).
 *
 * Readiness checks for a deploy: migrations current, queue guards clean,
 * production env matrix safe (when in production), stale-recovery
 * schedules present, private storage writable. Informational in any
 * environment; `--strict` exits non-zero on any violation so deploy
 * pipelines and readiness gates can fail loudly.
 */
#[Signature('deployment:verify {--strict : Exit non-zero when any check fails}')]
#[Description('Verify deploy readiness: migrations, queue guards, env matrix, schedules, storage (P7-008).')]
class DeploymentVerify extends Command
{
    public function handle(): int
    {
        // Evaluate every check (no short-circuit) so the report is
        // complete even when an early check fails.
        $results = [
            $this->checkMigrations(),
            $this->checkQueueGuards(),
            $this->checkProductionMatrix(),
            $this->checkPosture(),
            $this->checkTargetHost(),
            $this->checkDatastore(),
            $this->checkBackups(),
            $this->checkSchedules(),
            $this->checkStorage(),
            $this->checkStorageTopology(),
            $this->checkRetention(),
        ];

        $failed = in_array(true, $results, true);

        if ($failed) {
            $this->error('Deployment verification found failures.');

            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Deployment verification passed.');

        return self::SUCCESS;
    }

    private function checkMigrations(): bool
    {
        try {
            $ran = Schema::hasTable('migrations')
                ? DB::table('migrations')->pluck('migration')->all()
                : [];

            $files = collect(scandir(database_path('migrations')) ?: [])
                ->filter(fn ($file): bool => str_ends_with((string) $file, '.php'))
                ->map(fn ($file): string => pathinfo((string) $file, PATHINFO_FILENAME))
                ->values()
                ->all();

            $pending = array_values(array_diff($files, $ran));
        } catch (Throwable $exception) {
            $this->warn('Migrations: could not inspect ('.$exception->getMessage().')');

            return true;
        }

        if ($pending !== []) {
            $this->warn('Migrations: '.count($pending).' pending ('.implode(', ', $pending).')');

            return true;
        }

        $this->line('Migrations: current ('.count($files).' applied).');

        return false;
    }

    private function checkQueueGuards(): bool
    {
        $failed = false;

        foreach ([
            'Transcription' => TranscriptionQueueConfig::consistencyViolation(),
            'Translation' => TranslationQueueConfig::consistencyViolation(),
        ] as $label => $violation) {
            if ($violation !== null) {
                $this->warn("{$label} queue: VIOLATION — {$violation}");
                $failed = true;
            } else {
                $this->line("{$label} queue: ok");
            }
        }

        return $failed;
    }

    private function checkProductionMatrix(): bool
    {
        // In non-production environments the matrix is advisory: report it
        // without failing, so dev/test runs stay green.
        $violations = ProductionConfigGuard::violations();

        if ($violations === []) {
            $this->line('Production matrix: ok');

            return false;
        }

        foreach ($violations as $violation) {
            $this->warn('Production matrix: '.$violation);
        }

        if (! app()->isProduction()) {
            $this->line('Production matrix: advisory only (not production).');

            return false;
        }

        return true;
    }

    /**
     * P7-001 posture sub-check: Redis auth posture, upload-limit adequacy,
     * storage capacity. Advisory outside production (mirrors
     * checkProductionMatrix); enforcing under production + --strict.
     */
    private function checkPosture(): bool
    {
        $violations = ProductionPostureChecks::violations();

        if ($violations === []) {
            $this->line('Posture: ok (redis auth, upload limits, capacity)');

            return false;
        }

        foreach ($violations as $violation) {
            $this->warn('Posture: '.$violation);
        }

        if (! app()->isProduction()) {
            $this->line('Posture: advisory only (not production).');

            return false;
        }

        return true;
    }

    /**
     * P7-001 AC8 carry-forward sub-check: real-host AC2/AC8 evidence status.
     * PENDING is advisory outside production; production + --strict fails
     * until recorded PASS evidence exists (the disposition requirement).
     */
    private function checkTargetHost(): bool
    {
        $failed = false;
        $pending = false;

        foreach (TargetHostEvidence::EXPECTED_ITEMS as $item) {
            $status = TargetHostEvidence::itemStatus($item);

            if ($status === 'pass') {
                $this->line("Target evidence [{$item}]: recorded PASS");
            } else {
                $pending = true;
                $this->warn("Target evidence [{$item}]: ".strtoupper($status).' — real-host verification required before P7-012');

                if (app()->isProduction()) {
                    $failed = true;
                }
            }
        }

        if ($pending && ! $failed) {
            $this->line('Target evidence: advisory only (not production).');
        }

        return $failed;
    }

    /**
     * P7-002 datastore sub-check: sqlite is legal pre-cutover; pgsql
     * requires provisioned credentials (D7-01 flip). Advisory outside
     * production; enforcing under production + --strict, mirroring
     * checkPosture. Never claims migration readiness (P7-012 owns G-02).
     */
    private function checkDatastore(): bool
    {
        $connection = (string) config('database.default', 'sqlite');
        $violations = DatastorePosture::evaluate($connection);

        if ($violations === []) {
            $this->line("Datastore: ok (driver {$connection})");

            return false;
        }

        foreach ($violations as $violation) {
            $this->warn('Datastore: '.$violation);
        }

        if (! app()->isProduction()) {
            $this->line('Datastore: advisory only (not production).');

            return false;
        }

        return true;
    }

    /**
     * P7-007 stale-manifest signal: a missed daily run fails loudly in
     * production + --strict; advisory elsewhere. Never claims drill
     * readiness — foundation presence only.
     */
    private function checkBackups(): bool
    {
        $manager = app(BackupManager::class);
        $target = $manager->targetRoot();
        $manifest = $manager->latestManifest($target);

        if ($manifest === null) {
            $this->warn('Backups: no backup sets present');

            return $this->productionOnlyFail(sprintf('Backups: schedule `backup:run --driver=%s` daily.', $this->backupDriver()));
        }

        $status = $manifest['status'] ?? BackupManager::STATUS_FAILED;
        $createdAt = isset($manifest['created_at']) && is_string($manifest['created_at'])
            ? strtotime($manifest['created_at'])
            : false;
        $ageHours = $createdAt === false ? null : (time() - $createdAt) / 3600;
        $staleAfter = max(1, (int) config('backup.stale_after_hours', 26));

        $this->line(sprintf(
            'Backups: latest set %s (%s%s)',
            $manifest['name'] ?? 'unknown',
            $status,
            $ageHours === null ? '' : sprintf(', %.1fh old', $ageHours)
        ));

        if ($status !== BackupManager::STATUS_OK) {
            $this->warn('Backups: latest set is not manifest-valid');

            return $this->productionOnlyFail('Backups: investigate the failed set; pruning is blocked by last-good protection.');
        }

        if ($ageHours !== null && $ageHours > $staleAfter) {
            $this->warn(sprintf('Backups: latest set is stale (>%dh); the daily run may have missed.', $staleAfter));

            return $this->productionOnlyFail(sprintf('Backups: re-run `backup:run --driver=%s`.', $this->backupDriver()));
        }

        return false;
    }

    private function backupDriver(): string
    {
        return (string) config('database.default', 'sqlite') === 'pgsql' ? 'pgsql' : 'sqlite';
    }

    private function productionOnlyFail(string $advice): bool
    {
        if (! app()->isProduction()) {
            $this->line($advice.' Advisory only (not production).');

            return false;
        }

        $this->warn($advice);

        return true;
    }

    private function checkSchedules(): bool
    {
        $failed = false;

        foreach (['translation:recover-stale-attempts', 'transcription:recover-stale-attempts'] as $command) {
            $events = app(Schedule::class)->events();
            $scheduled = false;

            foreach ($events as $event) {
                if (is_string($event->command) && str_contains($event->command, $command)) {
                    $scheduled = true;

                    break;
                }
            }

            if ($scheduled) {
                $this->line("Schedule: {$command} present");
            } else {
                $this->warn("Schedule: {$command} MISSING");
                $failed = true;
            }
        }

        return $failed;
    }

    private function checkStorage(): bool
    {
        $roots = [
            'app' => storage_path('app'),
            'logs' => storage_path('logs'),
            'framework' => storage_path('framework'),
        ];

        $failed = false;

        foreach ($roots as $label => $path) {
            if (is_dir($path) && is_writable($path)) {
                $this->line("Storage [{$label}]: writable");
            } else {
                $this->warn("Storage [{$label}]: missing or not writable ({$path})");
                $failed = true;
            }
        }

        return $failed;
    }

    /**
     * P7-004 storage-topology sub-check: local driver, root
     * existence/writability, free-space floor. Advisory outside
     * production; enforcing under production + --strict, mirroring
     * checkStorage. Never claims capacity proof (P7-009 owns G-01).
     */
    private function checkStorageTopology(): bool
    {
        $result = StorageTopology::evaluate();

        foreach ($result['detail'] as $key => $value) {
            $this->line("Topology [{$key}]: {$value}");
        }

        if ($result['violations'] === []) {
            $this->line('Topology: ok (local private disk)');

            return false;
        }

        foreach ($result['violations'] as $violation) {
            $this->warn('Topology: '.$violation);
        }

        if (! app()->isProduction()) {
            $this->line('Topology: advisory only (not production).');

            return false;
        }

        return true;
    }

    /**
     * P7-011 retention signal: the daily purge schedule is present and
     * the latest audit ledger has no unacknowledged failure. Advisory
     * outside production; enforcing under production + --strict. Never
     * claims retention completeness (G-09 belongs to P7-012).
     */
    private function checkRetention(): bool
    {
        $scheduled = false;

        foreach (app(Schedule::class)->events() as $event) {
            if (is_string($event->command) && str_contains($event->command, 'retention:purge')) {
                $scheduled = true;

                break;
            }
        }

        if (! $scheduled) {
            $this->warn('Retention: retention:purge is not scheduled');

            return $this->productionOnlyFail('Retention: schedule retention:purge daily.');
        }

        $this->line('Retention: retention:purge scheduled');

        try {
            $failed = DB::table('retention_purge_audits')->where('outcome', 'failed')->count();
        } catch (Throwable $exception) {
            $this->warn('Retention: could not inspect audit ledger ('.$exception->getMessage().')');

            return true;
        }

        if ($failed > 0) {
            $this->warn("Retention: {$failed} failed purge outcome(s) recorded in the audit ledger");

            return $this->productionOnlyFail('Retention: investigate failed purge outcomes; resume with retention:purge.');
        }

        $this->line('Retention: audit ledger has no failures');

        return false;
    }
}
