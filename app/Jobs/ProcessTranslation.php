<?php

namespace App\Jobs;

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationLifecycle;
use App\Translation\TranslationProvider;
use App\Translation\TranslationQueueConfig;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Asynchronous translation orchestration (ADR-022; P5-004 cycle 2, P5-004C).
 *
 * The payload carries only small server-controlled identifiers plus the
 * attempt token. Every state mutation is fenced by that token, so a late or
 * stale worker can never mutate a newer attempt.
 */
class ProcessTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Per-job worker timeout; safely below the queue connection retry_after so
     * a running translation is never re-delivered mid-execution.
     */
    public int $timeout;

    public function __construct(
        public readonly int $translationId,
        public readonly int $transcriptionId,
        public readonly string $attemptToken,
    ) {
        if ($translationId <= 0 || $transcriptionId <= 0) {
            throw new InvalidArgumentException('Translation and transcription identifiers must be positive integers.');
        }

        if ($attemptToken === '') {
            throw new InvalidArgumentException('The attempt token must not be empty.');
        }

        $this->timeout = TranslationQueueConfig::jobTimeoutSeconds();
    }

    public function handle(
        TranslationProvider $provider,
        TranslationResultWriter $writer,
    ): void {
        $translation = Translation::query()->find($this->translationId);

        if ($translation === null || $translation->transcription_id !== $this->transcriptionId) {
            $this->skip('translation missing or mismatched');

            return;
        }

        if (in_array($translation->status, [
            TranslationStatus::Completed,
            TranslationStatus::Failed,
        ], true)) {
            $this->skip('translation already terminal');

            return;
        }

        if (! TranslationLifecycle::canTransition($translation->status, TranslationStatus::Translating)) {
            $this->skip('translation is not in a claimable state');

            return;
        }

        if (! $this->claim($translation)) {
            $this->skip('attempt superseded or already claimed');

            return;
        }

        // Everything after the claim runs inside one failure contract: any
        // setup or execution failure is recorded through the taxonomy (fenced by
        // the attempt token) instead of stranding the row in `translating`.
        try {
            $this->execute($translation, $provider, $writer);
        } catch (TranslationException $exception) {
            $this->fail($translation, $exception->failure, $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Translation job raised an unexpected failure.', [
                'translation_id' => $translation->getKey(),
                'exception' => $exception::class,
            ]);

            $this->fail($translation, TranslationFailure::ProcessingFailed, 'Unexpected translation failure.');
        }
    }

    /**
     * @throws TranslationException
     */
    private function execute(
        Translation $translation,
        TranslationProvider $provider,
        TranslationResultWriter $writer,
    ): void {
        $transcription = Transcription::query()
            ->with('segments')
            ->find($this->transcriptionId);

        if ($transcription === null) {
            throw new TranslationException(TranslationFailure::InvalidRequest, 'The source transcription is missing.');
        }

        $invocation = TranslationInvocation::create(
            transcriptionId: $transcription->getKey(),
            targetLanguage: $translation->target_language,
            segments: $this->sourceSegments($transcription),
            translationId: $translation->getKey(),
        );

        Log::info('Translation provider invocation started.', [
            'transcription_id' => $transcription->getKey(),
            'translation_id' => $translation->getKey(),
            'target_language' => $translation->target_language->value,
            'request_id' => $invocation->requestId,
        ]);

        try {
            $result = $provider->translate($invocation);
        } catch (TranslationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Translation provider raised an unexpected failure.', [
                'translation_id' => $translation->getKey(),
                'exception' => $exception::class,
            ]);

            throw new TranslationException(TranslationFailure::ProcessingFailed, 'Unexpected translation failure.', $exception);
        }

        try {
            $writer->persist($transcription, $result, $translation->getKey(), $this->attemptToken);
        } catch (TranslationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // P5-004C taxonomy: a transient persistence/concurrency failure
            // (e.g. a lost SQLite write lock) is recoverable and must keep a
            // retry path; a deterministic integrity/constraint failure must not
            // be auto-retryable.
            $transient = $this->isTransientPersistenceFailure($exception);
            $failure = $transient
                ? TranslationFailure::ProcessingFailed
                : TranslationFailure::PersistenceFailed;

            Log::error('Translation result persistence raised an unexpected failure.', [
                'translation_id' => $translation->getKey(),
                'exception' => $exception::class,
                'transient' => $transient,
            ]);

            throw new TranslationException(
                $failure,
                $transient ? 'Transient translation persistence failure.' : 'Translation persistence failed.',
                $exception,
            );
        }

        Log::info('Translation job completed.', [
            'translation_id' => $translation->getKey(),
        ]);
    }

    /**
     * @return list<TranslationSegmentData>
     */
    private function sourceSegments(Transcription $transcription): array
    {
        return array_values($transcription->segments
            ->map(fn (TranscriptionSegment $segment): TranslationSegmentData => new TranslationSegmentData(
                segmentIndex: (int) $segment->segment_index,
                startSeconds: (float) $segment->start_seconds,
                endSeconds: (float) $segment->end_seconds,
                text: (string) $segment->text,
                sourceLanguage: $segment->language,
            ))
            ->all());
    }

    /**
     * Atomically claim the current attempt. A duplicate or stale delivery
     * observes zero affected rows and no-ops.
     */
    private function claim(Translation $translation): bool
    {
        $claimed = Translation::query()
            ->whereKey($translation->getKey())
            ->where('attempt_token', $this->attemptToken)
            ->whereIn('status', [
                TranslationStatus::Pending->value,
                TranslationStatus::Queued->value,
            ])
            ->update([
                'status' => TranslationStatus::Translating->value,
                'started_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        try {
            $translation->refresh();
        } catch (Throwable $exception) {
            Log::error('Translation claim refresh failed after a successful claim.', [
                'translation_id' => $translation->getKey(),
                'exception' => $exception::class,
            ]);

            $this->fail(
                $translation,
                TranslationFailure::ProcessingFailed,
                'The translation attempt could not be prepared.',
            );

            return false;
        }

        return true;
    }

    private function fail(Translation $translation, TranslationFailure $failure, string $safeMessage): void
    {
        $updated = Translation::query()
            ->whereKey($translation->getKey())
            ->where('attempt_token', $this->attemptToken)
            ->whereIn('status', [
                TranslationStatus::Pending->value,
                TranslationStatus::Queued->value,
                TranslationStatus::Translating->value,
            ])
            ->update([
                'status' => TranslationStatus::Failed->value,
                'failure_code' => $failure->value,
                'completed_at' => now(),
            ]);

        if ($updated === 0) {
            Log::warning('Translation failure ignored for a superseded attempt.', [
                'translation_id' => $translation->getKey(),
                'failure' => $failure->value,
            ]);

            return;
        }

        Log::warning('Translation job failed.', [
            'translation_id' => $translation->getKey(),
            'failure' => $failure->value,
            'message' => $safeMessage,
        ]);
    }

    /**
     * Queue-level failure handler (P5-004C): invoked when the job is killed by
     * the worker timeout or fails after its attempts. Token-fenced via fail(),
     * so a killed/stale attempt can never overwrite a newer attempt.
     */
    public function failed(?Throwable $exception): void
    {
        $translation = Translation::query()->find($this->translationId);

        if ($translation === null) {
            return;
        }

        $this->fail(
            $translation,
            TranslationFailure::ProcessingFailed,
            'The translation attempt failed or timed out.',
        );
    }

    private function isTransientPersistenceFailure(Throwable $exception): bool
    {
        return $exception instanceof QueryException
            && app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($exception);
    }

    private function skip(string $reason): void
    {
        Log::info('Translation job skipped.', [
            'translation_id' => $this->translationId,
            'transcription_id' => $this->transcriptionId,
            'reason' => $reason,
        ]);
    }
}
