<?php

namespace App\Actions;

use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Transcription\TranscriptionFailure;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * P3-007 abandoned running-attempt recovery (ADR-018 / B3-05).
 *
 * A demonstrably stale `running` attempt is moved to a terminal recoverable
 * failure state, after which the transcription becomes eligible for explicit
 * manual retry. Recovery never starts a new inference attempt automatically.
 *
 * Stale authority: the attempt is failed with a guarded compare-and-set
 * (`running → failed`) so a worker that already completed the attempt is never
 * overwritten. The transcription is failed only while it is still in a
 * processing state and only when this attempt is the authoritative latest
 * attempt; an obsolete attempt is failed without touching the transcription.
 */
class StaleTranscriptionAttemptRecovery
{
    /**
     * Derive the stale threshold from the canonical provider execution timeout
     * plus an explicit 60-second safety margin, unless overridden by config.
     */
    public function staleThresholdSeconds(): int
    {
        $configured = config('transcription.attempt_stale_seconds');

        if (is_numeric($configured)) {
            return max(1, (int) $configured);
        }

        return max(1, (int) config('transcription.timeout_seconds', 300) + 60);
    }

    /**
     * @return int number of authoritative stale attempts recovered
     */
    public function recover(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $cutoff = $now->copy()->subSeconds($this->staleThresholdSeconds());

        $stale = ProcessingJob::query()
            ->where('status', ProcessingStatus::Running->value)
            ->whereNotNull('started_at')
            ->where('started_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        $recovered = 0;

        foreach ($stale as $attempt) {
            $failed = $this->failAttempt($attempt, $now);

            if (! $failed) {
                // Another writer completed or already recovered the attempt.
                continue;
            }

            if ($this->hasNewerAttempt($attempt)) {
                // Obsolete running attempt: terminalize it, but the newer
                // attempt owns the transcription.
                continue;
            }

            Transcription::query()
                ->whereKey($attempt->transcription_id)
                ->whereIn('status', [
                    TranscriptionStatus::Queued->value,
                    TranscriptionStatus::Preparing->value,
                    TranscriptionStatus::Transcribing->value,
                ])
                ->update([
                    'status' => TranscriptionStatus::Failed->value,
                    'completed_at' => $now,
                    'error_message' => 'The transcription attempt was abandoned.',
                ]);

            $recovered++;
        }

        if ($recovered > 0) {
            Log::warning('Recovered stale transcription attempts.', ['count' => $recovered]);
        }

        return $recovered;
    }

    private function failAttempt(ProcessingJob $attempt, CarbonInterface $now): bool
    {
        $won = ProcessingJob::query()
            ->whereKey($attempt->getKey())
            ->where('status', ProcessingStatus::Running->value)
            ->update([
                'status' => ProcessingStatus::Failed->value,
                'completed_at' => $now,
                'error_message' => 'The transcription attempt was abandoned.',
                'failure_code' => TranscriptionFailure::WorkerTimeout->value,
            ]);

        return $won === 1;
    }

    private function hasNewerAttempt(ProcessingJob $attempt): bool
    {
        return ProcessingJob::query()
            ->where('transcription_id', $attempt->transcription_id)
            ->where('id', '>', $attempt->getKey())
            ->exists();
    }
}
