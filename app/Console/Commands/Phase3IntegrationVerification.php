<?php

namespace App\Console\Commands;

use App\Actions\TranscriptionOrchestrator;
use App\Actions\TranscriptionRetry;
use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionFailure;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * P3-008 Phase 3 integration verification harness.
 *
 * Hidden and disabled unless RTFTT_P3008_RUN=1. Not production behavior: it
 * seeds a real private MediaFile + transcription, requests transcription
 * through the real orchestrator (dispatching onto the configured queue), and
 * asserts the final authoritative state after a real queue worker consumes it.
 *
 * Modes:
 *   --mode=seed   --fixture=<path> --state=<json path>
 *   --mode=assert --state=<json path> --out=<json path>
 *   --mode=redis-payload --state=<json path> --out=<json path>
 */
#[Signature('test:p3-008-integration {--mode=seed} {--fixture=} {--state=} {--out=}')]
#[Description('P3-008 Phase 3 integration verification harness (disabled unless RTFTT_P3008_RUN=1).')]
class Phase3IntegrationVerification extends Command
{
    protected $hidden = true;

    public function handle(TranscriptionOrchestrator $orchestrator): int
    {
        if (getenv('RTFTT_P3008_RUN') !== '1') {
            $this->error('P3-008 integration harness is disabled. Set RTFTT_P3008_RUN=1.');

            return self::FAILURE;
        }

        return match ((string) $this->option('mode')) {
            'seed' => $this->seed($orchestrator),
            'seed-retry' => $this->seedRetry(),
            'assert' => $this->assertResult(),
            'redis-payload' => $this->redisPayload(),
            default => $this->failWith('Unknown mode.'),
        };
    }

    private function seed(TranscriptionOrchestrator $orchestrator): int
    {
        $fixture = (string) $this->option('fixture');
        $statePath = (string) $this->option('state');

        if (! is_file($fixture)) {
            return $this->failWith("Fixture not found: {$fixture}");
        }

        $user = User::factory()->create();

        $storagePath = 'media/p3008/'.Str::uuid().'.mp3';
        MediaFile::storage()->put($storagePath, (string) file_get_contents($fixture));

        $media = MediaFile::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'p3008_speech.mp3',
            'storage_filename' => basename($storagePath),
            'storage_path' => $storagePath,
            'media_type' => MediaType::Audio,
            'mime_type' => 'audio/mpeg',
            'extension' => 'mp3',
            'file_size_bytes' => (int) filesize($fixture),
            'duration_seconds' => 9,
            'audio_codec' => 'mp3',
            'sample_rate' => 44100,
            'channels' => 1,
            'status' => MediaStatus::Uploaded,
        ]);

        $transcription = Transcription::create([
            'user_id' => $user->id,
            'media_file_id' => $media->id,
            'title' => 'P3-008 integration fixture',
            'language' => 'en',
            'status' => TranscriptionStatus::Draft,
        ]);

        $attempt = $orchestrator->request($transcription);

        $state = [
            'user_id' => $user->id,
            'media_id' => $media->id,
            'transcription_id' => $transcription->getKey(),
            'attempt_id' => $attempt->getKey(),
            'storage_path' => $storagePath,
        ];

        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT));

        $this->info(json_encode($state, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function redisPayload(): int
    {
        $state = json_decode((string) file_get_contents((string) $this->option('state')), true);
        $out = (string) $this->option('out');

        $connection = Redis::connection();

        $queueKey = 'queues:'.(string) config('transcription.queue', 'transcription');

        $evidence = [
            'queue_key' => $queueKey,
            'payload' => null,
        ];

        $items = $connection->lrange($queueKey, 0, -1);

        if (is_array($items) && $items !== []) {
            $evidence['list_length'] = count($items);
            $evidence['payload'] = $items[0];
        } else {
            $evidence['list_length'] = 0;
        }

        $payload = (string) ($evidence['payload'] ?? '');
        $evidence['payload_bytes'] = strlen($payload);
        $evidence['contains_storage_path'] = $state !== null
            && isset($state['storage_path'])
            && str_contains($payload, (string) $state['storage_path']);
        $evidence['contains_transcription_id'] = $state !== null
            && str_contains($payload, (string) ($state['transcription_id'] ?? ''));
        $evidence['contains_attempt_id'] = $state !== null
            && str_contains($payload, (string) ($state['attempt_id'] ?? ''));
        $evidence['contains_binary'] = str_contains($payload, 'audio-bytes');

        file_put_contents($out, json_encode($evidence, JSON_PRETTY_PRINT));
        $this->info(json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function seedRetry(): int
    {
        $fixture = (string) $this->option('fixture');
        $statePath = (string) $this->option('state');

        if (! is_file($fixture)) {
            return $this->failWith("Fixture not found: {$fixture}");
        }

        $user = User::factory()->create();

        $storagePath = 'media/p3008/'.Str::uuid().'.mp3';
        MediaFile::storage()->put($storagePath, (string) file_get_contents($fixture));

        $media = MediaFile::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'p3008_retry.mp3',
            'storage_filename' => basename($storagePath),
            'storage_path' => $storagePath,
            'media_type' => MediaType::Audio,
            'mime_type' => 'audio/mpeg',
            'extension' => 'mp3',
            'file_size_bytes' => (int) filesize($fixture),
            'duration_seconds' => 9,
            'audio_codec' => 'mp3',
            'sample_rate' => 44100,
            'channels' => 1,
            'status' => MediaStatus::Uploaded,
        ]);

        $transcription = Transcription::create([
            'user_id' => $user->id,
            'media_file_id' => $media->id,
            'title' => 'P3-008 retry fixture',
            'language' => 'en',
            'status' => TranscriptionStatus::Failed,
            'error_message' => 'Transcription worker did not complete in time.',
            'completed_at' => now(),
        ]);

        $failedAttempt = ProcessingJob::create([
            'transcription_id' => $transcription->getKey(),
            'worker_name' => null,
            'stage' => ProcessingStage::Transcribe,
            'status' => ProcessingStatus::Failed,
            'progress_percentage' => 0,
            'started_at' => now()->subMinutes(10),
            'completed_at' => now()->subMinutes(9),
            'error_message' => 'Transcription worker did not complete in time.',
            'failure_code' => TranscriptionFailure::WorkerTimeout->value,
        ]);

        $newAttempt = app(TranscriptionRetry::class)->retry($transcription);

        $state = [
            'user_id' => $user->id,
            'media_id' => $media->id,
            'transcription_id' => $transcription->getKey(),
            'failed_attempt_id' => $failedAttempt->getKey(),
            'attempt_id' => $newAttempt->getKey(),
            'storage_path' => $storagePath,
        ];

        file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT));
        $this->info(json_encode($state, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function assertResult(): int
    {
        $state = json_decode((string) file_get_contents((string) $this->option('state')), true);
        $out = (string) $this->option('out');

        $transcription = Transcription::query()->with('segments')->findOrFail((int) $state['transcription_id']);
        $attempt = $transcription->processingJobs()->orderByDesc('id')->first();

        $evidence = [
            'transcription_status' => $transcription->status->value,
            'attempt_status' => $attempt?->status->value,
            'full_text' => $transcription->full_text,
            'full_text_length' => strlen((string) $transcription->full_text),
            'detected_language' => $transcription->detected_language,
            'requested_language' => $transcription->language,
            'speech_detected' => $transcription->speech_detected,
            'model' => $transcription->model,
            'segment_count' => $transcription->segments->count(),
            'segment_languages' => $transcription->segments->pluck('language')->map->value->all(),
            'segment_indices' => $transcription->segments->pluck('segment_index')->all(),
            'segment_first' => $transcription->segments->first()?->text,
            'started_at' => optional($transcription->started_at)->toDateTimeString(),
            'completed_at' => optional($transcription->completed_at)->toDateTimeString(),
            'processing_seconds' => $transcription->processing_seconds,
            'error_message' => $transcription->error_message,
            'attempt_count' => $transcription->processingJobs()->count(),
            'active_attempt_count' => $transcription->processingJobs()
                ->whereIn('status', ['queued', 'running'])->count(),
            'attempts' => $transcription->processingJobs()->orderBy('id')->get()->map(
                static fn ($job): array => [
                    'id' => $job->getKey(),
                    'status' => $job->status->value,
                    'failure_code' => $job->failure_code?->value,
                ],
            )->all(),
        ];

        file_put_contents($out, json_encode($evidence, JSON_PRETTY_PRINT));
        $this->info(json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
