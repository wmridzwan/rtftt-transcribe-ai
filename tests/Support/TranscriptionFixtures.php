<?php

namespace Tests\Support;

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared Batch 2 (P3-004/P3-005/P3-006) test fixtures.
 */
final class TranscriptionFixtures
{
    /**
     * @return array{user: User, media: MediaFile, transcription: Transcription, attempt: ProcessingJob}
     */
    public static function scenario(
        TranscriptionStatus $transcriptionStatus = TranscriptionStatus::Queued,
        ProcessingStatus $attemptStatus = ProcessingStatus::Queued,
        bool $withPhysicalFile = true,
        bool $consistentOwnership = true,
        ?User $user = null,
    ): array {
        $user ??= User::factory()->create();

        $mediaOwner = $consistentOwnership ? $user : User::factory()->create();

        $media = MediaFile::factory()->create([
            'user_id' => $mediaOwner->id,
            'storage_path' => 'media/'.Str::uuid().'.mp3',
            'extension' => 'mp3',
            'mime_type' => 'audio/mpeg',
            'duration_seconds' => 12,
        ]);

        if ($withPhysicalFile) {
            Storage::disk((string) config('media.storage_disk'))->put($media->storage_path, 'audio-bytes');
        }

        $transcription = Transcription::factory()->create([
            'user_id' => $user->id,
            'media_file_id' => $media->id,
            'status' => $transcriptionStatus,
            'language' => 'en',
            'detected_language' => null,
            'speech_detected' => null,
            'full_text' => null,
            'started_at' => null,
            'completed_at' => null,
            'processing_seconds' => null,
            'error_message' => null,
        ]);

        $attempt = ProcessingJob::factory()->create([
            'transcription_id' => $transcription->id,
            'worker_name' => null,
            'stage' => ProcessingStage::Transcribe,
            'status' => $attemptStatus,
            'progress_percentage' => 0,
            'started_at' => null,
            'completed_at' => null,
            'processing_seconds' => null,
            'error_message' => null,
        ]);

        return [
            'user' => $user,
            'media' => $media,
            'transcription' => $transcription,
            'attempt' => $attempt,
        ];
    }

    /**
     * Create an owned media file + transcription without a processing attempt.
     *
     * @return array{user: User, media: MediaFile, transcription: Transcription}
     */
    public static function ownedTranscription(
        TranscriptionStatus $transcriptionStatus = TranscriptionStatus::Draft,
        bool $withPhysicalFile = true,
        ?User $user = null,
    ): array {
        $user ??= User::factory()->create();

        $media = MediaFile::factory()->create([
            'user_id' => $user->id,
            'storage_path' => 'media/'.Str::uuid().'.mp3',
            'extension' => 'mp3',
            'mime_type' => 'audio/mpeg',
            'duration_seconds' => 12,
        ]);

        if ($withPhysicalFile) {
            Storage::disk((string) config('media.storage_disk'))->put($media->storage_path, 'audio-bytes');
        }

        $transcription = Transcription::factory()->create([
            'user_id' => $user->id,
            'media_file_id' => $media->id,
            'status' => $transcriptionStatus,
            'language' => 'en',
            'detected_language' => null,
            'speech_detected' => null,
            'full_text' => null,
            'started_at' => null,
            'completed_at' => null,
            'processing_seconds' => null,
            'error_message' => null,
        ]);

        return [
            'user' => $user,
            'media' => $media,
            'transcription' => $transcription,
        ];
    }
}
