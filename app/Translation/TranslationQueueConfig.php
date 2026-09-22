<?php

namespace App\Translation;

use LogicException;

/**
 * Reconciles the translation provider timeout, the per-job worker timeout, and
 * the queue connection `retry_after` (P5-004C).
 *
 * Invariant: provider timeout < job timeout < queue connection retry_after, so
 * a running translation is never re-delivered while it is still executing.
 */
final class TranslationQueueConfig
{
    public static function providerTimeoutSeconds(): int
    {
        return max(1, (int) config('translation.timeout_seconds', 300));
    }

    public static function jobTimeoutSeconds(): int
    {
        return max(
            self::providerTimeoutSeconds() + 1,
            (int) config('translation.job_timeout_seconds', 330),
        );
    }

    public static function requiredRetryAfterSeconds(): int
    {
        return max(
            self::jobTimeoutSeconds() + 1,
            (int) config('translation.retry_after_seconds', 420),
        );
    }

    public static function configuredConnection(): ?string
    {
        $connection = config('translation.queue_connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    public static function connectionRetryAfterSeconds(?string $connection = null): ?int
    {
        $connection ??= self::configuredConnection();

        if ($connection === null) {
            return null;
        }

        $retryAfter = config("queue.connections.{$connection}.retry_after");

        return is_numeric($retryAfter) ? (int) $retryAfter : null;
    }

    /**
     * @return string|null a human-readable violation, or null when consistent
     */
    public static function consistencyViolation(): ?string
    {
        $connection = self::configuredConnection();

        if ($connection === null) {
            return null;
        }

        $retryAfter = self::connectionRetryAfterSeconds($connection);

        // Drivers without retry_after (sync/null) are exempt.
        if ($retryAfter === null) {
            return null;
        }

        if ($retryAfter < self::requiredRetryAfterSeconds()) {
            return sprintf(
                'Translation queue connection "%s" retry_after (%d) is below the required %d; '
                .'a running translation could be re-delivered.',
                $connection,
                $retryAfter,
                self::requiredRetryAfterSeconds(),
            );
        }

        return null;
    }

    /**
     * Boot-time guard for non-test runtimes. Skipped under the test runner so
     * unrelated CLI/test child processes are never affected; the pure
     * consistency logic is covered directly by tests.
     *
     * @throws LogicException when the configured connection retry_after is too low
     */
    public static function assertConsistent(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $violation = self::consistencyViolation();

        if ($violation !== null) {
            throw new LogicException($violation);
        }
    }
}
