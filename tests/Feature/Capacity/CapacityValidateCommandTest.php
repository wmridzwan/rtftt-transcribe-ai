<?php

use App\Capacity\CapacityEnvelope;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
 * P7-009 Phase A: the rehearsal command executes end to end on
 * substitute infrastructure and retains a labeled evidence bundle
 * with reconciled completion accounting.
 */

beforeEach(function () {
    Storage::fake('local');
});

it('runs the rehearsal and retains labeled evidence with reconciled accounting', function (): void {
    $output = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p7009-'.uniqid();

    $exit = Artisan::call('capacity:validate', [
        '--repeat' => 1,
        '--concurrency' => 2,
        '--seed' => 909,
        '--blob-bytes' => 4096,
        '--segments' => 6,
        '--output' => $output,
    ]);

    expect($exit)->toBe(0);

    $runs = array_values(array_filter(
        scandir($output) ?: [],
        fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($output.DIRECTORY_SEPARATOR.$entry)
    ));

    expect($runs)->toHaveCount(1);

    $dir = $output.DIRECTORY_SEPARATOR.$runs[0];

    foreach (['CAPACITY-ENVELOPE.md', 'invocation.json', 'environment.json', 'corpus-manifest.json', 'raw-results.json', 'summary.json', 'failures.json', 'completion-accounting.json', 'REHEARSAL-REPORT.md'] as $file) {
        expect(is_file($dir.DIRECTORY_SEPARATOR.$file))->toBeTrue($file.' retained');
    }

    $report = (string) file_get_contents($dir.'/REHEARSAL-REPORT.md');

    expect($report)->toContain('REHEARSAL / SUBSTITUTE EVIDENCE — NOT TARGET CAPACITY EVIDENCE')
        ->and($report)->toContain('NO capacity verdict')
        ->and($report)->toContain('Phase B remains NOT AUTHORIZED');

    $summary = json_decode((string) file_get_contents($dir.'/summary.json'), true);

    expect($summary['classification'])->toBe(CapacityEnvelope::CLASSIFICATION)
        ->and($summary['phase_b'])->toBe('NOT AUTHORIZED')
        ->and($summary['accounting_reconciled'])->toBeTrue()
        ->and(array_keys($summary['completion_accounting']))->toBe(['upload', 'probe', 'transcription', 'translation', 'queue', 'saturation', 'streaming', 'render', 'export']);

    foreach ($summary['completion_accounting'] as $workload => $counts) {
        expect($counts['reconciled'])->toBeTrue('accounting reconciles for '.$workload);
    }

    $raw = json_decode((string) file_get_contents($dir.'/raw-results.json'), true);

    expect($raw)->toHaveCount(9);

    foreach ($raw as $result) {
        expect($result)->toHaveKeys(['workload', 'repeat', 'run_id', 'started_at', 'completed_at', 'elapsed_ms', 'concurrency', 'outcome', 'correctness']);
    }

    $environment = json_decode((string) file_get_contents($dir.'/environment.json'), true);

    expect($environment['environment_classification'])->toBe('REHEARSAL / SUBSTITUTE');

    // Rehearsal hygiene: no synthetic objects or probe markers leak.
    expect(Storage::disk((string) config('media.storage_disk'))->allFiles('capacity'))->toBe([]);
});
