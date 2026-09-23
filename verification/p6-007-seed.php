<?php

/**
 * P6-007 browser-verification fixture seeder (verification tooling).
 *
 * Boots Laravel against a dedicated verification database (select it with the
 * DB_DATABASE environment variable), migrates it fresh, and seeds deterministic
 * completed transcriptions plus persisted Phase 5 translations for the
 * presentation-only source / revision / translation comparison harness:
 *
 * - `plain`       machine source only + completed translation (toggle, alignment);
 * - `edited`      edited active revision + completed translation (mismatch note);
 * - `identical`   machine-materialized initial revision + translation (aligned);
 * - `none`        edited active revision, no translation (no-translation state);
 * - `structural`  structurally changed revision + translation (alignment unavailable).
 *
 * Writes verification/p6-007-fixtures.json for the Playwright harness.
 *
 * Not application code. Dedicated local DB only; never production data.
 */

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Editing\RevisionService;
use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\Translation;
use App\Models\User;
use App\Transcription\LanguageIdentifier;
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

ensure_fixture($fixturesDir.'/p6-007-audio.wav', '-f lavfi -i "sine=frequency=440:duration=15" -c:a pcm_s16le');

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

function add_translation(Transcription $transcription, string $target, array $translatedTexts): Translation
{
    $translation = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => $target,
        'source_language' => 'en',
    ]);

    foreach ($transcription->segments()->orderBy('segment_index')->get() as $index => $segment) {
        $translation->segments()->create([
            'segment_index' => $segment->segment_index,
            'start_seconds' => $segment->start_seconds,
            'end_seconds' => $segment->end_seconds,
            'text' => $translatedTexts[$index] ?? '',
            'source_language' => $segment->language->value,
        ]);
    }

    return $translation;
}

function materialize_and_edit(Transcription $transcription, User $owner, array $texts): void
{
    $service = app(RevisionService::class);
    $initial = $service->materializeInitial($owner, $transcription);

    $segments = [];
    foreach ($initial->segments as $segment) {
        $segments[] = new RevisionSegmentData(
            identity: $segment->identity,
            position: $segment->position,
            startSeconds: $segment->startSeconds,
            endSeconds: $segment->endSeconds,
            text: $texts[$segment->position] ?? $segment->text,
            language: $segment->language,
        );
    }

    $service->edit($owner, $transcription, $initial->revisionId, $segments);
}

$owner = make_user('P6-007 Owner', 'p6-007@example.test');
$intruder = make_user('P6-007 Intruder', 'p6-007-intruder@example.test');

$rows = [
    [0, 0.500, 4.000, 'en', 'Machine first'],
    [1, 4.000, 8.000, 'ms', 'Machine kedua'],
    [2, 8.000, 12.000, 'zh', '机器第三'],
];

$translated = ['Translated first', 'Terjemahan kedua', '翻译第三'];
$edited = ['Edited revision first', 'Edited revision kedua', '已编辑第三'];

// plain: machine source only + translation.
$plain = make_transcription($owner, make_media($owner, $fixturesDir.'/p6-007-audio.wav'), 'P6-007 plain fixture', $rows);
add_translation($plain, 'zh', $translated);

// edited: edited active revision + translation (mismatch).
$editedT = make_transcription($owner, make_media($owner, $fixturesDir.'/p6-007-audio.wav'), 'P6-007 edited fixture', $rows);
add_translation($editedT, 'zh', $translated);
materialize_and_edit($editedT, $owner, $edited);

// identical: machine-materialized initial revision only + translation.
$identical = make_transcription($owner, make_media($owner, $fixturesDir.'/p6-007-audio.wav'), 'P6-007 identical fixture', $rows);
add_translation($identical, 'zh', $translated);
app(RevisionService::class)->materializeInitial($owner, $identical);

// none: edited active revision, no translation.
$none = make_transcription($owner, make_media($owner, $fixturesDir.'/p6-007-audio.wav'), 'P6-007 no-translation fixture', $rows);
materialize_and_edit($none, $owner, $edited);

// structural: structurally changed revision + translation (alignment unavailable).
$structural = make_transcription($owner, make_media($owner, $fixturesDir.'/p6-007-audio.wav'), 'P6-007 structural fixture', $rows);
add_translation($structural, 'zh', $translated);
$service = app(RevisionService::class);
$initial = $service->materializeInitial($owner, $structural);
$service->edit($owner, $structural, $initial->revisionId, [
    new RevisionSegmentData(RevisionSegmentIdentity::fromString('new-0'), 0, 0.5, 4.0, 'Restructured A', LanguageIdentifier::English),
    new RevisionSegmentData(RevisionSegmentIdentity::fromString('new-1'), 1, 4.0, 8.0, 'Restructured B', LanguageIdentifier::Malay),
]);

$payload = [
    'baseUrl' => 'http://127.0.0.1:8125',
    'email' => 'p6-007@example.test',
    'intruderEmail' => 'p6-007-intruder@example.test',
    'password' => 'password',
    'machineTexts' => array_column($rows, 4),
    'translatedTexts' => $translated,
    'editedTexts' => $edited,
    'plain' => ['transcriptionId' => $plain->id],
    'edited' => ['transcriptionId' => $editedT->id],
    'identical' => ['transcriptionId' => $identical->id],
    'none' => ['transcriptionId' => $none->id],
    'structural' => ['transcriptionId' => $structural->id],
];

file_put_contents(__DIR__.'/p6-007-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

fwrite(STDOUT, "Seeded P6-007 verification fixtures.\n");
fwrite(STDOUT, 'Plain: '.$plain->id.' Edited: '.$editedT->id.' Identical: '.$identical->id.' None: '.$none->id.' Structural: '.$structural->id."\n");
