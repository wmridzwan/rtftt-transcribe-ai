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

it('exempts connections without a retry_after setting', function (): void {
    config()->set('translation.queue_connection', 'sync');
    config()->set('queue.connections.sync.retry_after', null);

    expect(TranslationQueueConfig::consistencyViolation())->toBeNull()
        ->and(TranslationQueueConfig::connectionRetryAfterSeconds())->toBeNull();

    TranslationQueueConfig::assertConsistent();
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
