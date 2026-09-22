<?php

namespace App\Actions;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Log;

/**
 * Server-side entry point for requesting transcription.
 *
 * Creates/reuses the canonical processing attempt state, then dispatches a
 * small-identifier job onto the configured queue connection. Inference is
 * never executed inside the request boundary.
 */
class TranscriptionOrchestrator
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * @throws TranscriptionException when the transcription is not requestable
     */
    public function request(Transcription $transcription): ProcessingJob
    {
        $mediaFile = $transcription->mediaFile()->first();

        if ($mediaFile === null || $mediaFile->user_id !== $transcription->user_id) {
            throw new TranscriptionException(
                TranscriptionFailure::ProcessingFailed,
                'The transcription ownership relationship is inconsistent.',
            );
        }

        if (in_array($transcription->status, [
            TranscriptionStatus::Completed,
            TranscriptionStatus::Failed,
            TranscriptionStatus::Cancelled,
        ], true)) {
            throw new TranscriptionException(
                TranscriptionFailure::ProcessingFailed,
                'The transcription is not requestable in its current state.',
            );
        }

        $attempt = $this->database->transaction(function () use ($transcription): ProcessingJob {
            $locked = Transcription::query()
                ->whereKey($transcription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === TranscriptionStatus::Draft) {
                $locked->forceFill(['status' => TranscriptionStatus::Queued])->save();
            }

            $existing = ProcessingJob::query()
                ->where('transcription_id', $locked->getKey())
                ->whereIn('status', [
                    ProcessingStatus::Queued->value,
                    ProcessingStatus::Running->value,
                ])
                ->orderByDesc('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return ProcessingJob::query()->create([
                'transcription_id' => $locked->getKey(),
                'worker_name' => null,
                'stage' => ProcessingStage::Transcribe,
                'status' => ProcessingStatus::Queued,
                'progress_percentage' => 0,
            ]);
        });

        $this->dispatch($attempt);

        return $attempt;
    }

    /**
     * Dispatch an attempt onto the configured transcription queue.
     */
    public function dispatch(ProcessingJob $attempt): void
    {
        $queue = (string) config('transcription.queue', 'transcription');
        $connection = config('transcription.queue_connection');

        $pending = ProcessTranscription::dispatch(
            $attempt->transcription_id,
            $attempt->getKey(),
        )->onQueue($queue);

        if (is_string($connection) && $connection !== '') {
            $pending->onConnection($connection);
        }

        Log::info('Transcription dispatched to queue.', [
            'transcription_id' => $attempt->transcription_id,
            'processing_attempt_id' => $attempt->getKey(),
            'queue' => $queue,
            'connection' => is_string($connection) && $connection !== '' ? $connection : config('queue.default'),
        ]);
    }
}
