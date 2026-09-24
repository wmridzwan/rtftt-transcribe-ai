<?php

/**
 * P6-005 browser-verification fixture seeder (verification tooling).
 *
 * Boots Laravel against a dedicated verification database (select it with the
 * DB_DATABASE environment variable), migrates it fresh, and seeds deterministic
 * completed transcriptions for the split/merge + translation-invalidation
 * browser harness:
 *
 * - `main`       real player, completed translation (valid split / reload
 *                durability / invalidation visible / machine unchanged);
 * - `merge`      real player, mixed-language segments (valid adjacent merge);
 * - `boundary`   real player (split at a boundary rejected);
 * - `nonadjacent` real player (non-adjacent merge rejected);
 * - `conflict`   real player (stale structural conflict from two pages);
 * - `cancel`     real player (cancel discards structural selection);
 * - `immutable`  real player (machine source unchanged; P6-007 non-alignment).
 *
 * Writes verification/p6-005-fixtures.json for the Playwright harness.
 *
 * Not application code. Dedicated local DB only; never production data.
 */

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\Translation;
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

ensure_fixture($fixturesDir.'/p6-005-audio.wav', '-f lavfi -i "sine=frequency=440:duration=15" -c:a pcm_s16le');

function make_user(string $name, string $email): User
{
    return User::factory()->create([
        'name' => $name,
        'email' => $email,
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
}

function make_media(User $user, string $fixtureFile): MediaFile
{
    $uuid = (string) Str::uuid();
    $storageFilename = Str::random(40).'.wav';
    $storagePath = 'media/'.$uuid.'/'.$storageFilename;

    $bytes = file_get_contents($fixtureFile);

    Storage::disk((string) config('media.storage_disk'))->put($storagePath, $bytes);

    return MediaFile::factory()->create([
        'user_id' => $user->id,
        'uuid' => $uuid,
        'original_filename' => basename($fixtureFile),
        'storage_filename' => $storageFilename,
        'storage_path' => $storagePath,
        'media_type' => MediaType::Audio,
        'mime_type' => 'audio/wav',
        'extension' => 'wav',
        'file_size_bytes' => strlen($bytes),
        'duration_seconds' => 15,
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

$owner = make_user('P6-005 Owner', 'p6-005@example.test');
$intruder = make_user('P6-005 Intruder', 'p6-005-intruder@example.test');

$rows = [
    [0, 0.500, 4.000, 'en', 'Original first'],
    [1, 4.000, 8.000, 'ms', 'Original kedua'],
    [2, 8.000, 12.000, 'zh', '原始第三段'],
];

$make = function (string $title) use ($owner, $fixturesDir, $rows): Transcription {
    $media = make_media($owner, $fixturesDir.'/p6-005-audio.wav');

    return make_transcription($owner, $media, $title, $rows);
};

$fixtures = [
    'main' => $make('P6-005 main fixture'),
    'merge' => $make('P6-005 merge fixture'),
    'boundary' => $make('P6-005 boundary fixture'),
    'nonadjacent' => $make('P6-005 non-adjacent fixture'),
    'conflict' => $make('P6-005 conflict fixture'),
    'cancel' => $make('P6-005 cancel fixture'),
    'immutable' => $make('P6-005 immutable fixture'),
];

// A persisted completed translation on `main` so the structural invalidation is
// surfaced as a stale historical output.
$translation = Translation::factory()->completed()->create([
    'transcription_id' => $fixtures['main']->id,
    'target_language' => 'zh',
    'source_language' => 'en',
]);

foreach ($rows as [$index, $start, $end, $language, $text]) {
    $translation->segments()->create([
        'segment_index' => $index,
        'start_seconds' => $start,
        'end_seconds' => $end,
        'text' => 'translated '.$index,
        'source_language' => $language,
    ]);
}

$payload = [
    'baseUrl' => 'http://127.0.0.1:8127',
    'email' => 'p6-005@example.test',
    'intruderEmail' => 'p6-005-intruder@example.test',
    'password' => 'password',
    'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3], 'text' => $r[4]], $rows),
];

foreach ($fixtures as $key => $transcription) {
    $payload[$key] = ['transcriptionId' => $transcription->id];
}

file_put_contents(__DIR__.'/p6-005-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Seeded P6-005 verification fixtures.\n");
foreach ($fixtures as $key => $transcription) {
    fwrite(STDOUT, $key.': '.$transcription->id."\n");
}
