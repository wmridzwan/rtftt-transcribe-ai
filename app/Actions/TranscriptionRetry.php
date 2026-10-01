<?php

namespace App\Actions;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
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
 * provides defense-in-depth. `lockForUpdate()` is never used as the guarantee
 * for the CAS itself.
 *
 * P7-009-CORR-01 corrective cycle 1 (F-2): retry additionally takes the parent
 * `media_files` row lock (the same serialization point Start uses) and refuses
 * when another non-terminal transcription exists for the media file, so retry
 * cannot create competing active work.
 *
 * PostgreSQL (target store, READ COMMITTED; SQLite cannot exercise the row
 * lock because `lockForUpdate()` is a no-op there): the lock is taken after
 * the CAS write, so lock order is transcription row → media row. Nothing
 * takes the media lock and then waits on a transcription row (Start and
 * `RetentionPurge` hold only the media row), so no lock cycle exists.
 *   - Start ∥ Retry: Start holds the media lock and creates a Draft
 *     transcription; Retry blocks on the media lock, and after Start commits
 *     its next statement sees the Draft, so it throws and the CAS and new
 *     attempt roll back. If Retry commits first, Start sees the now-Queued
 *     transcription and reuses it. Either order ends with one active one.
 *   - Retry ∥ Retry on two Failed transcriptions of one media: the first to
 *     take the media lock commits; the second then sees it and refuses.
 *   - Retry ∥ Retry on the same transcription: the second CAS matches zero
 *     rows and converges on the winner's attempt (unchanged behaviour).
 * Taking the media lock before the CAS would also be correct on PostgreSQL but
 * puts a read ahead of the first write, which fails with a SQLite snapshot-
 * upgrade BUSY in `TranscriptionRetryConcurrencyTest`; the order is deliberate.
 */
class TranscriptionRetry
{
    /** @var list<TranscriptionStatus> */
    private const ACTIVE_STATUSES = [
        TranscriptionStatus::Draft,
        TranscriptionStatus::Queued,
        TranscriptionStatus::Preparing,
        TranscriptionStatus::Transcribing,
    ];

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
     * P7-009-CORR-01 corrective cycle 1 (F-2): whether a *different*
     * non-terminal transcription already exists for the same media file, which
     * blocks retry until it reaches a terminal state. The retrying
     * transcription itself is Failed (terminal) so it never matches; the id
     * exclusion is defense-in-depth. Eligibility stays failure-based; this is
     * the separate "blocked by active work" state (best-effort when read
     * outside `retry()`, authoritative inside it under the media row lock).
     */
    public function isBlockedByActiveTranscription(Transcription $transcription): bool
    {
        return Transcription::query()
            ->where('media_file_id', $transcription->media_file_id)
            ->whereKeyNot($transcription->getKey())
            ->whereIn('status', array_map(
                static fn (TranscriptionStatus $status): string => $status->value,
                self::ACTIVE_STATUSES,
            ))
            ->exists();
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

        if ($this->isBlockedByActiveTranscription($transcription)) {
            throw $this->blockedByActiveTranscription();
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

            $newAttempt = ProcessingJob::query()->create([
                'transcription_id' => $transcription->getKey(),
                'worker_name' => null,
                'stage' => ProcessingStage::Transcribe,
                'status' => ProcessingStatus::Queued,
                'progress_percentage' => 0,
                'failure_code' => null,
            ]);

            // P7-009-CORR-01 corrective cycle 1 (F-2): the authoritative
            // active-work guard, atomic with the CAS above (see class docblock
            // for the PostgreSQL interleavings). Refusing throws, which rolls
            // back the CAS and the new attempt together.
            MediaFile::query()
                ->whereKey($transcription->media_file_id)
                ->lockForUpdate()
                ->first();

            if ($this->isBlockedByActiveTranscription($transcription)) {
                throw $this->blockedByActiveTranscription();
            }

            return $newAttempt;
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

    private function blockedByActiveTranscription(): TranscriptionException
    {
        return new TranscriptionException(
            TranscriptionFailure::ProcessingFailed,
            'Another transcription is already active for this media file.',
        );
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
