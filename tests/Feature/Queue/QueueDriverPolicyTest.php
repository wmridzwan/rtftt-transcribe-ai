<?php

use App\Queue\QueueDriverPolicy;

/*
 * P7-003: prohibited queue-driver policy.
 */

it('prohibits the sync and null drivers', function (): void {
    expect(QueueDriverPolicy::isProhibited('sync'))->toBeTrue()
        ->and(QueueDriverPolicy::isProhibited('null'))->toBeTrue();
});

it('allows the database and redis drivers', function (): void {
    expect(QueueDriverPolicy::isProhibited('database'))->toBeFalse()
        ->and(QueueDriverPolicy::isProhibited('redis'))->toBeFalse();
});

it('reports a violation naming the queue and driver', function (): void {
    config()->set('queue.connections.sync.driver', 'sync');

    $violation = QueueDriverPolicy::prohibitedDriverViolation('sync', 'Transcription');

    expect($violation)->not->toBeNull()
        ->and($violation)->toContain('Transcription')
        ->and($violation)->toContain('sync');
});

it('reports no violation for allowed or unknown connections', function (): void {
    expect(QueueDriverPolicy::prohibitedDriverViolation('redis', 'Translation'))->toBeNull()
        ->and(QueueDriverPolicy::prohibitedDriverViolation(null, 'Translation'))->toBeNull();
});
