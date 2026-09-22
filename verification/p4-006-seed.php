<?php

/**
 * P4-006 final integration-verification fixture seeder (verification tooling).
 *
 * Boots Laravel against a dedicated verification database, migrates it fresh,
 * and seeds deterministic completed audio/video/no-speech fixtures plus
 * processing and failed transcriptions. Writes
 * verification/p4-006-fixtures.json for the Playwright final-gate harness.
 *
 * Not application code. Dedicated local DB only; never production data.
 */

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionFailure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('migrate:fresh', ['--force' => true]);

$fixturesDir = __DIR__.'/fixtures';
@mkdir($fixturesDir, 0777, true);

function ensure_fixture(string $path, string $ffmpegArgs): void
{
    if (is_file($path) && filesize($path) > 0) {
        return;
    }

    exec('ffmpeg -y -hide_banner -loglevel error '.$ffmpegArgs.' '.escapeshellarg($path), $output, $exit);
    if ($exit !== 0 || ! is_file($path)) {
        fwrite(STDERR, "Failed to generate fixture: {$path}\n");
        exit(1);
    }
}

ensure_fixture($fixturesDir.'/p4-006-audio.wav', '-f lavfi -i "sine=frequency=440:duration=25" -c:a pcm_s16le');
ensure_fixture($fixturesDir.'/p4-006-video.webm', '-f lavfi -i "color=c=blue:s=320x240:r=15:d=25" -f lavfi -i "sine=frequency=440:duration=25" -shortest -c:v libvpx-vp9 -b:v 250k -c:a libopus');

$disk = Storage::disk((string) config('media.storage_disk'));

function make_user(string $name, string $email): User
{
    return User::factory()->create([
        'name' => $name,
        'email' => $email,
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
}

function make_media(User $user, string $fixtureFile, string $extension, string $mime, MediaType $type): MediaFile
{
    $uuid = (string) Str::uuid();
    $storageFilename = Str::random(40).'.'.$extension;
    $storagePath = 'media/'.$uuid.'/'.$storageFilename;
    $bytes = file_get_contents($fixtureFile);

    Storage::disk((string) config('media.storage_disk'))->put($storagePath, $bytes);

    return MediaFile::factory()->create([
        'user_id' => $user->id,
        'uuid' => $uuid,
        'original_filename' => basename($fixtureFile),
        'storage_filename' => $storageFilename,
        'storage_path' => $storagePath,
        'media_type' => $type,
        'mime_type' => $mime,
        'extension' => $extension,
        'file_size_bytes' => strlen($bytes),
        'duration_seconds' => 25,
        'status' => MediaStatus::Ready,
    ]);
}

$owner = make_user('P4-006 Owner', 'p4-006@example.test');
$other = make_user('P4-006 Other', 'p4-006-other@example.test');

$rows = [
    [0, 0.500, 4.000, 'en', 'First segment'],
    [1, 4.000, 8.250, 'ms', 'Segmen kedua'],
    [2, 12.345, 16.500, 'zh', '第三段'],
    [3, 16.500, 18.000, 'ta', 'நான்காம்'],
    [4, 18.000, 19.500, 'und', 'Undetermined text'],
    [5, 19.500, 21.000, 'en', 'Sixth segment'],
    [6, 21.000, 22.500, 'ms', 'Segmen keenam'],
    [7, 22.500, 24.000, 'zh', '第八段'],
];

function make_transcription(User $user, MediaFile $media, string $title, array $rows, bool $speech = true): Transcription
{
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'title' => $title,
        'language' => 'en',
        'detected_language' => $speech ? 'en' : 'und',
        'model' => 'large-v3',
        'speech_detected' => $speech,
        'full_text' => $speech ? implode("\n", array_column($rows, 4)) : '',
    ]);

    foreach ($rows as [$index, $start, $end, $language, $text]) {
        $transcription->segments()->create([
            'segment_index' => $index,
            'start_seconds' => $start,
            'end_seconds' => $end,
            'language' => $language,
            'text' => $text,
        ]);
    }

    return $transcription;
}

$audioMedia = make_media($owner, $fixturesDir.'/p4-006-audio.wav', 'wav', 'audio/wav', MediaType::Audio);
$videoMedia = make_media($owner, $fixturesDir.'/p4-006-video.webm', 'webm', 'video/webm', MediaType::Video);

$audio = make_transcription($owner, $audioMedia, 'P4-006 audio fixture', $rows);
$video = make_transcription($owner, $videoMedia, 'P4-006 video fixture', $rows);

// Processing fixture (no segments, transcribing).
$processingMedia = make_media($owner, $fixturesDir.'/p4-006-audio.wav', 'wav', 'audio/wav', MediaType::Audio);
$processing = Transcription::factory()->transcribing()->create([
    'user_id' => $owner->id,
    'media_file_id' => $processingMedia->id,
    'title' => 'P4-006 processing fixture',
    'speech_detected' => null,
    'full_text' => null,
]);

// Failed fixture (no segments) with a retryable failed attempt.
$failedMedia = make_media($owner, $fixturesDir.'/p4-006-audio.wav', 'wav', 'audio/wav', MediaType::Audio);
$failed = Transcription::factory()->failed()->create([
    'user_id' => $owner->id,
    'media_file_id' => $failedMedia->id,
    'title' => 'P4-006 failed fixture',
    'error_message' => 'Worker timed out.',
]);
ProcessingJob::query()->create([
    'transcription_id' => $failed->id,
    'stage' => ProcessingStage::Transcribe,
    'status' => ProcessingStatus::Failed,
    'progress_percentage' => 0,
    'failure_code' => TranscriptionFailure::WorkerTimeout,
    'error_message' => 'Worker timed out.',
    'started_at' => now()->subMinutes(5),
    'completed_at' => now()->subMinutes(4),
]);

// No-speech fixture.
$noSpeechMedia = make_media($owner, $fixturesDir.'/p4-006-audio.wav', 'wav', 'audio/wav', MediaType::Audio);
$noSpeech = Transcription::factory()->completed()->create([
    'user_id' => $owner->id,
    'media_file_id' => $noSpeechMedia->id,
    'title' => 'P4-006 no-speech fixture',
    'language' => 'en',
    'detected_language' => 'und',
    'model' => 'large-v3',
    'speech_detected' => false,
    'full_text' => '',
]);

$samples = [
    ['time' => 0.500, 'expected' => 0],
    ['time' => 3.999, 'expected' => 0],
    ['time' => 4.000, 'expected' => 1],
    ['time' => 8.249, 'expected' => 1],
    ['time' => 8.250, 'expected' => null],
    ['time' => 10.000, 'expected' => null],
    ['time' => 12.344, 'expected' => null],
    ['time' => 12.345, 'expected' => 2],
    ['time' => 16.499, 'expected' => 2],
    ['time' => 16.500, 'expected' => 3],
    ['time' => 24.000, 'expected' => null],
];

$payload = [
    'baseUrl' => 'http://127.0.0.1:8123',
    'email' => 'p4-006@example.test',
    'password' => 'password',
    'otherEmail' => 'p4-006-other@example.test',
    'audio' => [
        'transcriptionId' => $audio->id,
        'uuid' => $audioMedia->uuid,
        'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3], 'text' => $r[4]], $rows),
    ],
    'video' => [
        'transcriptionId' => $video->id,
        'uuid' => $videoMedia->uuid,
        'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3], 'text' => $r[4]], $rows),
    ],
    'processing' => ['transcriptionId' => $processing->id],
    'failed' => ['transcriptionId' => $failed->id],
    'noSpeech' => ['transcriptionId' => $noSpeech->id, 'uuid' => $noSpeechMedia->uuid],
    'copyFullText' => implode("\n", array_column($rows, 4)),
    'search' => [
        'latin' => ['query' => 'segmen', 'expected' => 4],
        'chinese' => ['query' => '第三', 'expected' => 1],
        'tamil' => ['query' => 'நான்காம்', 'expected' => 1],
    ],
    'samples' => $samples,
];

file_put_contents(__DIR__.'/p4-006-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Seeded P4-006 verification fixtures.\n");
fwrite(STDOUT, 'Audio: '.$audio->id.' Video: '.$video->id.' Processing: '.$processing->id.' Failed: '.$failed->id.' NoSpeech: '.$noSpeech->id."\n");
fwrite(STDOUT, 'Other user: '.$other->id."\n");
