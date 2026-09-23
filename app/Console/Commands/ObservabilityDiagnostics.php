<?php

namespace App\Console\Commands;

use App\Translation\TranslationQueueConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
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
        $this->line('Schedule: '.$this->scheduleSummary('translation:recover-stale-attempts'));

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
