<?php

namespace App\Actions;

use App\Enums\MediaStatus;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use Illuminate\Database\DatabaseManager;

/**
 * P7-009-CORR-01 explicit "Start Transcription" for an already-ingested media file.
 *
 * Finds or creates the real `Transcription` for the media (never a new media
 * row) and hands it to `TranscriptionOrchestrator::request()`, which alone owns
 * the Draft → Queued transition, ProcessingJob creation/reuse, and queue
 * dispatch. Inference never runs in the request.
 *
 * Idempotency: concurrent/duplicate initiations for one media file serialize on
 * the parent `media_files` row lock and converge on the single non-terminal
 * transcription; only when every earlier transcription is terminal does a
 * deliberate re-transcription create a new one.
 */
class TranscriptionInitiator
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
     * Whether the media is in a canonical processable state.
     */
    public function isProcessable(MediaFile $mediaFile): bool
    {
        return in_array($mediaFile->status, [MediaStatus::Uploaded, MediaStatus::Ready], true)
            && $mediaFile->purged_at === null
            && $mediaFile->scan_verdict !== 'infected'
            && $mediaFile->hasPhysicalFile();
    }

    /**
     * @throws TranscriptionException when the media is not processable
     */
    public function start(MediaFile $mediaFile): Transcription
    {
        $transcription = $this->database->transaction(function () use ($mediaFile): Transcription {
            $locked = MediaFile::query()
                ->whereKey($mediaFile->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $this->isProcessable($locked)) {
                throw new TranscriptionException(
                    TranscriptionFailure::MediaRejected,
                    'This media file cannot be transcribed in its current state.',
                );
            }

            $active = Transcription::query()
                ->where('media_file_id', $locked->getKey())
                ->whereIn('status', array_map(
                    static fn (TranscriptionStatus $status): string => $status->value,
                    self::ACTIVE_STATUSES,
                ))
                ->orderByDesc('id')
                ->first();

            return $active ?? Transcription::query()->create([
                'user_id' => $locked->user_id,
                'media_file_id' => $locked->getKey(),
                'title' => $locked->display_name,
                'language' => null,
                'model' => (string) config('transcription.model'),
                'status' => TranscriptionStatus::Draft,
            ]);
        });

        $this->orchestrator->request($transcription);

        return $transcription->refresh();
    }
}
