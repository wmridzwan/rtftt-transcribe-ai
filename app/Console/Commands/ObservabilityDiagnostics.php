<?php

namespace App\Console\Commands;

use App\Translation\TranslationQueueConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
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

        $this->newLine();
        $this->line('Schedule: translation:recover-stale-attempts (every minute, without overlapping)');

        $probeFailed = false;

        if ($this->option('probe-worker')) {
            $probeFailed = ! $this->probeWorker();
        }

        $failed = $violation !== null || $probeFailed;

        if ($failed && $this->option('strict')) {
            $this->error('Observability diagnostics detected a failure condition.');

            return self::FAILURE;
        }

        $this->info('Observability diagnostics complete.');

        return self::SUCCESS;
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
