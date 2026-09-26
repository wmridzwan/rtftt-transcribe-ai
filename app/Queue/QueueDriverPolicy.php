<?php

namespace App\Queue;

/**
 * Production queue-driver policy (P7-003, D7-02).
 *
 * The `sync` and `null` drivers silently void the provider < job <
 * retry_after timeout invariant (no `retry_after` exists on those drivers)
 * and run long transcription/translation jobs inline. They are forbidden
 * for the transcription/translation queues in any non-test environment.
 * Test environments keep working because the boot guard
 * (`assertConsistent()`) is skipped under the test runner; only the pure
 * violation logic reports here.
 */
final class QueueDriverPolicy
{
    /**
     * Drivers that must never back the transcription/translation queues
     * outside tests.
     *
     * @var list<string>
     */
    public const PROHIBITED_DRIVERS = ['sync', 'null'];

    public static function driverOf(?string $connection): ?string
    {
        if ($connection === null || $connection === '') {
            return null;
        }

        $driver = config("queue.connections.{$connection}.driver");

        return is_string($driver) && $driver !== '' ? $driver : null;
    }

    public static function isProhibited(?string $connection): bool
    {
        // Match by connection name as well as resolved driver: the `null`
        // driver may be selected by name without a configured connection
        // entry, and it is equally unprotected (no retry_after).
        return in_array($connection, self::PROHIBITED_DRIVERS, true)
            || in_array(self::driverOf($connection), self::PROHIBITED_DRIVERS, true);
    }

    /**
     * @return string|null a human-readable violation, or null when allowed
     */
    public static function prohibitedDriverViolation(?string $connection, string $queueLabel): ?string
    {
        if ($connection === null) {
            return null;
        }

        if (! self::isProhibited($connection)) {
            return null;
        }

        return sprintf(
            '%s queue connection "%s" uses the "%s" driver, which is prohibited outside tests; '
            .'a running job would have no retry_after protection and long jobs would run inline.',
            $queueLabel,
            $connection,
            (string) (self::driverOf($connection) ?? $connection),
        );
    }
}
