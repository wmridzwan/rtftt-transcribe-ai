<?php

namespace App\Capacity;

/**
 * Rehearsal report renderer for P7-009 Phase A.
 *
 * Every report carries the REHEARSAL / SUBSTITUTE banner so the label
 * survives into retained artifacts, and states NO capacity verdict,
 * NO production capacity, and NO release fitness — the Phase A outcome
 * is harness executability evidence, not a capacity verdict.
 */
final class CapacityReport
{
    /**
     * @param  list<WorkloadResult>  $results
     * @param  array<string, array{count: int, min: ?float, max: ?float, mean: ?float, median: ?float, stddev: ?float, values: list<float>, exclusion_notes: list<string>}>  $summaries
     * @param  array<string, array{dispatched: int, completed: int, failed: int, skipped: int, reconciled: bool}>  $accounting
     * @param  array<string, mixed>  $environment
     * @param  list<array<string, mixed>>  $failures
     */
    public static function toMarkdown(
        string $runId,
        array $results,
        array $summaries,
        array $accounting,
        array $environment,
        array $failures,
        bool $reconciled,
    ): string {
        $completed = count(array_filter($results, fn (WorkloadResult $r): bool => $r->outcome === 'completed'));
        $failed = count(array_filter($results, fn (WorkloadResult $r): bool => $r->outcome === 'failed'));
        $skipped = count(array_filter($results, fn (WorkloadResult $r): bool => $r->outcome === 'skipped'));

        $lines = [
            '# P7-009 Phase A Rehearsal Report',
            '',
            '> '.CapacityEnvelope::CLASSIFICATION,
            '>',
            '> Run: `'.$runId.'`. This report is harness-executability',
            '> evidence only. It states NO capacity verdict, NO production',
            '> capacity, and NO release fitness. The final',
            '> production-shaped capacity run (Phase B) is NOT AUTHORIZED.',
            '',
            '## Completion accounting (readiness LOW finding addressed)',
            '',
            'Reconciled: '.($reconciled ? 'YES' : 'NO — see mismatches below').'. '
                .'Repeats total: '.count($results).' (completed '.$completed
                .', failed '.$failed.', skipped '.$skipped.').',
            '',
        ];

        foreach ($accounting as $workload => $counts) {
            $lines[] = '- `'.$workload.'`: dispatched '.$counts['dispatched']
                .', completed '.$counts['completed']
                .', failed '.$counts['failed']
                .', skipped '.$counts['skipped']
                .' — '.($counts['reconciled'] ? 'reconciled' : 'MISMATCH');
        }

        $lines[] = '';
        $lines[] = '## Per-workload timing summaries (rehearsal scale)';
        $lines[] = '';

        foreach ($summaries as $workload => $summary) {
            $lines[] = '### `'.$workload.'` (n='.$summary['count'].')';
            $lines[] = '';

            if ($summary['count'] === 0) {
                $lines[] = 'No completed repeats with timing (all failed or skipped; see failure records).';
            } else {
                $lines[] = '- min/max: '.self::ms($summary['min']).' / '.self::ms($summary['max']);
                $lines[] = '- mean/median: '.self::ms($summary['mean']).' / '.self::ms($summary['median']);
                $lines[] = '- stddev: '.self::ms($summary['stddev']);
                $lines[] = '- raw values (ms): '.implode(', ', array_map(fn (float $v): string => number_format($v, 2), $summary['values']));
            }

            foreach ($summary['exclusion_notes'] as $note) {
                $lines[] = '- exclusion note (raw retained): '.$note;
            }

            $lines[] = '';
        }

        $lines[] = '## Failure records';
        $lines[] = '';

        if ($failures === []) {
            $lines[] = 'No failed repeats in this run.';
        } else {
            foreach ($failures as $failure) {
                $lines[] = '- `'.($failure['workload'] ?? '?').'` repeat '.($failure['repeat'] ?? '?')
                    .': point='.($failure['failure_point'] ?? '?')
                    .'; detail='.($failure['failure_detail'] ?? '?');
            }
        }

        $lines[] = '';
        $lines[] = '## Rehearsal environment (substitute, not target)';
        $lines[] = '';
        $lines[] = '- classification: `'.($environment['environment_classification'] ?? '?').'`';
        $lines[] = '- os: '.($environment['os'] ?? '?');
        $lines[] = '- commit: '.($environment['commit'] ?? '?');
        $lines[] = '- php: '.(is_array($environment['php'] ?? null) ? ($environment['php']['version'] ?? '?') : '?');
        $lines[] = '- database: '.(is_array($environment['database'] ?? null) ? ($environment['database']['driver'] ?? '?') : '?');
        $lines[] = '- queue: '.(is_array($environment['queue'] ?? null) ? json_encode($environment['queue']) : '?');
        $lines[] = '- storage: '.(is_array($environment['storage'] ?? null) ? json_encode($environment['storage']) : '?');
        $lines[] = '- models: '.(is_array($environment['models'] ?? null) ? json_encode($environment['models']) : '?');
        $lines[] = '';
        $lines[] = 'Full metadata: `environment.json` in this run directory.';
        $lines[] = '';
        $lines[] = '## Interpretation boundary';
        $lines[] = '';
        $lines[] = 'These numbers describe rehearsal-harness executability on';
        $lines[] = 'substitute infrastructure. They MUST NOT be read as';
        $lines[] = 'production capacity, target-hardware RTF/latency, G-01 proof,';
        $lines[] = 'or release fitness. Phase B remains NOT AUTHORIZED.';

        return implode("\n", $lines)."\n";
    }

    private static function ms(?float $value): string
    {
        return $value === null ? 'n/a' : number_format($value, 2).' ms';
    }
}
