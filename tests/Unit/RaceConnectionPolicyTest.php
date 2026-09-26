<?php

use App\Testing\RaceConnectionPolicy;

/*
 * Pre-Linux remediation (HIGH-C): race-harness lock-wait setup is
 * driver-aware. SQLite racers wait via PRAGMA busy_timeout (behavior
 * preserved exactly); PostgreSQL and unknown drivers receive no
 * statement (a SQLite PRAGMA throws a PDO exception on pgsql).
 */

it('maps sqlite to a PRAGMA busy_timeout statement', function (): void {
    expect(RaceConnectionPolicy::lockWaitStatement('sqlite', 10000))
        ->toBe('PRAGMA busy_timeout = 10000')
        ->and(RaceConnectionPolicy::lockWaitStatement('sqlite', 15000))
        ->toBe('PRAGMA busy_timeout = 15000');
});

it('maps pgsql and unknown drivers to no statement', function (): void {
    expect(RaceConnectionPolicy::lockWaitStatement('pgsql', 10000))->toBeNull()
        ->and(RaceConnectionPolicy::lockWaitStatement('mysql', 10000))->toBeNull()
        ->and(RaceConnectionPolicy::lockWaitStatement('sqlsrv', 10000))->toBeNull();
});

it('clamps non-positive sqlite timeouts to a minimum wait', function (): void {
    expect(RaceConnectionPolicy::lockWaitStatement('sqlite', 0))
        ->toBe('PRAGMA busy_timeout = 1')
        ->and(RaceConnectionPolicy::lockWaitStatement('sqlite', -5))
        ->toBe('PRAGMA busy_timeout = 1');
});

it('exposes the mapping used by the live-connection apply step', function (): void {
    // applyLockWait() itself needs a booted connection (covered in the
    // Feature preflight); the mapping above is its full contract.
    expect(RaceConnectionPolicy::lockWaitStatement('sqlite', 10000))->toBeString()
        ->and(RaceConnectionPolicy::lockWaitStatement('pgsql', 10000))->toBeNull();
});
