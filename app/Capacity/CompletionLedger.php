<?php

namespace App\Capacity;

/**
 * Explicit completion accounting for P7-009 runs.
 *
 * Addresses the readiness-review LOW finding: every workload/run
 * captures dispatched / completed / failed / skipped counts, and the
 * counts must reconcile (dispatched == completed + failed +
 * skipped). A performance number without matching completion
 * accounting must not be treated as valid run evidence.
 *
 * This is an implementation-quality correction inside the existing
 * contract, not a new product policy.
 */
final class CompletionLedger
{
    /**
     * @var array<string, array{dispatched: int, completed: int, failed: int, skipped: int}>
     */
    private array $entries = [];

    public function record(string $workload, int $dispatched, int $completed, int $failed, int $skipped): void
    {
        $this->entries[$workload] = [
            'dispatched' => $dispatched,
            'completed' => $completed,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    public function increment(string $workload, string $outcome): void
    {
        if (! isset($this->entries[$workload])) {
            $this->entries[$workload] = ['dispatched' => 0, 'completed' => 0, 'failed' => 0, 'skipped' => 0];
        }

        if ($outcome === 'completed') {
            $this->entries[$workload]['dispatched']++;
            $this->entries[$workload]['completed']++;
        } elseif ($outcome === 'failed') {
            $this->entries[$workload]['dispatched']++;
            $this->entries[$workload]['failed']++;
        } elseif ($outcome === 'skipped') {
            $this->entries[$workload]['dispatched']++;
            $this->entries[$workload]['skipped']++;
        }
    }

    /**
     * Every recorded workload must satisfy
     * dispatched == completed + failed + skipped.
     *
     * @return array{reconciled: bool, mismatches: list<string>}
     */
    public function reconcile(): array
    {
        $mismatches = [];

        foreach ($this->entries as $workload => $counts) {
            if ($counts['dispatched'] !== $counts['completed'] + $counts['failed'] + $counts['skipped']) {
                $mismatches[] = $workload;
            }
        }

        return ['reconciled' => $mismatches === [], 'mismatches' => $mismatches];
    }

    /**
     * @return array<string, array{dispatched: int, completed: int, failed: int, skipped: int, reconciled: bool}>
     */
    public function toArray(): array
    {
        $out = [];

        foreach ($this->entries as $workload => $counts) {
            $out[$workload] = $counts + [
                'reconciled' => $counts['dispatched'] === $counts['completed'] + $counts['failed'] + $counts['skipped'],
            ];
        }

        return $out;
    }
}
