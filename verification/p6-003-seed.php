<?php

/**
 * P6-003 browser-verification fixture seeder (verification tooling).
 *
 * Boots Laravel against a dedicated verification database (select it with the
 * DB_DATABASE environment variable), migrates it fresh, and seeds deterministic
 * completed transcriptions for the text-editing + undo/redo browser harness:
 *
 * - `main`      3 multilingual segments, real player (edit / save / cancel /
 *               reload durability / ownership denial / machine immutability);
 * - `conflict`  3 segments, real player (stale-write conflict from two pages);
 * - `history`   3 segments, real player (undo / redo / branch-after-undo).
 *
 * Writes verification/p6-003-fixtures.json for the Playwright harness.
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

ensure_fixture($fixturesDir.'/p6-003-audio.wav', '-f lavfi -i "sine=frequency=440:duration=15" -c:a pcm_s16le');

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

$owner = make_user('P6-003 Owner', 'p6-003@example.test');
$intruder = make_user('P6-003 Intruder', 'p6-003-intruder@example.test');

$rows = [
    [0, 0.500, 4.000, 'en', 'Original first'],
    [1, 4.000, 8.000, 'ms', 'Original kedua'],
    [2, 8.000, 12.000, 'zh', '原始第三段'],
];

$mainMedia = make_media($owner, $fixturesDir.'/p6-003-audio.wav');
$main = make_transcription($owner, $mainMedia, 'P6-003 main fixture', $rows);

$cancelMedia = make_media($owner, $fixturesDir.'/p6-003-audio.wav');
$cancel = make_transcription($owner, $cancelMedia, 'P6-003 cancel fixture', $rows);

$conflictMedia = make_media($owner, $fixturesDir.'/p6-003-audio.wav');
$conflict = make_transcription($owner, $conflictMedia, 'P6-003 conflict fixture', $rows);

$historyMedia = make_media($owner, $fixturesDir.'/p6-003-audio.wav');
$history = make_transcription($owner, $historyMedia, 'P6-003 history fixture', $rows);

$branchMedia = make_media($owner, $fixturesDir.'/p6-003-audio.wav');
$branch = make_transcription($owner, $branchMedia, 'P6-003 branch fixture', $rows);

$immutableMedia = make_media($owner, $fixturesDir.'/p6-003-audio.wav');
$immutable = make_transcription($owner, $immutableMedia, 'P6-003 immutable fixture', $rows);

$payload = [
    'baseUrl' => 'http://127.0.0.1:8124',
    'email' => 'p6-003@example.test',
    'intruderEmail' => 'p6-003-intruder@example.test',
    'password' => 'password',
    'segments' => array_map(fn (array $r) => ['index' => $r[0], 'start' => $r[1], 'end' => $r[2], 'language' => $r[3], 'text' => $r[4]], $rows),
    'main' => ['transcriptionId' => $main->id],
    'cancel' => ['transcriptionId' => $cancel->id],
    'conflict' => ['transcriptionId' => $conflict->id],
    'history' => ['transcriptionId' => $history->id],
    'branch' => ['transcriptionId' => $branch->id],
    'immutable' => ['transcriptionId' => $immutable->id],
];

file_put_contents(__DIR__.'/p6-003-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Seeded P6-003 verification fixtures.\n");
fwrite(STDOUT, 'Main: '.$main->id.' Cancel: '.$cancel->id.' Conflict: '.$conflict->id.' History: '.$history->id.' Branch: '.$branch->id.' Immutable: '.$immutable->id."\n");
