<?php

use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;
use App\TranscriptExperience\WorkspaceAvailability;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function workspaceTranscription(TranscriptionStatus $status): Transcription
{
    $user = User::factory()->create();

    return Transcription::factory()->create([
        'user_id' => $user->id,
        'status' => $status,
        'speech_detected' => null,
        'full_text' => null,
    ]);
}

it('disables all interactive and export features for non-completed processing states', function () {
    foreach ([
        TranscriptionStatus::Draft,
        TranscriptionStatus::Queued,
        TranscriptionStatus::Preparing,
        TranscriptionStatus::Transcribing,
        TranscriptionStatus::Cancelled,
    ] as $status) {
        $state = WorkspaceAvailability::for(workspaceTranscription($status));

        expect($state->transcriptInteraction)->toBeFalse()
            ->and($state->search)->toBeFalse()
            ->and($state->copy)->toBeFalse()
            ->and($state->export)->toBeFalse()
            ->and($state->retry)->toBeFalse()
            ->and($state->noSpeech)->toBeFalse();
    }
});

it('preserves the failed-state retry surface without transcript interaction', function () {
    $state = WorkspaceAvailability::for(workspaceTranscription(TranscriptionStatus::Failed));

    expect($state->retry)->toBeTrue()
        ->and($state->transcriptInteraction)->toBeFalse()
        ->and($state->search)->toBeFalse()
        ->and($state->copy)->toBeFalse()
        ->and($state->export)->toBeFalse();
});

it('enables interaction, search, copy, and export for a completed transcription with segments', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Hello world.',
        'language' => 'en',
    ]);

    $state = WorkspaceAvailability::for($transcription);

    expect($state->transcriptInteraction)->toBeTrue()
        ->and($state->search)->toBeTrue()
        ->and($state->copy)->toBeTrue()
        ->and($state->export)->toBeTrue()
        ->and($state->retry)->toBeFalse()
        ->and($state->noSpeech)->toBeFalse();
});

it('keeps export available but interaction unavailable for a completed transcription without segments', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'speech_detected' => true,
    ]);

    $state = WorkspaceAvailability::for($transcription);

    expect($state->transcriptInteraction)->toBeFalse()
        ->and($state->search)->toBeFalse()
        ->and($state->copy)->toBeFalse()
        ->and($state->export)->toBeTrue()
        ->and($state->noSpeech)->toBeFalse();
});

it('reports media player availability from physical file existence', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    expect(WorkspaceAvailability::for($transcription)->mediaPlayer)->toBeFalse();

    Storage::disk(config('media.storage_disk'))->put($mediaFile->storage_path, 'media-bytes');

    expect(WorkspaceAvailability::for($transcription->fresh())->mediaPlayer)->toBeTrue();
});
