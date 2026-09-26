<?php

use App\Capacity\CapacityEnvelope;
use App\Capacity\CapacityStats;
use App\Capacity\CapacityTimer;
use App\Capacity\CompletionLedger;
use App\Capacity\CorpusBuilder;

/*
 * P7-009 Phase A: harness self-tests (timer math, fixture integrity,
 * completion-accounting reconciliation, report/statistics rendering).
 */

it('measures elapsed time between explicit boundaries only', function (): void {
    $timer = new CapacityTimer;

    expect($timer->elapsedMilliseconds())->toBeNull()
        ->and($timer->hasCompletedBoundaries())->toBeFalse();

    $timer->start();
    usleep(1500);
    $timer->stop();

    $elapsed = $timer->elapsedMilliseconds();

    expect($elapsed)->not->toBeNull()
        ->and($elapsed)->toBeGreaterThan(0)
        ->and($timer->hasCompletedBoundaries())->toBeTrue();
});

it('never fabricates a measurement from an incomplete boundary', function (): void {
    $timer = new CapacityTimer;
    $timer->start();

    expect($timer->elapsedMilliseconds())->toBeNull();

    $unstopped = new CapacityTimer;
    $unstopped->stop();

    expect($unstopped->elapsedMilliseconds())->toBeNull();
});

it('builds a reproducible synthetic corpus with recorded characteristics', function (): void {
    $first = CorpusBuilder::build(909, 4096, 12);
    $second = CorpusBuilder::build(909, 4096, 12);

    expect($first)->toBe($second)
        ->and($first['seed'])->toBe(909)
        ->and($first['items'])->toHaveCount(3);

    foreach ($first['items'] as $item) {
        expect($item)->toHaveKeys(['name', 'format', 'size_bytes', 'duration_seconds', 'language_characteristics', 'workload_class', 'concurrency_configuration', 'payload', 'sha256'])
            ->and(hash('sha256', (string) base64_decode((string) $item['payload'])))->toBe($item['sha256']);
    }

    $other = CorpusBuilder::build(910, 4096, 12);

    expect($other['sha256'])->not->toBe($first['sha256']);
});

it('reconciles completion accounting and flags mismatches', function (): void {
    $ledger = new CompletionLedger;
    $ledger->record('upload', 3, 2, 1, 0);
    $ledger->record('queue', 3, 1, 1, 1);

    expect($ledger->reconcile())->toBe(['reconciled' => true, 'mismatches' => []]);

    $ledger->record('broken', 3, 1, 1, 0);

    expect($ledger->reconcile())->toBe(['reconciled' => false, 'mismatches' => ['broken']]);
});

it('increments dispatched/completed/failed/skipped outcomes', function (): void {
    $ledger = new CompletionLedger;
    $ledger->increment('probe', 'completed');
    $ledger->increment('probe', 'skipped');
    $ledger->increment('probe', 'failed');

    expect($ledger->toArray()['probe'])->toBe([
        'dispatched' => 3,
        'completed' => 1,
        'failed' => 1,
        'skipped' => 1,
        'reconciled' => true,
    ]);
});

it('summarizes repeats while preserving raw values and exclusion notes', function (): void {
    $summary = CapacityStats::summarize([3.0, 1.0, 2.0], ['repeat 2 ran during a GC pause; raw retained']);

    expect($summary['count'])->toBe(3)
        ->and($summary['min'])->toBe(1.0)
        ->and($summary['max'])->toBe(3.0)
        ->and($summary['mean'])->toBe(2.0)
        ->and($summary['median'])->toBe(2.0)
        ->and($summary['values'])->toBe([1.0, 2.0, 3.0])
        ->and($summary['exclusion_notes'])->toBe(['repeat 2 ran during a GC pause; raw retained']);
});

it('summarizes empty input without fabricating numbers', function (): void {
    $summary = CapacityStats::summarize([]);

    expect($summary['count'])->toBe(0)
        ->and($summary['mean'])->toBeNull()
        ->and($summary['values'])->toBe([]);
});

it('defines exactly the contract-approved workload categories with no verdicts', function (): void {
    $keys = array_column(CapacityEnvelope::items(), 'key');

    expect($keys)->toBe(['upload', 'probe', 'transcription', 'translation', 'queue', 'saturation', 'streaming', 'render', 'export']);

    $markdown = CapacityEnvelope::toMarkdown('test-run');

    expect($markdown)->toContain(CapacityEnvelope::CLASSIFICATION)
        ->and($markdown)->toContain('P7-012 decides')
        ->and(strtolower($markdown))->not->toContain('verdict: pass');
});
