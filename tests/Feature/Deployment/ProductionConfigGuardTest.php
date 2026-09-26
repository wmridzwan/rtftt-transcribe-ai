<?php

use App\Deployment\ProductionConfigGuard;

/*
 * P7-008: production env-matrix guard (pure logic; enforced at boot only
 * in the production environment).
 */

function productionSafeBaseline(): void
{
    config()->set('app.debug', false);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('queue.default', 'redis');
    config()->set('transcription.worker_url', 'http://worker.prod:8000');
    config()->set('translation.worker_url', 'http://worker.prod:8000');
    config()->set('transcription.worker_token', 'secret-transcription');
    config()->set('translation.worker_token', 'secret-translation');
}

it('reports no violations for a safe production matrix', function (): void {
    productionSafeBaseline();

    expect(ProductionConfigGuard::violations())->toBe([]);
});

it('flags debug mode in production', function (): void {
    productionSafeBaseline();
    config()->set('app.debug', true);

    expect(ProductionConfigGuard::violations())->not->toBeEmpty();
});

it('flags a non-redis queue default', function (): void {
    productionSafeBaseline();
    config()->set('queue.default', 'database');

    $violations = ProductionConfigGuard::violations();

    expect(implode(' ', $violations))->toContain('QUEUE_CONNECTION');
});

it('flags a missing application key', function (): void {
    productionSafeBaseline();
    config()->set('app.key', '');

    expect(ProductionConfigGuard::violations())->not->toBeEmpty();
});

it('flags localhost worker urls and missing worker tokens', function (): void {
    productionSafeBaseline();
    config()->set('transcription.worker_url', 'http://localhost:8000');
    config()->set('translation.worker_token', '');

    $joined = implode(' ', ProductionConfigGuard::violations());

    expect($joined)->toContain('transcription.worker_url')
        ->and($joined)->toContain('translation.worker_token');
});

it('does not throw outside the production environment', function (): void {
    config()->set('app.debug', true);
    config()->set('queue.default', 'database');

    ProductionConfigGuard::assertValid();

    expect(true)->toBeTrue();
});
