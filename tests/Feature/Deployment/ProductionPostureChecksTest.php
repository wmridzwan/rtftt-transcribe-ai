<?php

use App\Deployment\ProductionPostureChecks;

/*
 * P7-001: additive posture rules (Redis auth, upload adequacy, storage
 * capacity). Pure logic with injectable seams; enforced at boot in
 * production only, alongside — never instead of — the P7-008 guard.
 */

function postureSafeSeams(): array
{
    return [
        'ini' => ['upload_max_filesize' => '700M', 'post_max_size' => '700M'],
        'redis' => ['host' => '127.0.0.1', 'port' => 6379, 'password' => null],
        'free' => 10 * 1024 * 1024 * 1024,
    ];
}

it('reports no violations for a safe posture', function (): void {
    $seams = postureSafeSeams();

    expect(ProductionPostureChecks::violations($seams['ini'], $seams['redis'], $seams['free']))->toBe([]);
});

it('flags inadequate upload limits', function (): void {
    $seams = postureSafeSeams();

    $joined = implode(' ', ProductionPostureChecks::violations(
        ['upload_max_filesize' => '2M', 'post_max_size' => '8M'],
        $seams['redis'],
        $seams['free']
    ));

    expect($joined)->toContain('upload_max_filesize')->and($joined)->toContain('post_max_size');
});

it('flags passwordless non-loopback redis', function (): void {
    $seams = postureSafeSeams();

    $joined = implode(' ', ProductionPostureChecks::violations(
        $seams['ini'],
        ['host' => 'redis.internal', 'port' => 6379, 'password' => null],
        $seams['free']
    ));

    expect($joined)->toContain('redis.internal');
});

it('flags insufficient free space', function (): void {
    $seams = postureSafeSeams();

    $joined = implode(' ', ProductionPostureChecks::violations($seams['ini'], $seams['redis'], 123));

    expect($joined)->toContain('Free space');
});

it('never throws outside the production environment', function (): void {
    ProductionPostureChecks::assertValid();

    expect(true)->toBeTrue();
});

it('fails fast in production on an unsafe posture', function (): void {
    $this->app->detectEnvironment(fn () => 'production');

    try {
        // Real dev-machine ini (2M/8M) guarantees at least the upload
        // findings here, which is exactly the fail-closed behavior.
        expect(fn () => ProductionPostureChecks::assertValid())
            ->toThrow(LogicException::class, 'Unsafe production posture (P7-001)');
    } finally {
        $this->app->detectEnvironment(fn () => 'testing');
    }
});
