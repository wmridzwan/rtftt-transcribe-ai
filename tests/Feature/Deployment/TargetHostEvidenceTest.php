<?php

use App\Deployment\TargetHostEvidence;
use App\Deployment\UploadLimitAudit;

/*
 * P7-001 AC8: real-host carry-forward evidence (AC2 reboot-cycle + AC8
 * SIGTERM-drain). Schema validation, record/load roundtrip, golden
 * command output. Recorded files are removed after each test so no
 * evidence is fabricated into the repo.
 */

function targetEvidencePayload(): array
{
    return [
        'host' => 'staging-linux-01',
        'items' => [
            'ac2-reboot-cycle' => [
                'result' => 'pass',
                'detail' => 'units enabled at boot; workers resumed; scheduler timer active',
                'recorded_at' => '2026-09-26T00:00:00Z',
            ],
            'ac8-sigterm-drain' => [
                'result' => 'pass',
                'detail' => 'SIGTERM drained current job in 12s (budget 330s job / 420s stop)',
                'recorded_at' => '2026-09-26T00:00:00Z',
            ],
        ],
    ];
}

function removeTargetEvidenceFile(): void
{
    $path = TargetHostEvidence::path();

    if (is_file($path)) {
        unlink($path);
    }
}

it('rejects payloads missing host, items, or item fields', function (): void {
    expect(TargetHostEvidence::validate(['nope' => true]))->not->toBeEmpty()
        ->and(TargetHostEvidence::validate(['host' => 'h', 'items' => []]))->not->toBeEmpty()
        ->and(TargetHostEvidence::validate(['host' => 'h', 'items' => [
            'ac2-reboot-cycle' => ['result' => 'maybe', 'detail' => 'x', 'recorded_at' => 't'],
            'ac8-sigterm-drain' => ['result' => 'pass', 'detail' => 'x', 'recorded_at' => 't'],
        ]]))->not->toBeEmpty();
});

it('records, loads, and reports item status', function (): void {
    try {
        TargetHostEvidence::record(targetEvidencePayload());

        expect(TargetHostEvidence::itemStatus('ac2-reboot-cycle'))->toBe('pass')
            ->and(TargetHostEvidence::itemStatus('ac8-sigterm-drain'))->toBe('pass')
            ->and(TargetHostEvidence::load()['host'])->toBe('staging-linux-01');
    } finally {
        removeTargetEvidenceFile();
    }
});

it('reports pending when no evidence is recorded', function (): void {
    removeTargetEvidenceFile();

    expect(TargetHostEvidence::itemStatus('ac2-reboot-cycle'))->toBe('pending');
});

it('records valid evidence files with golden output', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'p7001-evidence-').'.json';
    file_put_contents($file, json_encode(targetEvidencePayload()));

    try {
        $this->artisan('deployment:record-target-evidence', ['file' => $file])
            ->expectsOutputToContain('Target evidence recorded:')
            ->expectsOutputToContain('Evidence [ac2-reboot-cycle]: pass')
            ->expectsOutputToContain('Evidence [ac8-sigterm-drain]: pass')
            ->assertExitCode(0);
    } finally {
        unlink($file);
        removeTargetEvidenceFile();
    }
});

it('rejects unrecordable evidence files with exit 1', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'p7001-evidence-').'.json';
    file_put_contents($file, json_encode(['host' => 'h']));

    try {
        $this->artisan('deployment:record-target-evidence', ['file' => $file])
            ->expectsOutputToContain('Evidence rejected:')
            ->assertExitCode(1);
    } finally {
        unlink($file);
        removeTargetEvidenceFile();
    }
});

it('emits a machine-parseable audit report with the pinned floor', function (): void {
    // Console mechanics hold on any machine: human verdict lines print
    // and the exit code always matches the evaluated verdict.
    $this->artisan('env:audit-limits', ['--json' => true])
        ->expectsOutputToContain('629145600')
        ->assertExitCode(UploadLimitAudit::evaluate()['pass'] ? 0 : 1);

    $this->artisan('env:audit-limits')
        ->expectsOutputToContain('verdict:')
        ->expectsOutputToContain('required minimum:');
});

it('serializes the audit report with verdict, floor, and findings', function (): void {
    $decoded = json_decode(json_encode(UploadLimitAudit::evaluate(), JSON_PRETTY_PRINT), true);

    expect($decoded)->toHaveKeys(['upload_max_filesize', 'post_max_size', 'required', 'pass', 'findings'])
        ->and($decoded['required'])->toBe(629145600)
        ->and($decoded['pass'])->toBeBool();
});
