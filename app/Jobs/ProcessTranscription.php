<?php

namespace App\Jobs;

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Transcription\LanguageIdentifier;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionLifecycle;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Asynchronous transcription orchestration.
 *
 * The payload contains only small server-controlled identifiers. The job
 * reloads authoritative state from persistence and invokes the
 * provider-neutral TranscriptionProvider. At-least-once delivery is made
 * safe by the processing attempt claim boundary plus the idempotent result
 * writer.
 */
class ProcessTranscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Batch 2 does not implement retry/backoff policy (P3-007). A single
     * delivery keeps failures terminal and avoids duplicate inference.
     */
    public int $tries = 1;

    public function __construct(
        public readonly int $transcriptionId,
        public readonly int $processingAttemptId,
    ) {
        if ($transcriptionId <= 0 || $processingAttemptId <= 0) {
            throw new InvalidArgumentException('Transcription and processing attempt identifiers must be positive integers.');
        }
    }

    public function handle(
        TranscriptionProvider $provider,
        TranscriptionResultWriter $writer,
    ): void {
        $transcription = Transcription::query()
            ->with('mediaFile')
            ->find($this->transcriptionId);

        if ($transcription === null) {
            $this->skip('transcription not found');

            return;
        }

        $attempt = ProcessingJob::query()->find($this->processingAttemptId);

        if ($attempt === null || $attempt->transcription_id !== $transcription->getKey()) {
            $this->skip('processing attempt missing or mismatched');

            return;
        }

        if (in_array($transcription->status, [
            TranscriptionStatus::Completed,
            TranscriptionStatus::Failed,
            TranscriptionStatus::Cancelled,
        ], true)) {
            $this->skip('transcription already terminal');

            return;
        }

        if (in_array($attempt->status, [
            ProcessingStatus::Completed,
            ProcessingStatus::Failed,
            ProcessingStatus::Cancelled,
        ], true)) {
            $this->skip('processing attempt already terminal');

            return;
        }

        if ($this->hasNewerAttempt($transcription, $attempt)) {
            $this->skip('a newer processing attempt exists');

            return;
        }

        if (! $this->claimAttempt($attempt)) {
            $this->skip('processing attempt already claimed');

            return;
        }

        if ($transcription->status === TranscriptionStatus::Draft) {
            $this->transition($transcription, TranscriptionStatus::Queued);
        }

        $this->transition($transcription, TranscriptionStatus::Preparing);
        $transcription->forceFill([
            'started_at' => $transcription->started_at ?? now(),
        ])->save();

        $mediaFile = $transcription->mediaFile;

        if ($mediaFile === null || $mediaFile->user_id !== $transcription->user_id) {
            $this->fail($transcription, $attempt, TranscriptionFailure::MediaMissing, 'The media file relationship is missing or inconsistent.');

            return;
        }

        if (! $mediaFile->hasPhysicalFile()) {
            $this->fail($transcription, $attempt, TranscriptionFailure::MediaMissing, 'The media file is missing from private storage.');

            return;
        }

        $this->transition($transcription, TranscriptionStatus::Transcribing);

        $invocation = TranscriptionInvocation::create(
            transcriptionId: $transcription->getKey(),
            processingAttemptId: $attempt->getKey(),
            media: new TranscriptionMedia(
                storageKey: $mediaFile->storage_path,
                mimeType: $mediaFile->mime_type,
                fileSizeBytes: $mediaFile->file_size_bytes,
                durationSeconds: $mediaFile->duration_seconds !== null ? (float) $mediaFile->duration_seconds : null,
            ),
            requestedLanguage: $this->requestedLanguage($transcription),
        );

        Log::info('Transcription provider invocation started.', [
            'transcription_id' => $transcription->getKey(),
            'processing_attempt_id' => $attempt->getKey(),
            'media_file_id' => $mediaFile->getKey(),
            'request_id' => $invocation->requestId,
        ]);

        try {
            $result = $provider->transcribe($invocation);
        } catch (TranscriptionException $exception) {
            $this->fail($transcription, $attempt, $exception->failure, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            Log::error('Transcription provider raised an unexpected failure.', [
                'transcription_id' => $transcription->getKey(),
                'processing_attempt_id' => $attempt->getKey(),
                'exception' => $exception::class,
            ]);

            $this->fail($transcription, $attempt, TranscriptionFailure::ProcessingFailed, 'Unexpected transcription failure.');

            return;
        }

        try {
            $writer->persist(
                $transcription,
                $attempt,
                $result,
                (string) config('transcription.model', 'large-v3'),
            );
        } catch (TranscriptionException $exception) {
            $this->fail($transcription, $attempt, $exception->failure, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            Log::error('Transcription result persistence raised an unexpected failure.', [
                'transcription_id' => $transcription->getKey(),
                'processing_attempt_id' => $attempt->getKey(),
                'exception' => $exception::class,
            ]);

            $this->fail($transcription, $attempt, TranscriptionFailure::PersistenceFailed, 'Result persistence failed.');

            return;
        }

        Log::info('Transcription job completed.', [
            'transcription_id' => $transcription->getKey(),
            'processing_attempt_id' => $attempt->getKey(),
        ]);
    }

    private function requestedLanguage(Transcription $transcription): ?LanguageIdentifier
    {
        if ($transcription->language === null || $transcription->language === '') {
            return null;
        }

        return LanguageIdentifier::fromBcp47($transcription->language);
    }

    private function hasNewerAttempt(Transcription $transcription, ProcessingJob $attempt): bool
    {
        return ProcessingJob::query()
            ->where('transcription_id', $transcription->getKey())
            ->where('id', '>', $attempt->getKey())
            ->exists();
    }

    /**
     * Atomically claim a queued attempt. A duplicate delivery that arrives
     * after another worker already claimed (or finished) the attempt will
     * observe zero affected rows and no-op.
     */
    private function claimAttempt(ProcessingJob $attempt): bool
    {
        $claimed = ProcessingJob::query()
            ->whereKey($attempt->getKey())
            ->where('status', ProcessingStatus::Queued->value)
            ->update([
                'status' => ProcessingStatus::Running->value,
                'started_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $attempt->refresh();

        return true;
    }

    private function transition(Transcription $transcription, TranscriptionStatus $target): void
    {
        if ($transcription->status === $target) {
            return;
        }

        if (! TranscriptionLifecycle::canTransition($transcription->status, $target)) {
            return;
        }

        $transcription->forceFill(['status' => $target])->save();
    }

    private function fail(
        Transcription $transcription,
        ProcessingJob $attempt,
        TranscriptionFailure $failure,
        string $safeMessage,
    ): void {
        $this->databaseFail($transcription, $attempt, $failure, $safeMessage);

        Log::warning('Transcription job failed.', [
            'transcription_id' => $transcription->getKey(),
            'processing_attempt_id' => $attempt->getKey(),
            'failure' => $failure->value,
            'retryable' => $failure->isRetryable(),
        ]);
    }

    private function databaseFail(
        Transcription $transcription,
        ProcessingJob $attempt,
        TranscriptionFailure $failure,
        string $safeMessage,
    ): void {
        DB::transaction(function () use ($transcription, $attempt, $failure, $safeMessage): void {
            $fresh = Transcription::query()->whereKey($transcription->getKey())->lockForUpdate()->first();

            if ($fresh !== null && $fresh->status !== TranscriptionStatus::Completed) {
                $fresh->forceFill([
                    'status' => TranscriptionStatus::Failed,
                    'completed_at' => now(),
                    'error_message' => $safeMessage,
                ])->save();
            }

            $freshAttempt = ProcessingJob::query()->whereKey($attempt->getKey())->lockForUpdate()->first();

            if ($freshAttempt !== null && $freshAttempt->status !== ProcessingStatus::Completed) {
                $freshAttempt->forceFill([
                    'status' => ProcessingStatus::Failed,
                    'completed_at' => now(),
                    'error_message' => $safeMessage,
                    'failure_code' => $failure->value,
                ])->save();
            }
        });
    }

    private function skip(string $reason): void
    {
        Log::info('Transcription job skipped.', [
            'transcription_id' => $this->transcriptionId,
            'processing_attempt_id' => $this->processingAttemptId,
            'reason' => $reason,
        ]);
    }
}
