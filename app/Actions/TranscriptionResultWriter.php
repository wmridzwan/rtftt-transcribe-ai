<?php

namespace App\Actions;

use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Transcription\TranscriptSegmentData;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

/**
 * Persists a normalized provider result into the transcription domain.
 *
 * P3-004: transcript-level semantic result.
 * P3-005: segment set + atomic completion boundary.
 *
 * Atomic completion contract: the transcript semantic fields, the complete
 * segment set, the processing attempt terminal state, and the transcription
 * Completed status all commit inside one short database transaction. A
 * failure at any point rolls the whole boundary back, so Completed is never
 * visible with incomplete semantic data.
 */
class TranscriptionResultWriter
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @throws TranscriptionException when the result cannot be persisted safely
     */
    public function persist(
        Transcription $transcription,
        ProcessingJob $attempt,
        NormalizedTranscript $result,
        string $model,
    ): void {
        if ($attempt->transcription_id !== $transcription->getKey()) {
            throw new TranscriptionException(
                TranscriptionFailure::PersistenceFailed,
                'The processing attempt does not belong to the transcription.',
            );
        }

        $this->assertOwnershipIntegrity($transcription);
        $this->assertSegmentsValid($result);

        $this->database->transaction(function () use ($transcription, $attempt, $result, $model): void {
            $locked = Transcription::query()
                ->whereKey($transcription->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                throw new TranscriptionException(
                    TranscriptionFailure::PersistenceFailed,
                    'The transcription no longer exists.',
                );
            }

            if ($locked->status === TranscriptionStatus::Completed) {
                return;
            }

            if (in_array($locked->status, [TranscriptionStatus::Failed, TranscriptionStatus::Cancelled], true)) {
                return;
            }

            // P3-007 stale-authority guard: a recovered (terminal) attempt, or an
            // attempt superseded by a newer one, must never persist a result.
            // This prevents an obsolete worker that resumes after stale recovery
            // or a manual retry from overwriting newer authoritative state.
            $authoritativeAttempt = ProcessingJob::query()
                ->whereKey($attempt->getKey())
                ->first();

            if ($authoritativeAttempt === null
                || in_array($authoritativeAttempt->status, [ProcessingStatus::Failed, ProcessingStatus::Cancelled], true)
                || $this->hasNewerAttempt($locked, $authoritativeAttempt)) {
                return;
            }

            $startedAt = $locked->started_at ?? now();
            $completedAt = now();
            // Carbon 3 returns fractional seconds; the processing_seconds columns are
            // integer, and PostgreSQL rejects a fractional value (P7-009-CORR-01).
            $processingSeconds = max(0, (int) round($completedAt->diffInSeconds($startedAt, true)));

            $locked->forceFill([
                'full_text' => $result->text,
                'detected_language' => $result->detectedLanguage->value,
                'speech_detected' => $result->speechDetected,
                'model' => $model,
                'started_at' => $startedAt,
            ])->save();

            $this->replaceSegments($locked, $result->segments);

            $locked->forceFill([
                'status' => TranscriptionStatus::Completed,
                'completed_at' => $completedAt,
                'processing_seconds' => $processingSeconds,
                'error_message' => null,
            ])->save();

            $authoritativeAttempt->forceFill([
                'status' => ProcessingStatus::Completed,
                'progress_percentage' => 100,
                'completed_at' => $completedAt,
                'processing_seconds' => $processingSeconds,
                'error_message' => null,
                'failure_code' => null,
            ])->save();
        });

        Log::info('Transcription result persisted.', [
            'transcription_id' => $transcription->getKey(),
            'processing_attempt_id' => $attempt->getKey(),
            'segment_count' => count($result->segments),
            'speech_detected' => $result->speechDetected,
            'detected_language' => $result->detectedLanguage->value,
        ]);
    }

    private function hasNewerAttempt(Transcription $transcription, ProcessingJob $attempt): bool
    {
        return ProcessingJob::query()
            ->where('transcription_id', $transcription->getKey())
            ->where('id', '>', $attempt->getKey())
            ->exists();
    }

    private function assertOwnershipIntegrity(Transcription $transcription): void
    {
        $mediaFile = $transcription->mediaFile()->first();

        if ($mediaFile === null || $mediaFile->user_id !== $transcription->user_id) {
            throw new TranscriptionException(
                TranscriptionFailure::PersistenceFailed,
                'The transcription ownership relationship is inconsistent.',
            );
        }
    }

    private function assertSegmentsValid(NormalizedTranscript $result): void
    {
        $indices = array_map(
            static fn (TranscriptSegmentData $segment): int => $segment->segmentIndex,
            $result->segments,
        );

        if (count($indices) !== count(array_unique($indices))) {
            throw new TranscriptionException(
                TranscriptionFailure::PersistenceFailed,
                'The normalized result contains duplicate segment indices.',
            );
        }
    }

    /**
     * @param  list<TranscriptSegmentData>  $segments
     */
    private function replaceSegments(Transcription $transcription, array $segments): void
    {
        TranscriptionSegment::query()
            ->where('transcription_id', $transcription->getKey())
            ->delete();

        usort(
            $segments,
            static fn (TranscriptSegmentData $a, TranscriptSegmentData $b): int => $a->segmentIndex <=> $b->segmentIndex,
        );

        foreach ($segments as $segment) {
            TranscriptionSegment::query()->create([
                'transcription_id' => $transcription->getKey(),
                'segment_index' => $segment->segmentIndex,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
                'text' => $segment->text,
                'language' => $segment->language->value,
            ]);
        }
    }
}
