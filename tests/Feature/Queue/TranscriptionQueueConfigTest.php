<?php

use App\Jobs\ProcessTranscription;
use App\Transcription\TranscriptionQueueConfig;

/*
 * P7-003: transcription queue timeout reconciliation, prohibited-driver
 * guard, and stale-recovery scheduling — mirroring the P5-004C translation
 * preflight.
 */

it('reconciles provider, job and retry_after defaults', function (): void {
    config()->set('transcription.timeout_seconds', 300);
    config()->set('transcription.job_timeout_seconds', 330);
    config()->set('transcription.retry_after_seconds', 420);

    expect(TranscriptionQueueConfig::providerTimeoutSeconds())->toBe(300)
        ->and(TranscriptionQueueConfig::jobTimeoutSeconds())->toBe(330)
        ->and(TranscriptionQueueConfig::requiredRetryAfterSeconds())->toBe(420)
        ->and(TranscriptionQueueConfig::jobTimeoutSeconds())->toBeGreaterThan(TranscriptionQueueConfig::providerTimeoutSeconds())
        ->and(TranscriptionQueueConfig::requiredRetryAfterSeconds())->toBeGreaterThan(TranscriptionQueueConfig::jobTimeoutSeconds());
});

it('accepts a connection whose retry_after meets the requirement', function (): void {
    config()->set('transcription.queue_connection', 'redis');
    config()->set('queue.connections.redis.retry_after', 420);
    config()->set('transcription.retry_after_seconds', 420);

    expect(TranscriptionQueueConfig::consistencyViolation())->toBeNull()
        ->and(TranscriptionQueueConfig::connectionRetryAfterSeconds())->toBe(420);

    TranscriptionQueueConfig::assertConsistent();
});

it('flags a connection whose retry_after is too low', function (): void {
    config()->set('transcription.queue_connection', 'redis');
    config()->set('queue.connections.redis.retry_after', 90);
    config()->set('transcription.retry_after_seconds', 420);

    expect(TranscriptionQueueConfig::consistencyViolation())->not->toBeNull();
});

it('prohibits the sync driver outside tests', function (): void {
    config()->set('transcription.queue_connection', 'sync');

    expect(TranscriptionQueueConfig::consistencyViolation())->not->toBeNull();

    TranscriptionQueueConfig::assertConsistent();
});

it('resolves the effective connection from the default when no override is set', function (): void {
    config()->set('transcription.queue_connection', null);
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 420);

    expect(TranscriptionQueueConfig::effectiveConnection())->toBe('database')
        ->and(TranscriptionQueueConfig::connectionRetryAfterSeconds())->toBe(420)
        ->and(TranscriptionQueueConfig::consistencyViolation())->toBeNull();
});

it('ships default database and redis retry_after at or above the requirement', function (): void {
    $required = TranscriptionQueueConfig::requiredRetryAfterSeconds();

    expect((int) config('queue.connections.database.retry_after'))->toBeGreaterThanOrEqual($required)
        ->and((int) config('queue.connections.redis.retry_after'))->toBeGreaterThanOrEqual($required);
});

it('derives the transcription job timeout below the required retry_after', function (): void {
    $job = new ProcessTranscription(transcriptionId: 1, processingAttemptId: 1);

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(TranscriptionQueueConfig::jobTimeoutSeconds())
        ->and($job->timeout)->toBeLessThan(TranscriptionQueueConfig::requiredRetryAfterSeconds());
});

it('schedules transcription stale-attempt recovery without overlap', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('transcription:recover-stale-attempts')
        ->assertSuccessful();
});

it('reports the transcription queue section in diagnostics', function (): void {
    // P7-003 closed the sync exemption: override the prohibited `sync`
    // test default with an allowed driver for the ok-path assertion.
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.retry_after', 420);

    $this->artisan('observability:diagnostics')
        ->expectsOutputToContain('Transcription queue:')
        ->expectsOutputToContain('Transcription effective connection:')
        ->expectsOutputToContain('Transcription timeout consistency: ok')
        ->expectsOutputToContain('transcription:recover-stale-attempts (every minute, without overlapping)')
        ->assertSuccessful();
});
