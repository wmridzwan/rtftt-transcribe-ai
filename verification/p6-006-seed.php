<?php

/**
 * P6-006 browser-verification fixture seeder (verification tooling).
 *
 * Boots Laravel against a dedicated verification database (select it with the
 * DB_DATABASE environment variable), migrates it fresh, and seeds deterministic
 * completed transcriptions that exercise advanced navigation and language
 * filtering:
 *
 * - `filter`:  8 multilingual segments (en/ms/zh/ta/und) with a real player;
 * - `overlap`: overlapping and zero-length segment timings with a real player;
 * - `noMedia`: segments but no physical media (no player) for no-media feedback.
 *
 * Writes verification/p6-006-fixtures.json for the Playwright harness.
 *
 * Not application code. Dedicated local DB only; never production data.
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

    exec('ffmpeg -y -hide_banner -loglevel error '.$ffmpegArgs.' '.escapeshellarg($path), $output, $exit);
    if ($exit !== 0 || ! is_file($path)) {
        fwrite(STDERR, "Failed to generate fixture: {$path}\n");
        exit(1);
    }
}

ensure_fixture($fixturesDir.'/p6-006-audio.wav', '-f lavfi -i "sine=frequency=440:duration=25" -c:a pcm_s16le');

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

function make_media(User $user, ?string $fixtureFile, string $extension, string $mime, MediaType $type, bool $withBytes = true): MediaFile
{
    $uuid = (string) Str::uuid();
    $storageFilename = Str::random(40).'.'.$extension;
    $storagePath = 'media/'.$uuid.'/'.$storageFilename;

    $bytes = $withBytes && $fixtureFile !== null ? file_get_contents($fixtureFile) : '';

    if ($withBytes) {
        Storage::disk((string) config('media.storage_disk'))->put($storagePath, $bytes);
    }

    return MediaFile::factory()->create([
        'user_id' => $user->id,
        'uuid' => $uuid,
        'original_filename' => $fixtureFile !== null ? basename($fixtureFile) : 'missing.'.$extension,
        'storage_filename' => $storageFilename,
        'storage_path' => $storagePath,
        'media_type' => $type,
        'mime_type' => $mime,
        'extension' => $extension,
        'file_size_bytes' => $withBytes ? strlen($bytes) : 1024,
        'duration_seconds' => 25,
        'status' => MediaStatus::Ready,
    ]);
}

function make_transcription(User $user, MediaFile $media, string $title, array $rows): Transcription
{
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'title' => $title,
        'language' => 'en',
        'detected_language' => 'en',
        'model' => 'large-v3',
        'speech_detected' => true,
        'full_text' => implode("\n", array_column($rows, 4)),
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

$owner = make_user('P6-006 Owner', 'p6-006@example.test');

// 1. Language-filter fixture: 8 segments across all five languages.
$filterRows = [
    [0, 0.500, 4.000, 'en', 'First segment'],
    [1, 4.000, 8.250, 'ms', 'Segmen kedua'],
    [2, 12.345, 16.500, 'zh', '第三段'],
    [3, 16.500, 18.000, 'ta', 'நான்காம்'],
    [4, 18.000, 19.500, 'und', 'Undetermined text'],
    [5, 19.500, 21.000, 'en', 'Sixth segment'],
    [6, 21.000, 22.500, 'ms', 'Segmen keenam'],
    [7, 22.500, 24.000, 'zh', '第八段'],
];

$filterMedia = make_media($owner, $fixturesDir.'/p6-006-audio.wav', 'wav', 'audio/wav', MediaType::Audio);
$filter = make_transcription($owner, $filterMedia, 'P6-006 filter fixture', $filterRows);

// 2. Overlap fixture: segment 1 starts inside segment 0; segment 4 is zero-length.
$overlapRows = [
    [0, 0.500, 6.000, 'en', 'Overlap zero'],
    [1, 3.000, 9.000, 'ms', 'Overlap one'],
    [2, 9.000, 12.000, 'zh', 'Overlap two'],
    [3, 12.000, 15.000, 'ta', 'Overlap three'],
    [4, 15.000, 15.000, 'und', 'Zero length'],
];

$overlapMedia = make_media($owner, $fixturesDir.'/p6-006-audio.wav', 'wav', 'audio/wav', MediaType::Audio);
$overlap = make_transcription($owner, $overlapMedia, 'P6-006 overlap fixture', $overlapRows);

// 3. No-media fixture: segments exist but the private object is absent.
$noMediaRows = [
    [0, 0.000, 5.000, 'en', 'No media one'],
    [1, 5.000, 10.000, 'ms', 'No media two'],
    [2, 10.000, 15.000, 'zh', 'No media three'],
];

$noMediaMedia = make_media($owner, null, 'wav', 'audio/wav', MediaType::Audio, withBytes: false);
$noMedia = make_transcription($owner, $noMediaMedia, 'P6-006 no-media fixture', $noMediaRows);

$payload = [
    'baseUrl' => 'http://127.0.0.1:8123',
    'email' => 'p6-006@example.test',
    'password' => 'password',
    'filter' => [
        'transcriptionId' => $filter->id,
        'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3], 'text' => $r[4]], $filterRows),
    ],
    'overlap' => [
        'transcriptionId' => $overlap->id,
        'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3], 'text' => $r[4]], $overlapRows),
    ],
    'noMedia' => [
        'transcriptionId' => $noMedia->id,
        'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3], 'text' => $r[4]], $noMediaRows),
    ],
    'copyFullText' => implode("\n", array_column($filterRows, 4)),
];

file_put_contents(__DIR__.'/p6-006-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Seeded P6-006 verification fixtures.\n");
fwrite(STDOUT, 'Filter: '.$filter->id.' Overlap: '.$overlap->id.' NoMedia: '.$noMedia->id."\n");
