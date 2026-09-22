<?php

namespace App\Actions;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

/**
 * P3-007 manual domain retry (ADR-018).
 *
 * Retry preserves the same logical transcription and creates a new
 * ProcessingJob attempt. It is manual-only: there is no automatic retry
 * scheduler or backoff. The previous failed attempt is never reused or reset.
 *
 * Correctness boundary: a guarded compare-and-set moves the transcription
 * `failed → queued`; the surrounding transaction makes the transition and the
 * new attempt a single atomic unit. A partial unique index over active attempts
 * provides defense-in-depth. `lockForUpdate()` is never used as the guarantee.
 */
class TranscriptionRetry
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly TranscriptionOrchestrator $orchestrator,
    ) {}

    /**
     * Whether the transcription may be manually retried. Retryability is derived
     * from the authoritative provider-neutral TranscriptionFailure taxonomy of
     * the latest processing attempt, never from worker-supplied metadata.
     */
    public function isEligible(Transcription $transcription): bool
    {
        if ($transcription->status !== TranscriptionStatus::Failed) {
            return false;
        }

        $latest = $this->latestAttempt($transcription);

        if ($latest === null || $latest->status !== ProcessingStatus::Failed) {
            return false;
        }

        return $latest->failure_code?->isRetryable() ?? false;
    }

    /**
     * @throws TranscriptionException when the transcription is not retry-eligible
     */
    public function retry(Transcription $transcription): ProcessingJob
    {
        $mediaFile = $transcription->mediaFile()->first();

        if ($mediaFile === null || $mediaFile->user_id !== $transcription->user_id) {
            throw new TranscriptionException(
                TranscriptionFailure::ProcessingFailed,
                'The transcription ownership relationship is inconsistent.',
            );
        }

        // Idempotent: if an active attempt already exists, return it unchanged.
        $active = $this->activeAttempt($transcription);

        if ($active !== null) {
            return $active;
        }

        if (! $this->isEligible($transcription)) {
            throw new TranscriptionException(
                TranscriptionFailure::ProcessingFailed,
                'The transcription is not retry-eligible.',
            );
        }

        $attempt = $this->database->transaction(function () use ($transcription): ?ProcessingJob {
            $won = Transcription::query()
                ->whereKey($transcription->getKey())
                ->where('status', TranscriptionStatus::Failed->value)
                ->update([
                    'status' => TranscriptionStatus::Queued->value,
                    'completed_at' => null,
                    'error_message' => null,
                ]);

            if ($won === 0) {
                return null;
            }

            return ProcessingJob::query()->create([
                'transcription_id' => $transcription->getKey(),
                'worker_name' => null,
                'stage' => ProcessingStage::Transcribe,
                'status' => ProcessingStatus::Queued,
                'progress_percentage' => 0,
                'failure_code' => null,
            ]);
        });

        if ($attempt === null) {
            // A concurrent retry won the CAS; return its authoritative attempt.
            $active = $this->activeAttempt($transcription);

            if ($active !== null) {
                return $active;
            }

            throw new TranscriptionException(
                TranscriptionFailure::ProcessingFailed,
                'The transcription is not retry-eligible.',
            );
        }

        $this->orchestrator->dispatch($attempt);

        Log::info('Transcription retry queued.', [
            'transcription_id' => $transcription->getKey(),
            'processing_attempt_id' => $attempt->getKey(),
        ]);

        return $attempt;
    }

    public function latestAttempt(Transcription $transcription): ?ProcessingJob
    {
        return ProcessingJob::query()
            ->where('transcription_id', $transcription->getKey())
            ->orderByDesc('id')
            ->first();
    }

    private function activeAttempt(Transcription $transcription): ?ProcessingJob
    {
        return ProcessingJob::query()
            ->where('transcription_id', $transcription->getKey())
            ->whereIn('status', [
                ProcessingStatus::Queued->value,
                ProcessingStatus::Running->value,
            ])
            ->orderByDesc('id')
            ->first();
    }
}
