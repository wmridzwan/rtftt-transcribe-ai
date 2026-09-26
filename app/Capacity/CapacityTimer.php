<?php

namespace App\Capacity;

/**
 * Deterministic wall-clock timer for the P7-009 capacity harness.
 *
 * Boundaries are explicit (`start()` / `stop()`) so every measured
 * workload has a reviewable start boundary, completion boundary, and
 * elapsed time. Uses `hrtime(true)` (monotonic nanoseconds) internally
 * and reports milliseconds as float.
 *
 * A timer that was never started, or never stopped, reports `null`
 * rather than a fabricated zero — a performance number without a real
 * measurement boundary must never be treated as run evidence.
 */
final class CapacityTimer
{
    private ?int $startedAt = null;

    private ?int $stoppedAt = null;

    public function start(): void
    {
        $this->startedAt = hrtime(true);
        $this->stoppedAt = null;
    }

    public function stop(): void
    {
        if ($this->startedAt === null) {
            return;
        }

        $this->stoppedAt = hrtime(true);
    }

    /**
     * Elapsed milliseconds between the start and completion boundaries,
     * or null when either boundary is missing.
     */
    public function elapsedMilliseconds(): ?float
    {
        if ($this->startedAt === null || $this->stoppedAt === null) {
            return null;
        }

        return ($this->stoppedAt - $this->startedAt) / 1_000_000;
    }

    public function hasCompletedBoundaries(): bool
    {
        return $this->startedAt !== null && $this->stoppedAt !== null;
    }
}
