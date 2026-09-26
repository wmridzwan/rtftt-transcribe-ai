<?php

use App\Deployment\RedisPosture;

/*
 * P7-001: Redis posture record + certification (TD-004 / HPO-04).
 * Loopback without a password is recorded, never a violation; any
 * non-loopback host without a password fails closed. Live capture is
 * covered by a skip-if-unreachable test.
 */

it('records loopback without password as acceptable dev posture', function (): void {
    $record = RedisPosture::record(['host' => '127.0.0.1', 'port' => 6379, 'password' => null]);

    expect($record['loopback'])->toBeTrue()
        ->and($record['password_set'])->toBeFalse()
        ->and($record['violations'])->toBe([]);
});

it('flags passwordless non-loopback redis in every environment', function (): void {
    $record = RedisPosture::record(['host' => '10.0.0.5', 'port' => 6379, 'password' => null]);

    expect($record['loopback'])->toBeFalse()
        ->and(implode(' ', $record['violations']))->toContain('10.0.0.5');
});

it('accepts authenticated non-loopback redis', function (): void {
    $record = RedisPosture::record(['host' => '10.0.0.5', 'port' => 6379, 'password' => 'provisioned']);

    expect($record['password_set'])->toBeTrue()->and($record['violations'])->toBe([]);
});

it('captures the live server version when redis is reachable', function (): void {
    try {
        $pong = app('redis')->connection('default')->ping();
        $reachable = $pong === true || $pong === 'PONG' || $pong === 1;
    } catch (Throwable) {
        $reachable = false;
    }

    if (! $reachable) {
        test()->markTestSkipped('Redis unreachable at 127.0.0.1:6379; version capture needs a live server.');
    }

    $version = RedisPosture::captureVersion();

    expect($version)->toBeString()->and($version)->toMatch('/^\d+\.\d+/');
});
