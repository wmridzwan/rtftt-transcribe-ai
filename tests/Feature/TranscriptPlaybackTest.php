<?php

use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function playbackMedia(User $user, bool $video = false): MediaFile
{
    $media = $video
        ? MediaFile::factory()->video()->create(['user_id' => $user->id])
        : MediaFile::factory()->audio()->create(['user_id' => $user->id]);

    Storage::disk(config('media.storage_disk'))->put($media->storage_path, 'media-bytes');

    return $media;
}

function playbackTranscription(User $user, ?MediaFile $media = null): Transcription
{
    return Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media?->id ?? MediaFile::factory()->create(['user_id' => $user->id])->id,
    ]);
}

it('renders an audio player bound to the authorized stream URL', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = playbackMedia($user);
    $transcription = playbackTranscription($user, $media);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('data-media-player', false);
    $response->assertSee('<audio', false);
    $response->assertSee(route('media.stream', ['mediaFile' => $media->uuid]), false);
    $response->assertDontSee($media->storage_path);
});

it('renders a video player for video media', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = playbackMedia($user, video: true);
    $transcription = playbackTranscription($user, $media);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('<video', false);
    $response->assertSee(route('media.stream', ['mediaFile' => $media->uuid]), false);
});

it('omits the player when no physical media file exists', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = MediaFile::factory()->audio()->create(['user_id' => $user->id]);
    $transcription = playbackTranscription($user, $media);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertDontSee('<audio', false);
    $response->assertDontSee('<video', false);
});

it('renders timestamp controls with exact persisted millisecond seek values', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = playbackTranscription($user);
    $transcription->segments()->create([
        'segment_index' => 0, 'start_seconds' => 5.999, 'end_seconds' => 6.999,
        'text' => 'First.', 'language' => 'en',
    ]);
    $transcription->segments()->create([
        'segment_index' => 1, 'start_seconds' => 65.123, 'end_seconds' => 70.0,
        'text' => 'Second.', 'language' => 'zh',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('data-seek-seconds="5.999"', false);
    $response->assertSee('data-seek-seconds="65.123"', false);
    $response->assertSee('aria-label="Seek to 00:05"', false);
});

it('renders active-segment rows and per-segment language labels', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = playbackTranscription($user);
    $transcription->segments()->create([
        'segment_index' => 0, 'start_seconds' => 0, 'end_seconds' => 5,
        'text' => '欢迎', 'language' => 'zh',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('data-segment-row', false);
    $response->assertSee('data-segment-language="zh"', false);
    $response->assertSee('欢迎');
});

it('keeps the no-speech workspace valid without timestamp controls', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = playbackMedia($user);
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'speech_detected' => false,
        'full_text' => '',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('No transcript segments');
    $response->assertSee('data-media-player', false);
    $response->assertDontSee('data-seek-seconds', false);
});

it('preserves the P4-004 search and copy surface', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = playbackTranscription($user);
    $transcription->segments()->create([
        'segment_index' => 0, 'start_seconds' => 0, 'end_seconds' => 5,
        'text' => 'Searchable text.', 'language' => 'en',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('Search transcript');
    $response->assertSee('Copy transcript');
    $response->assertSee('data-segment-text', false);
    $response->assertSee('transcriptPlayback', false);
    $response->assertSee('transcriptSearch', false);
});

it('does not expose the private storage path or a raw binary in the page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $media = playbackMedia($user);
    $transcription = playbackTranscription($user, $media);
    $transcription->segments()->create([
        'segment_index' => 0, 'start_seconds' => 0, 'end_seconds' => 5,
        'text' => 'Text.', 'language' => 'en',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertDontSee($media->storage_path);
    $response->assertDontSee('media-bytes');
});
