<?php

namespace App\Console\Commands;

use App\Capacity\CapacityEnvelope;
use App\Capacity\CapacityReport;
use App\Capacity\CapacityStats;
use App\Capacity\CompletionLedger;
use App\Capacity\CorpusBuilder;
use App\Capacity\EnvironmentMetadata;
use App\Capacity\WorkloadResult;
use App\Capacity\WorkloadRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Deterministic capacity-validation harness (P7-009 Phase A).
 *
 * Builds a seeded synthetic corpus, executes the contract-approved
 * workloads with timer instrumentation, and retains a full evidence
 * bundle (envelope, invocation, environment, raw + summarized results,
 * failure records, completion accounting, rehearsal report).
 *
 * REHEARSAL / SUBSTITUTE EVIDENCE — NOT TARGET CAPACITY EVIDENCE.
 * This command never produces a capacity verdict, a production
 * capacity claim, or a release-fitness statement. The final
 * production-shaped run (Phase B) is NOT AUTHORIZED and cannot be
 * produced by this command.
 *
 * Exit code: 0 when the harness executed and completion accounting
 * reconciles; non-zero when the harness itself is defective (exception
 * escaping a workload boundary, unreconciled accounting, or evidence
 * persistence failure). Degraded application findings are reported,
 * never hidden, and never fail the run — measure and report.
 */
#[Signature('capacity:validate
    {--repeat=3 : Repeats per workload (1-10)}
    {--concurrency=3 : Queue batch size per repeat (1-10, rehearsal-safe)}
    {--seed=909 : Corpus seed (reproducibility identifier)}
    {--blob-bytes=262144 : Rehearsal upload/streaming blob size in bytes (capped at 8 MiB)}
    {--segments=60 : Synthetic transcript segment count (capped at 500)}
    {--output= : Evidence directory (default: verification/p7-009)}')]
#[Description('Run the P7-009 Phase A rehearsal harness (synthetic corpus, substitute infrastructure only).')]
final class CapacityValidate extends Command
{
    public function handle(): int
    {
        $repeat = max(1, min(10, (int) $this->option('repeat')));
        $concurrency = max(1, min(10, (int) $this->option('concurrency')));
        $seed = (int) $this->option('seed');
        $blobBytes = max(1024, min(8_388_608, (int) $this->option('blob-bytes')));
        $segments = max(1, min(500, (int) $this->option('segments')));

        $runId = 'p7-009-'.now()->format('Ymd-His').'-'.substr(hash('sha256', $seed.':'.$repeat.':'.$concurrency.':'.microtime(true)), 0, 8);
        $base = (string) ($this->option('output') !== '' && $this->option('output') !== null
            ? $this->option('output')
            : base_path('verification/p7-009'));
        $dir = rtrim($base, '/\\').DIRECTORY_SEPARATOR.$runId;

        try {
            File::ensureDirectoryExists($dir);
        } catch (Throwable $e) {
            $this->error('Evidence directory could not be created: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line(CapacityEnvelope::CLASSIFICATION);
        $this->line('Run: '.$runId.' (Phase A rehearsal; Phase B NOT AUTHORIZED)');

        $invocation = [
            'run_id' => $runId,
            'command' => 'capacity:validate',
            'options' => ['repeat' => $repeat, 'concurrency' => $concurrency, 'seed' => $seed, 'blob_bytes' => $blobBytes, 'segments' => $segments],
            'classification' => CapacityEnvelope::CLASSIFICATION,
            'started_at' => now()->toIso8601String(),
        ];

        try {
            $corpus = CorpusBuilder::build($seed, $blobBytes, $segments);
            $environment = EnvironmentMetadata::collect($runId);
            $ledger = new CompletionLedger;
            $runner = new WorkloadRunner($runId, $corpus, $ledger);

            ['results' => $results, 'failures' => $failures] = $runner->runAll($repeat, $concurrency);
        } catch (Throwable $e) {
            $this->error('Harness defect (execution failed): '.get_class($e).': '.$e->getMessage());

            return self::FAILURE;
        }

        /** @var list<WorkloadResult> $results */
        $summaries = [];

        foreach (CapacityEnvelope::items() as $item) {
            $values = [];

            foreach ($results as $result) {
                if ($result->workload === $item['key'] && $result->outcome === 'completed' && $result->elapsedMs !== null) {
                    $values[] = $result->elapsedMs;
                }
            }

            $summaries[$item['key']] = CapacityStats::summarize($values);
        }

        $reconciliation = $ledger->reconcile();
        $accounting = $ledger->toArray();

        $raw = array_map(static fn (WorkloadResult $r): array => $r->toArray(), $results);
        $summary = [
            'run_id' => $runId,
            'classification' => CapacityEnvelope::CLASSIFICATION,
            'verdict' => 'NONE — Phase A rehearsal states no capacity verdict, no production capacity, no release fitness.',
            'phase_b' => 'NOT AUTHORIZED',
            'options' => $invocation['options'],
            'summaries' => $summaries,
            'completion_accounting' => $accounting,
            'accounting_reconciled' => $reconciliation['reconciled'],
            'accounting_mismatches' => $reconciliation['mismatches'],
            'failure_count' => count($failures),
            'envelope_keys' => array_column(CapacityEnvelope::items(), 'key'),
        ];

        try {
            File::put($dir.'/CAPACITY-ENVELOPE.md', CapacityEnvelope::toMarkdown($runId));
            File::put($dir.'/invocation.json', $this->encode(array_merge($invocation, ['finished_at' => now()->toIso8601String()])));
            File::put($dir.'/environment.json', $this->encode($environment));
            File::put($dir.'/corpus-manifest.json', $this->encode($corpus));
            File::put($dir.'/raw-results.json', $this->encode($raw));
            File::put($dir.'/summary.json', $this->encode($summary));
            File::put($dir.'/failures.json', $this->encode($failures));
            File::put($dir.'/completion-accounting.json', $this->encode($accounting));
            File::put($dir.'/REHEARSAL-REPORT.md', CapacityReport::toMarkdown($runId, $results, $summaries, $accounting, $environment, $failures, $reconciliation['reconciled']));
        } catch (Throwable $e) {
            $this->error('Harness defect (evidence persistence failed): '.$e->getMessage());

            return self::FAILURE;
        }

        $completed = count(array_filter($results, fn (WorkloadResult $r): bool => $r->outcome === 'completed'));
        $this->info(sprintf(
            'Rehearsal complete: %d/%d repeats completed, %d failed, %d failures recorded. Accounting %s. Evidence: %s',
            $completed,
            count($results),
            count(array_filter($results, fn (WorkloadResult $r): bool => $r->outcome === 'failed')),
            count($failures),
            $reconciliation['reconciled'] ? 'reconciled' : 'MISMATCHED',
            $dir
        ));

        if (! $reconciliation['reconciled']) {
            $this->error('Completion accounting mismatch (harness defect): '.implode(', ', $reconciliation['mismatches']));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function encode(mixed $data): string
    {
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{"error":"json-encoding-failed"}' : $encoded;
    }
}
