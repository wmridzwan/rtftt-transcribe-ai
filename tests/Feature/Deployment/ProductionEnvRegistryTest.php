<?php

use App\Deployment\ProductionEnvRegistry;

/*
 * P7-001: central env-key registry. Every production-relevant key in
 * `.env.example` must be registered (or the test names the gap and fails);
 * shapes validate as specified.
 */

it('registers every active key in .env.example', function (): void {
    expect(ProductionEnvRegistry::unregisteredExampleKeys(base_path('.env.example')))->toBe([]);
});

it('validates url, port, bytes, secret, and connection shapes', function (): void {
    expect(ProductionEnvRegistry::validateValue('RTFTT_TRANSCRIPTION_WORKER_URL', 'http://worker.prod:8000'))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('RTFTT_TRANSCRIPTION_WORKER_URL', 'not-a-url'))->not->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('REDIS_PORT', 6379))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('REDIS_PORT', 70000))->not->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('REDIS_QUEUE_RETRY_AFTER', 420))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('REDIS_QUEUE_RETRY_AFTER', -1))->not->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('RTFTT_TRANSCRIPTION_WORKER_TOKEN', 'provisioned-secret'))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('RTFTT_TRANSCRIPTION_WORKER_TOKEN', ''))->not->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('QUEUE_CONNECTION', 'redis'))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('QUEUE_CONNECTION', ''))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('QUEUE_CONNECTION', 'has space'))->not->toBeNull();
});

it('rejects unknown keys until ownership is registered', function (): void {
    expect(ProductionEnvRegistry::validateValue('RTFTT_BRAND_NEW_UNOWNED_KEY', 'x'))->toContain('not registered');
});

it('keeps ClamAV and backup keys registered under their owning tasks', function (): void {
    $definitions = ProductionEnvRegistry::definitions();

    expect($definitions['CLAMAV_HOST']['owner'])->toBe('P7-006')
        ->and($definitions['RTFTT_BACKUP_TARGET']['owner'])->toBe('P7-007')
        ->and($definitions['REDIS_PASSWORD']['owner'])->toBe('P7-001');
});
