<?php

use App\Models\MediaFile;
use App\Models\User;
use App\Services\MediaMetadataProbeService;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

test('media metadata probe service can be instantiated', function () {
    $service = app(MediaMetadataProbeService::class);
    expect($service)->toBeInstanceOf(MediaMetadataProbeService::class);
});

test('probe returns null values when file does not exist on disk', function () {
    $user = User::factory()->create();

    // Create a MediaFile record without actually storing a file
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'media/nonexistent-file.mp3',
    ]);

    $service = app(MediaMetadataProbeService::class);
    $result = $service->probe($mediaFile);

    expect($result)->toBe([
        'duration_seconds' => null,
        'audio_codec' => null,
        'video_codec' => null,
        'sample_rate' => null,
        'channels' => null,
    ]);
});

test('probe resolves a real private-disk path and extracts metadata from a WAV fixture', function () {
    $service = app(MediaMetadataProbeService::class);

    if (! $service->isAvailable()) {
        $this->markTestSkipped('FFprobe is unavailable; real metadata extraction requires an installed FFprobe executable.');
    }

    $user = User::factory()->create();
    $sampleRate = 8000;
    $data = str_repeat("\0", $sampleRate * 2);
    $wav = 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $sampleRate, $sampleRate * 2, 2, 16).'data'.pack('V', strlen($data)).$data;
    $storagePath = 'media/probe-fixture.wav';
    Storage::disk('local')->put($storagePath, $wav);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => $storagePath,
        'file_size_bytes' => strlen($wav),
        'extension' => 'wav',
        'mime_type' => 'audio/wav',
    ]);

    config(['media.ffprobe_path' => getenv('RTFTT_FFPROBE_PATH') ?: 'ffprobe']);
    $result = $service->probe($mediaFile);

    expect($result['duration_seconds'])->toBe(1)
        ->and($result['audio_codec'])->toBe('pcm_s16le')
        ->and($result['video_codec'])->toBeNull()
        ->and($result['sample_rate'])->toBe($sampleRate)
        ->and($result['channels'])->toBe(1);
});

test('is available returns boolean', function () {
    $service = app(MediaMetadataProbeService::class);
    $result = $service->isAvailable();
    expect($result)->toBeBool();
});

test('probe and update sets null values when probe fails', function () {
    $user = User::factory()->create();

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'media/nonexistent.mp3',
        'duration_seconds' => null,
        'audio_codec' => null,
        'video_codec' => null,
        'sample_rate' => null,
        'channels' => null,
    ]);

    $service = app(MediaMetadataProbeService::class);
    $result = $service->probeAndUpdate($mediaFile);

    expect($result->duration_seconds)->toBeNull()
        ->and($result->audio_codec)->toBeNull()
        ->and($result->video_codec)->toBeNull()
        ->and($result->sample_rate)->toBeNull()
        ->and($result->channels)->toBeNull();
});
