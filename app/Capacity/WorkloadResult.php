<?php

namespace App\Capacity;

/**
 * A single measured workload repeat.
 *
 * Records the deterministic measurement boundaries (start/completion),
 * elapsed time, concurrency/load configuration, success/failure
 * outcome, repeat identity, and environment/run identity. Timing is
 * never reported without outcome correctness: `elapsed_ms` is null
 * unless the boundaries completed AND correctness was evaluated.
 *
 * Failed or partial repeats stay distinguishable: `failure_point`,
 * `failure_detail`, `completed_work`, and `incomplete_work` are
 * preserved, and a later successful repeat never rewrites them (each
 * repeat carries its own identity).
 */
final class WorkloadResult
{
    /**
     * @param  array<string, mixed>  $concurrency
     * @param  array<string, mixed>  $correctness
     */
    public function __construct(
        public readonly string $workload,
        public readonly int $repeat,
        public readonly string $runId,
        public readonly string $startedAt,
        public readonly ?string $completedAt,
        public readonly ?float $elapsedMs,
        public readonly array $concurrency,
        public readonly string $outcome,
        public readonly array $correctness = [],
        public readonly ?string $failurePoint = null,
        public readonly ?string $failureDetail = null,
        public readonly ?string $completedWork = null,
        public readonly ?string $incompleteWork = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'workload' => $this->workload,
            'repeat' => $this->repeat,
            'run_id' => $this->runId,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'elapsed_ms' => $this->elapsedMs,
            'concurrency' => $this->concurrency,
            'outcome' => $this->outcome,
            'correctness' => $this->correctness,
            'failure_point' => $this->failurePoint,
            'failure_detail' => $this->failureDetail,
            'completed_work' => $this->completedWork,
            'incomplete_work' => $this->incompleteWork,
        ];
    }
}
