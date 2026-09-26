<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Deterministic probe job for the P7-009 capacity harness.
 *
 * Performs a small, fixed CPU slice (repeated SHA-256 over the token)
 * and records a completion marker in the cache. The harness dispatches
 * batches of these jobs on the configured queue driver and verifies
 * the markers — proving dispatch → execution → completion accounting
 * on whatever driver is available. True broker concurrency is a
 * target-environment measurement; rehearsal scale stays tiny.
 */
class CapacityProbeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $runId,
        public readonly string $token,
        public readonly int $iterations = 200,
    ) {}

    public function handle(): void
    {
        $digest = $this->token;

        for ($i = 0; $i < $this->iterations; $i++) {
            $digest = hash('sha256', $digest.$i);
        }

        Cache::put($this->markerKey(), $digest, 600);
    }

    public function markerKey(): string
    {
        return 'capacity-probe:'.$this->runId.':'.$this->token;
    }

    public static function expectedMarker(string $token, int $iterations = 200): string
    {
        $digest = $token;

        for ($i = 0; $i < $iterations; $i++) {
            $digest = hash('sha256', $digest.$i);
        }

        return $digest;
    }
}
