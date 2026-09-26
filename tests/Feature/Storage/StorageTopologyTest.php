<?php

use App\Storage\StorageTopology;

/*
 * P7-004: local private-storage topology truth (D7-03/A). Pure logic
 * with injectable seams; the live mount is reported by
 * `storage:validate-topology` and the verify sub-check, never asserted
 * here (machine-dependent).
 */

function topologyGoodSeams(): array
{
    return [
        'disk' => 'local',
        'driver' => 'local',
        'root' => '/app/storage/app',
        'rootExists' => true,
        'rootWritable' => true,
        'freeBytes' => 10 * 1024 * 1024 * 1024,
        'minimum' => 1024 * 1024 * 1024,
    ];
}

it('accepts a healthy local private topology', function (): void {
    $result = StorageTopology::evaluate(topologyGoodSeams());

    expect($result['violations'])->toBe([])
        ->and($result['detail']['driver'])->toBe('local');
});

it('rejects non-local drivers (object storage is deferred, not adopted)', function (): void {
    foreach (['s3', 's3-compatible', 'minio', 'r2'] as $driver) {
        $seams = topologyGoodSeams();
        $seams['driver'] = $driver;

        expect(implode(' ', StorageTopology::evaluate($seams)['violations']))->toContain('must be "local"');
    }
});

it('rejects a missing or unwritable root', function (): void {
    $missing = topologyGoodSeams();
    $missing['rootExists'] = false;

    expect(implode(' ', StorageTopology::evaluate($missing)['violations']))->toContain('does not exist');

    $readOnly = topologyGoodSeams();
    $readOnly['rootWritable'] = false;

    expect(implode(' ', StorageTopology::evaluate($readOnly)['violations']))->toContain('not writable');
});

it('rejects free space below the floor and unknown capacity', function (): void {
    $low = topologyGoodSeams();
    $low['freeBytes'] = 100;

    expect(implode(' ', StorageTopology::evaluate($low)['violations']))->toContain('below the required minimum');

    $unknown = topologyGoodSeams();
    $unknown['freeBytes'] = false;

    expect(implode(' ', StorageTopology::evaluate($unknown)['violations']))->toContain('could not be determined');
});

it('rejects an unconfigured disk', function (): void {
    $seams = topologyGoodSeams();
    $seams['disk'] = '';

    expect(implode(' ', StorageTopology::evaluate($seams)['violations']))->toContain('not configured');
});

it('reports the live topology without failing the suite on this dev box', function (): void {
    $result = StorageTopology::evaluate();

    expect($result['detail'])->toHaveKeys(['disk', 'driver', 'root'])
        ->and($result['detail']['disk'])->toBeString();
});
