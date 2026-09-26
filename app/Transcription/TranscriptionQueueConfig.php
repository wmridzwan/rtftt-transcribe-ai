<?php

namespace App\Transcription;

use App\Queue\QueueDriverPolicy;
use LogicException;

/**
 * Reconciles the transcription provider timeout, the per-job worker
 * timeout, and the queue connection `retry_after` (P7-003).
 *
 * Mirrors `App\Translation\TranslationQueueConfig` (P5-004C) for the
 * transcription queue, which previously had no equivalent guard.
 *
 * Invariant: provider timeout < job timeout < queue connection retry_after,
 * so a running transcription is never re-delivered while it is still
 * executing. The `sync`/`null` drivers are prohibited for this queue
 * outside tests (see `QueueDriverPolicy`).
 */
final class TranscriptionQueueConfig
{
    public static function providerTimeoutSeconds(): int
    {
        return max(1, (int) config('transcription.timeout_seconds', 300));
    }

    public static function jobTimeoutSeconds(): int
    {
        return max(
            self::providerTimeoutSeconds() + 1,
            (int) config('transcription.job_timeout_seconds', 330),
        );
    }

    public static function requiredRetryAfterSeconds(): int
    {
        return max(
            self::jobTimeoutSeconds() + 1,
            (int) config('transcription.retry_after_seconds', 420),
        );
    }

    public static function configuredConnection(): ?string
    {
        $connection = config('transcription.queue_connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    /**
     * The connection the orchestrator will actually use: the explicit
     * transcription override when set, otherwise the application default
     * queue connection (mirroring `TranscriptionOrchestrator::dispatch()`).
     */
    public static function effectiveConnection(): ?string
    {
        $configured = self::configuredConnection();

        if ($configured !== null) {
            return $configured;
        }

        $default = config('queue.default');

        return is_string($default) && $default !== '' ? $default : null;
    }

    public static function connectionRetryAfterSeconds(?string $connection = null): ?int
    {
        $connection ??= self::effectiveConnection();

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
        $connection = self::effectiveConnection();

        if ($connection === null) {
            return null;
        }

        $prohibited = QueueDriverPolicy::prohibitedDriverViolation($connection, 'Transcription');

        if ($prohibited !== null) {
            return $prohibited;
        }

        $retryAfter = self::connectionRetryAfterSeconds($connection);

        if ($retryAfter === null) {
            return null;
        }

        if ($retryAfter < self::requiredRetryAfterSeconds()) {
            return sprintf(
                'Transcription queue connection "%s" retry_after (%d) is below the required %d; '
                .'a running transcription could be re-delivered.',
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
     * @throws LogicException when the effective connection is prohibited or inconsistent
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
