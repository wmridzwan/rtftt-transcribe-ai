<?php

namespace App\Console\Commands;

use App\Backup\BackupManager;
use App\Deployment\DatastorePosture;
use App\Deployment\ProductionEnvRegistry;
use App\Deployment\RedisPosture;
use App\Deployment\TargetHostEvidence;
use App\Deployment\UploadLimitAudit;
use App\Security\ClamavScanner;
use App\Storage\StorageTopology;
use App\Transcription\TranscriptionQueueConfig;
use App\Translation\TranslationQueueConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * P7-005 observability diagnostics.
 *
 * Reports logging/correlation configuration, queue and timeout consistency, and
 * (optionally) worker health. Product-semantic-neutral: it observes existing
 * configuration and does not change any domain behavior.
 */
#[Signature('observability:diagnostics {--probe-worker : Probe the configured worker health endpoints} {--strict : Exit non-zero when a consistency check fails}')]
#[Description('Report logging, correlation, queue/runtime, and worker health diagnostics (P7-005).')]
class ObservabilityDiagnostics extends Command
{
    public function handle(): int
    {
        $channel = (string) config('logging.default');
        $channels = (array) config('logging.channels', []);
        $structuredConfigured = array_key_exists('structured', $channels);

        $this->line('Environment: '.app()->environment());
        $this->line('Log channel: '.$channel);
        $this->line('Structured channel configured: '.($structuredConfigured ? 'yes' : 'no'));

        $defaultConnection = (string) config('queue.default');
        $translationQueue = (string) config('translation.queue');
        $effectiveConnection = TranslationQueueConfig::effectiveConnection() ?? 'none';
        $provider = TranslationQueueConfig::providerTimeoutSeconds();
        $job = TranslationQueueConfig::jobTimeoutSeconds();
        $required = TranslationQueueConfig::requiredRetryAfterSeconds();
        $violation = TranslationQueueConfig::consistencyViolation();

        $this->newLine();
        $this->line('Queue default connection: '.$defaultConnection);
        $this->line('Translation queue: '.$translationQueue);
        $this->line('Translation effective connection: '.$effectiveConnection);
        $this->line("Timeouts: provider {$provider}s < job {$job}s < required retry_after {$required}s");
        $this->line('Timeout consistency: '.($violation === null ? 'ok' : 'VIOLATION'));

        if ($violation !== null) {
            $this->warn($violation);
        }

        // P7-003: transcription queue section. Field names and the aggregate
        // "Timeout consistency" line above follow the frozen P7-005
        // conventions; this section adds per-queue detail without renaming
        // them.
        $transcriptionQueue = (string) config('transcription.queue');
        $transcriptionConnection = TranscriptionQueueConfig::effectiveConnection() ?? 'none';
        $transcriptionViolation = TranscriptionQueueConfig::consistencyViolation();

        $this->line('Transcription queue: '.$transcriptionQueue);
        $this->line('Transcription effective connection: '.$transcriptionConnection);
        $this->line('Transcription timeout consistency: '.($transcriptionViolation === null ? 'ok' : 'VIOLATION'));

        if ($transcriptionViolation !== null) {
            $this->warn($transcriptionViolation);
        }

        $this->newLine();
        $this->line('Schedule: '.$this->scheduleSummary('translation:recover-stale-attempts'));
        $this->line('Schedule: '.$this->scheduleSummary('transcription:recover-stale-attempts'));

        // P7-001: env/registry section. Additive lines within the frozen
        // P7-005 field naming; no existing line format is altered.
        $registryCount = count(ProductionEnvRegistry::definitions());
        $limitAudit = UploadLimitAudit::evaluate();
        $redisRecord = RedisPosture::record();

        $this->newLine();
        $this->line('Env registry: '.$registryCount.' keys registered');
        $this->line('Upload limits: '.($limitAudit['pass'] ? 'ok' : 'BELOW REQUIRED')
            .' (upload_max_filesize '.UploadLimitAudit::formatBytes($limitAudit['upload_max_filesize'])
            .', post_max_size '.UploadLimitAudit::formatBytes($limitAudit['post_max_size'])
            .', required '.UploadLimitAudit::formatBytes($limitAudit['required']).')');
        $this->line('Redis posture: '.$redisRecord['host'].':'.$redisRecord['port']
            .($redisRecord['loopback'] ? ' (loopback)' : ' (non-loopback)')
            .($redisRecord['password_set'] ? ', authenticated' : ', no password')
            .($redisRecord['version'] !== null ? ', version '.$redisRecord['version'] : ''));
        $this->line('Target evidence: '.TargetHostEvidence::ITEM_AC2_REBOOT.'='
            .TargetHostEvidence::itemStatus(TargetHostEvidence::ITEM_AC2_REBOOT)
            .', '.TargetHostEvidence::ITEM_AC8_DRAIN.'='
            .TargetHostEvidence::itemStatus(TargetHostEvidence::ITEM_AC8_DRAIN));

        // P7-002: datastore section. Additive lines within the frozen
        // P7-005 field naming; driver + posture only, never a migration
        // readiness claim (G-02 belongs to P7-012).
        $datastoreConnection = (string) config('database.default', 'sqlite');
        $datastoreViolations = DatastorePosture::evaluate($datastoreConnection);

        $this->line('Datastore driver: '.$datastoreConnection
            .($datastoreViolations === [] ? ' (posture ok)' : ' ('.count($datastoreViolations).' violation(s))'));

        // P7-006: security section. Additive lines within the frozen P7-005
        // field naming; no live daemon probe here (use `clamav:health` and
        // `--probe-worker` for network-touching checks).
        $scanner = app(ClamavScanner::class);
        $endpoint = $scanner->endpoint();

        $this->newLine();
        $this->line('CSP mode: '.config('security.csp_mode', 'enforce'));
        $this->line('Limiters: login='
            .(RateLimiter::limiter('login') !== null ? 'yes' : 'no')
            .', upload-initiate='
            .(RateLimiter::limiter('upload-initiate') !== null ? 'yes' : 'no')
            .', csp-report='
            .(RateLimiter::limiter('csp-report') !== null ? 'yes' : 'no'));
        $this->line('ClamAV: '.($scanner->isEnabled() ? 'enabled' : 'disabled')
            .(isset($endpoint['refused']) ? ' (endpoint refused)' : ' ('.$endpoint['transport'].' '.$endpoint['target'].')'));

        // P7-007: backup section. Additive lines within the frozen P7-005
        // field naming; presence/health only, never drill readiness.
        $backup = app(BackupManager::class);
        $backupTarget = $backup->targetRoot();
        $latest = $backup->latestManifest($backupTarget);

        $this->newLine();
        $this->line('Backup target: '.$backupTarget.((is_dir($backupTarget) && is_writable($backupTarget)) ? ' (writable)' : ' (missing or not writable)'));
        $this->line('Backup schedule: '.$this->scheduleSummary('backup:run'));

        if ($latest === null) {
            $this->line('Backup last-good: none recorded');
        } else {
            $this->line('Backup last-good: '.($latest['name'] ?? 'unknown')
                .' ('.($latest['status'] ?? BackupManager::STATUS_FAILED).')');
        }

        // P7-004: storage section. Additive lines within the frozen P7-005
        // field naming; topology truth only, never a capacity-proof claim.
        $topology = StorageTopology::evaluate();

        $this->newLine();
        $this->line('Storage disk: '.$topology['detail']['disk'].' ('.$topology['detail']['driver'].')');
        $this->line('Storage root: '.$topology['detail']['root']
            .($topology['violations'] === [] ? ' (topology ok)' : ' ('.count($topology['violations']).' violation(s))'));

        // P7-011: retention section. Additive lines within the frozen
        // P7-005 field naming; ledger presence only, never a G-09 claim.
        $this->line('Retention schedule: '.$this->scheduleSummary('retention:purge'));

        try {
            $retentionCounts = DB::table('retention_purge_audits')
                ->selectRaw('outcome, COUNT(*) as aggregate')
                ->groupBy('outcome')
                ->pluck('aggregate', 'outcome')
                ->all();

            $this->line('Retention ledger: '.($retentionCounts === []
                ? 'no runs recorded'
                : implode(', ', array_map(
                    fn ($outcome, $count): string => "{$outcome}={$count}",
                    array_keys($retentionCounts),
                    array_values($retentionCounts)
                ))));
        } catch (Throwable $throwable) {
            $this->line('Retention ledger: unavailable ('.$throwable->getMessage().')');
        }

        $probeFailed = false;

        if ($this->option('probe-worker')) {
            $probeFailed = ! $this->probeWorker();
        }

        $failed = $violation !== null || $transcriptionViolation !== null || $probeFailed;

        if ($failed && $this->option('strict')) {
            $this->error('Observability diagnostics detected a failure condition.');

            return self::FAILURE;
        }

        $this->info('Observability diagnostics complete.');

        return self::SUCCESS;
    }

    /**
     * Derive the stale-recovery schedule line from the actual scheduler state
     * instead of a hardcoded string, so it cannot drift from routes/console.php.
     */
    private function scheduleSummary(string $command): string
    {
        try {
            $events = app(Schedule::class)->events();
        } catch (Throwable) {
            return $command.' (scheduler unavailable)';
        }

        foreach ($events as $event) {
            if (! is_string($event->command) || ! str_contains($event->command, $command)) {
                continue;
            }

            $parts = [$this->describeExpression((string) $event->expression)];

            if ($event->withoutOverlapping) {
                $parts[] = 'without overlapping';
            }

            return $command.' ('.implode(', ', $parts).')';
        }

        return $command.' (not scheduled)';
    }

    private function describeExpression(string $expression): string
    {
        return match ($expression) {
            '* * * * *' => 'every minute',
            default => 'cron "'.$expression.'"',
        };
    }

    private function probeWorker(): bool
    {
        $ok = true;

        foreach ([
            'transcription' => config('transcription.worker_url'),
            'translation' => config('translation.worker_url'),
        ] as $label => $url) {
            if (! is_string($url) || $url === '') {
                $this->line("Worker ({$label}): not configured");

                continue;
            }

            $health = rtrim($url, '/').'/health';

            try {
                $response = Http::timeout(2)->get($health);
                $reachable = $response->successful();
            } catch (Throwable) {
                $reachable = false;
            }

            $this->line("Worker ({$label}): ".($reachable ? 'reachable' : 'unreachable')." [{$health}]");

            if (! $reachable) {
                $ok = false;
            }
        }

        return $ok;
    }
}
