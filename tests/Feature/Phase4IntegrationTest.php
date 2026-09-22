<?php

use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionFailure;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function phase4Media(User $user): MediaFile
{
    $media = MediaFile::factory()->audio()->create(['user_id' => $user->id]);
    Storage::disk(config('media.storage_disk'))->put($media->storage_path, 'media-bytes');

    return $media;
}

it('V4-01 exposes the full completed Phase 4 workspace', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = phase4Media($user);
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
    ]);
    $transcription->segments()->create([
        'segment_index' => 0, 'start_seconds' => 0, 'end_seconds' => 5,
        'text' => 'Searchable segment', 'language' => 'zh',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('data-media-player', false);
    $response->assertSee('data-seek-seconds="0"', false);
    $response->assertSee('data-segment-language="zh"', false);
    $response->assertSee('Search transcript');
    $response->assertSee('Copy transcript');
    $response->assertSee(route('transcriptions.export.txt', $transcription), false);
    $response->assertSee(route('transcriptions.export.srt', $transcription), false);
    $response->assertSee(route('transcriptions.export.vtt', $transcription), false);
    $response->assertSee(route('transcriptions.export.docx', $transcription), false);
});

it('V4-02 does not expose completed transcript interaction for a processing transcription', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = phase4Media($user);
    $transcription = Transcription::factory()->transcribing()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertDontSee('data-seek-seconds', false);
    $response->assertDontSee('Search transcript');
    $response->assertSee('No transcript segments');
});

it('V4-03 preserves the failed-state retry surface without completed workspace controls', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = phase4Media($user);
    $transcription = Transcription::factory()->failed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'error_message' => 'Worker timed out.',
    ]);
    ProcessingJob::query()->create([
        'transcription_id' => $transcription->id,
        'stage' => ProcessingStage::Transcribe,
        'status' => ProcessingStatus::Failed,
        'progress_percentage' => 0,
        'failure_code' => TranscriptionFailure::WorkerTimeout,
        'error_message' => 'Worker timed out.',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee(route('transcriptions.retry', $transcription), false);
    $response->assertSee('Retry transcription');
    $response->assertDontSee('data-seek-seconds', false);
    $response->assertDontSee('Search transcript');
});
