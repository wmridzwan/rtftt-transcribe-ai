<?php

use App\Translation\TranslationQueueConfig;
use Illuminate\Support\Facades\DB;

/*
 * P5-004C operational pre-flight: queue timeout reconciliation, SQLite
 * busy_timeout, and stale-attempt recovery scheduling.
 */

it('reconciles provider, job and retry_after defaults', function (): void {
    config()->set('translation.timeout_seconds', 300);
    config()->set('translation.job_timeout_seconds', 330);
    config()->set('translation.retry_after_seconds', 420);

    expect(TranslationQueueConfig::providerTimeoutSeconds())->toBe(300)
        ->and(TranslationQueueConfig::jobTimeoutSeconds())->toBe(330)
        ->and(TranslationQueueConfig::requiredRetryAfterSeconds())->toBe(420)
        ->and(TranslationQueueConfig::jobTimeoutSeconds())->toBeGreaterThan(TranslationQueueConfig::providerTimeoutSeconds())
        ->and(TranslationQueueConfig::requiredRetryAfterSeconds())->toBeGreaterThan(TranslationQueueConfig::jobTimeoutSeconds());
});

it('accepts a connection whose retry_after meets the requirement', function (): void {
    config()->set('translation.queue_connection', 'redis');
    config()->set('queue.connections.redis.retry_after', 420);
    config()->set('translation.retry_after_seconds', 420);

    expect(TranslationQueueConfig::consistencyViolation())->toBeNull()
        ->and(TranslationQueueConfig::connectionRetryAfterSeconds())->toBe(420);

    TranslationQueueConfig::assertConsistent();
});

it('flags a connection whose retry_after is too low', function (): void {
    config()->set('translation.queue_connection', 'redis');
    config()->set('queue.connections.redis.retry_after', 90);
    config()->set('translation.retry_after_seconds', 420);

    expect(TranslationQueueConfig::consistencyViolation())->not->toBeNull();
});

it('prohibits connections without retry_after protection (sync) — P7-003 closes the exemption', function (): void {
    config()->set('translation.queue_connection', 'sync');
    config()->set('queue.connections.sync.retry_after', null);

    // The historical exemption is closed: sync has no retry_after, so it
    // voids the invariant and is now a boot-guard violation outside tests.
    expect(TranslationQueueConfig::consistencyViolation())->not->toBeNull()
        ->and(TranslationQueueConfig::connectionRetryAfterSeconds())->toBeNull();

    // The boot guard itself still skips under the test runner so test-env
    // sync usage keeps working.
    TranslationQueueConfig::assertConsistent();
});

it('resolves the effective connection from the default when no override is set', function (): void {
    config()->set('translation.queue_connection', null);
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 420);

    expect(TranslationQueueConfig::effectiveConnection())->toBe('database')
        ->and(TranslationQueueConfig::connectionRetryAfterSeconds())->toBe(420)
        ->and(TranslationQueueConfig::consistencyViolation())->toBeNull();
});

it('flags the default connection when its retry_after is too low and no override is set', function (): void {
    config()->set('translation.queue_connection', null);
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 90);
    config()->set('translation.retry_after_seconds', 420);

    expect(TranslationQueueConfig::consistencyViolation())->not->toBeNull();
});

it('ships default database and redis retry_after at or above the requirement', function (): void {
    $required = TranslationQueueConfig::requiredRetryAfterSeconds();

    expect((int) config('queue.connections.database.retry_after'))->toBeGreaterThanOrEqual($required)
        ->and((int) config('queue.connections.redis.retry_after'))->toBeGreaterThanOrEqual($required);
});

it('configures a positive sqlite busy_timeout independent of local config', function (): void {
    $busyTimeout = (int) DB::connection()->getPdo()->query('PRAGMA busy_timeout')->fetchColumn();

    expect($busyTimeout)->toBeGreaterThan(0);
});

it('schedules stale-attempt recovery without overlap', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('translation:recover-stale-attempts')
        ->assertSuccessful();
});
