<?php

use App\Models\Transcription;
use App\Models\User;
use App\TranscriptExperience\WorkspaceAvailability;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('treats canonical no-speech completed state as a valid empty workspace', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'speech_detected' => false,
        'full_text' => '',
    ]);

    expect(WorkspaceAvailability::isNoSpeech($transcription))->toBeTrue();

    $state = WorkspaceAvailability::for($transcription);

    expect($state->noSpeech)->toBeTrue()
        ->and($state->export)->toBeTrue()
        ->and($state->search)->toBeFalse()
        ->and($state->copy)->toBeFalse()
        ->and($state->transcriptInteraction)->toBeFalse()
        ->and($state->retry)->toBeFalse();
});

it('does not treat a normal completed transcript as no-speech', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Speech content.',
        'language' => 'en',
    ]);

    expect(WorkspaceAvailability::isNoSpeech($transcription))->toBeFalse()
        ->and(WorkspaceAvailability::for($transcription)->noSpeech)->toBeFalse();
});

it('does not treat an empty completed transcript with speech detected as no-speech', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'speech_detected' => true,
        'full_text' => '',
    ]);

    expect(WorkspaceAvailability::isNoSpeech($transcription))->toBeFalse();
});
