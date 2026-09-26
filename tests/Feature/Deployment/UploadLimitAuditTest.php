<?php

use App\Deployment\UploadLimitAudit;

/*
 * P7-001: upload-limit adequacy audit (TD-002 application share). Pure
 * logic with injectable ini values; never depends on this machine's
 * php.ini. Boundary math: 500 MiB product boundary + overhead = 600 MiB
 * floor (629,145,600 bytes).
 */

it('parses php.ini size strings', function (): void {
    expect(UploadLimitAudit::iniBytes('x', '512M'))->toBe(512 * 1024 * 1024)
        ->and(UploadLimitAudit::iniBytes('x', '1G'))->toBe(1024 * 1024 * 1024)
        ->and(UploadLimitAudit::iniBytes('x', '2048K'))->toBe(2048 * 1024)
        ->and(UploadLimitAudit::iniBytes('x', '4096'))->toBe(4096)
        ->and(UploadLimitAudit::iniBytes('x', '-1'))->toBe(PHP_INT_MAX);
});

it('passes limits at or above the 600 MiB floor', function (): void {
    $report = UploadLimitAudit::evaluate(['upload_max_filesize' => '700M', 'post_max_size' => '1G']);

    expect($report['pass'])->toBeTrue()->and($report['findings'])->toBe([]);
});

it('fails limits below the floor with actionable findings', function (): void {
    $report = UploadLimitAudit::evaluate(['upload_max_filesize' => '2M', 'post_max_size' => '8M']);

    expect($report['pass'])->toBeFalse()
        ->and($report['findings'])->toHaveCount(2)
        ->and(implode(' ', $report['findings']))->toContain('upload_max_filesize')
        ->and(implode(' ', $report['findings']))->toContain('post_max_size');
});

it('treats unlimited as passing', function (): void {
    $report = UploadLimitAudit::evaluate(['upload_max_filesize' => '-1', 'post_max_size' => '-1']);

    expect($report['pass'])->toBeTrue();
});

it('pins the required floor to 600 MiB', function (): void {
    expect(UploadLimitAudit::REQUIRED_BYTES)->toBe(629_145_600)
        ->and(UploadLimitAudit::PRODUCT_BOUNDARY_BYTES)->toBe(524_288_000);
});
