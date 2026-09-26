<?php

namespace App\Testing;

use Illuminate\Support\Facades\DB;

/**
 * Driver-aware lock-wait setup for genuine two-process race harnesses
 * (pre-Linux remediation, HIGH-C).
 *
 * SQLite serializes writers at the file level: racers must wait on the
 * write lock (`PRAGMA busy_timeout`) so the guarded UPDATE observes
 * committed state instead of erroring. PostgreSQL uses MVCC row locks
 * for the same CAS statements, so no driver-specific setup is required
 * there — and executing a SQLite PRAGMA on pgsql throws a PDO
 * exception, which previously crashed every race harness the moment it
 * pointed at PostgreSQL. Unknown drivers get no setup (fail-open is
 * wrong; the harness still proves whatever the driver does natively).
 */
final class RaceConnectionPolicy
{
    /**
     * Map a driver name to its lock-wait statement, or null when the
     * driver needs no setup. Pure mapping — unit-testable per driver
     * without a live server.
     */
    public static function lockWaitStatement(string $driver, int $sqliteBusyTimeoutMs): ?string
    {
        if ($driver === 'sqlite') {
            return sprintf('PRAGMA busy_timeout = %d', max(1, $sqliteBusyTimeoutMs));
        }

        return null;
    }

    /**
     * Apply the lock-wait setup for the current default connection.
     * Never throws for driver reasons: drivers without a statement
     * are simply left alone.
     */
    public static function applyLockWait(int $sqliteBusyTimeoutMs = 10000): void
    {
        $statement = self::lockWaitStatement(DB::getDriverName(), $sqliteBusyTimeoutMs);

        if ($statement !== null) {
            DB::statement($statement);
        }
    }
}
