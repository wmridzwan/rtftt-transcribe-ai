<?php

/**
 * P6-009 FINAL_GATE_ONLY browser-verification fixture seeder.
 *
 * Boots Laravel against a dedicated verification database (select it with the
 * DB_DATABASE environment variable), migrates it fresh, and seeds deterministic
 * completed transcriptions for the final integration gate harness:
 *
 * - `plain`     machine source only, no revisions (machine-source state);
 * - `linear`    v1 initial + v2 text edit, v2 active (list, active marker,
 *               comparison no-translation state, export links);
 * - `branched`  v1 + v2, undo to v1, v3 branch (sibling activation,
 *               activation reload durability);
 * - `branched2` same shape as branched (stale-base conflict presentation);
 * - `stale`     completed translation + structural split (persisted
 *               staleness-cause marker + comparison mismatch note).
 *
 * Writes verification/p6-009-fixtures.json for the Playwright harness.
 *
 * Not application code. Dedicated local DB only; never production data.
 */

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionService;
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

function p6009_user(string $name, string $email): User
{
    return User::factory()->create([
        'name' => $name,
        'email' => $email,
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
}

function p6009_media(User $user, string $fixtureFile): MediaFile
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

function p6009_transcription(User $user, MediaFile $media, string $title, array $rows): Transcription
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

function p6009_edit_texts(Transcription $transcription, User $owner, string $baseRevisionId, array $texts): void
{
    $service = app(RevisionService::class);
    $baseRevision = null;

    foreach ($service->history($owner, $transcription) as $revision) {
        if ($revision->revisionId === $baseRevisionId) {
            $baseRevision = $revision;
        }
    }

    if ($baseRevision === null) {
        fwrite(STDERR, "Unknown base revision {$baseRevisionId}\n");
        exit(1);
    }

    $segments = [];

    foreach ($baseRevision->segments as $segment) {
        $segments[] = new RevisionSegmentData(
            identity: $segment->identity,
            position: $segment->position,
            startSeconds: $segment->startSeconds,
            endSeconds: $segment->endSeconds,
            text: $texts[$segment->position] ?? $segment->text,
            language: $segment->language,
        );
    }

    $service->edit($owner, $transcription, $baseRevisionId, $segments);
}

function p6009_branched(User $owner, string $audio, string $title, array $rows, array $edited, array $branched): Transcription
{
    $transcription = p6009_transcription($owner, p6009_media($owner, $audio), $title, $rows);
    $service = app(RevisionService::class);
    $v1 = $service->materializeInitial($owner, $transcription);
    p6009_edit_texts($transcription, $owner, $v1->revisionId, $edited);
    $service->undo($owner, $transcription, $v1->revisionId);
    p6009_edit_texts($transcription, $owner, $v1->revisionId, $branched);

    return $transcription;
}

$owner = p6009_user('P6-009 Owner', 'p6-009@example.test');
$intruder = p6009_user('P6-009 Intruder', 'p6-009-intruder@example.test');

$rows = [
    [0, 0.500, 4.000, 'en', 'Machine first'],
    [1, 4.000, 8.000, 'ms', 'Machine kedua'],
    [2, 8.000, 12.000, 'zh', '机器第三'],
];

$edited = ['Edited revision first', 'Edited revision kedua', '已编辑第三'];
$branched = ['Branch revision first', 'Branch revision kedua', '分支第三'];

$audio = $fixturesDir.'/p6-007-audio.wav';
$service = app(RevisionService::class);

// plain: machine source only, no revisions.
$plain = p6009_transcription($owner, p6009_media($owner, $audio), 'P6-009 plain fixture', $rows);

// linear: v1 initial + v2 text edit, v2 active.
$linear = p6009_transcription($owner, p6009_media($owner, $audio), 'P6-009 linear fixture', $rows);
$linearV1 = $service->materializeInitial($owner, $linear);
p6009_edit_texts($linear, $owner, $linearV1->revisionId, $edited);

// branched: v1 + v2, undo to v1, v3 branch (v3 active; v2 is a sibling).
$branchedT = p6009_branched($owner, $audio, 'P6-009 branched fixture', $rows, $edited, $branched);

// branched2: same shape, reserved for the stale-base conflict check.
$branched2 = p6009_branched($owner, $audio, 'P6-009 conflict fixture', $rows, $edited, $branched);

// stale: completed translation + structural edit (persisted staleness cause).
$stale = p6009_transcription($owner, p6009_media($owner, $audio), 'P6-009 stale fixture', $rows);
Translation::factory()->completed()->create([
    'transcription_id' => $stale->getKey(),
    'target_language' => 'ms',
    'source_language' => 'en',
]);
$staleV1 = $service->materializeInitial($owner, $stale);
$service->split($owner, $stale, $staleV1->revisionId, 'machine:0', 2.0, 4);

$payload = [
    'baseUrl' => 'http://127.0.0.1:8129',
    'email' => 'p6-009@example.test',
    'intruderEmail' => 'p6-009-intruder@example.test',
    'password' => 'password',
    'machineTexts' => array_column($rows, 4),
    'editedTexts' => $edited,
    'branchedTexts' => $branched,
    'plain' => ['transcriptionId' => $plain->id],
    'linear' => ['transcriptionId' => $linear->id],
    'branched' => ['transcriptionId' => $branchedT->id],
    'branched2' => ['transcriptionId' => $branched2->id],
    'stale' => ['transcriptionId' => $stale->id],
];

file_put_contents(__DIR__.'/p6-009-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

fwrite(STDOUT, "Seeded P6-009 verification fixtures.\n");
fwrite(STDOUT, 'Plain: '.$plain->id.' Linear: '.$linear->id.' Branched: '.$branchedT->id.' Conflict: '.$branched2->id.' Stale: '.$stale->id."\n");
