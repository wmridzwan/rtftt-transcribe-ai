<?php

/**
 * P4-003 browser-verification fixture seeder (verification tooling).
 *
 * Boots Laravel against a dedicated verification database, migrates it fresh,
 * creates a verified owner, and seeds deterministic audio/video/no-speech
 * fixtures with completed persisted transcriptions and segments. Writes
 * verification/p4-003-fixtures.json for the Playwright harness.
 *
 * Not application code. Run against a dedicated DB only; never production data.
 */

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;
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

    $command = 'ffmpeg -y -hide_banner -loglevel error '.$ffmpegArgs.' '.escapeshellarg($path);
    exec($command, $output, $exit);
    if ($exit !== 0 || ! is_file($path)) {
        fwrite(STDERR, "Failed to generate fixture: {$path}\n");
        exit(1);
    }
}

ensure_fixture(
    $fixturesDir.'/p4-003-audio.wav',
    '-f lavfi -i "sine=frequency=440:duration=25" -c:a pcm_s16le',
);
ensure_fixture(
    $fixturesDir.'/p4-003-video.webm',
    '-f lavfi -i "color=c=blue:s=320x240:r=15:d=25" -f lavfi -i "sine=frequency=440:duration=25" -shortest -c:v libvpx-vp9 -b:v 250k -c:a libopus',
);

$disk = Storage::disk((string) config('media.storage_disk'));

$user = User::factory()->create([
    'name' => 'P4-003 Verifier',
    'email' => 'p4-003@example.test',
    'password' => 'password',
    'email_verified_at' => now(),
]);

/**
 * @param  array<int, array{0:int,1:float,2:float,3:string,4:string}>  $rows
 */
function seed_transcription(
    User $user,
    string $title,
    string $fixtureFile,
    string $extension,
    string $mime,
    MediaType $type,
    array $rows,
    bool $speechDetected = true,
): Transcription {
    $uuid = (string) Str::uuid();
    $storageFilename = Str::random(40).'.'.$extension;
    $storagePath = 'media/'.$uuid.'/'.$storageFilename;
    $bytes = file_get_contents($fixtureFile);

    Storage::disk((string) config('media.storage_disk'))->put($storagePath, $bytes);

    $media = MediaFile::factory()->create([
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

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'title' => $title,
        'language' => 'en',
        'detected_language' => 'en',
        'model' => 'large-v3',
        'speech_detected' => $speechDetected,
        'full_text' => $speechDetected ? 'Seeded transcript text.' : '',
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

$rows = [
    [0, 0.500, 4.000, 'en', 'First segment'],
    [1, 4.000, 8.250, 'ms', 'Segmen kedua'],
    [2, 12.345, 16.500, 'zh', '第三段'],
    [3, 16.500, 18.000, 'ta', 'நான்காம்'],
    [4, 18.000, 19.500, 'en', 'Fifth segment'],
    [5, 19.500, 21.000, 'ms', 'Segmen keenam'],
    [6, 21.000, 22.500, 'zh', '第七段'],
    [7, 22.500, 24.000, 'und', 'Eighth segment'],
];

$audio = seed_transcription(
    $user, 'P4-003 audio fixture', $fixturesDir.'/p4-003-audio.wav',
    'wav', 'audio/wav', MediaType::Audio, $rows,
);
$video = seed_transcription(
    $user, 'P4-003 video fixture', $fixturesDir.'/p4-003-video.webm',
    'webm', 'video/webm', MediaType::Video, $rows,
);

$noSpeechMediaUuid = (string) Str::uuid();
$noSpeechStorageFilename = Str::random(40).'.wav';
$noSpeechStoragePath = 'media/'.$noSpeechMediaUuid.'/'.$noSpeechStorageFilename;
$disk->put($noSpeechStoragePath, file_get_contents($fixturesDir.'/p4-003-audio.wav'));
$noSpeechMedia = MediaFile::factory()->create([
    'user_id' => $user->id,
    'uuid' => $noSpeechMediaUuid,
    'original_filename' => 'p4-003-no-speech.wav',
    'storage_filename' => $noSpeechStorageFilename,
    'storage_path' => $noSpeechStoragePath,
    'media_type' => MediaType::Audio,
    'mime_type' => 'audio/wav',
    'extension' => 'wav',
    'file_size_bytes' => filesize($fixturesDir.'/p4-003-audio.wav'),
    'duration_seconds' => 25,
    'status' => MediaStatus::Ready,
]);
$noSpeech = Transcription::factory()->completed()->create([
    'user_id' => $user->id,
    'media_file_id' => $noSpeechMedia->id,
    'title' => 'P4-003 no-speech fixture',
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
    ['time' => 25.000, 'expected' => null],
];

$payload = [
    'baseUrl' => 'http://127.0.0.1:8123',
    'email' => 'p4-003@example.test',
    'password' => 'password',
    'audio' => [
        'transcriptionId' => $audio->id,
        'uuid' => $audio->mediaFile->uuid,
        'mime' => 'audio/wav',
        'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3]], $rows),
    ],
    'video' => [
        'transcriptionId' => $video->id,
        'uuid' => $video->mediaFile->uuid,
        'mime' => 'video/webm',
        'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3]], $rows),
    ],
    'noSpeech' => [
        'transcriptionId' => $noSpeech->id,
        'uuid' => $noSpeechMedia->uuid,
    ],
    'samples' => $samples,
];

file_put_contents(__DIR__.'/p4-003-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Seeded P4-003 verification fixtures.\n");
fwrite(STDOUT, 'Audio transcription id: '.$audio->id."\n");
fwrite(STDOUT, 'Video transcription id: '.$video->id."\n");
fwrite(STDOUT, 'No-speech transcription id: '.$noSpeech->id."\n");
