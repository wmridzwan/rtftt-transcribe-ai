<?php

/**
 * P7-006 CSP workspace fixture seeder (verification tooling).
 *
 * Boots Laravel against a dedicated verification database, migrates it
 * fresh (incl. the P7-006 scan-verdict columns), and seeds one user plus
 * one completed audio transcription with two segments. Writes
 * verification/p7-006-fixtures.json.
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

$owner = User::factory()->create([
    'name' => 'P7-006 Owner',
    'email' => 'p7-006@example.test',
    'password' => 'password',
    'email_verified_at' => now(),
]);

$fixtureFile = __DIR__.'/fixtures/p4-006-audio.wav';
$bytes = file_get_contents($fixtureFile);
$uuid = (string) Str::uuid();
$storageFilename = Str::random(40).'.wav';
$storagePath = 'media/'.$uuid.'/'.$storageFilename;

Storage::disk((string) config('media.storage_disk'))->put($storagePath, $bytes);

$media = MediaFile::factory()->create([
    'user_id' => $owner->id,
    'uuid' => $uuid,
    'original_filename' => 'p7-006-audio.wav',
    'storage_filename' => $storageFilename,
    'storage_path' => $storagePath,
    'media_type' => MediaType::Audio,
    'mime_type' => 'audio/wav',
    'extension' => 'wav',
    'file_size_bytes' => strlen($bytes),
    'duration_seconds' => 25,
    'status' => MediaStatus::Ready,
]);

$transcription = Transcription::factory()->completed()->create([
    'user_id' => $owner->id,
    'media_file_id' => $media->id,
    'title' => 'P7-006 CSP workspace fixture',
    'language' => 'en',
    'detected_language' => 'en',
    'model' => 'large-v3',
    'speech_detected' => true,
    'full_text' => "First segment\nSecond segment",
]);

foreach ([[0, 0.500, 4.000, 'en', 'First segment'], [1, 4.000, 8.250, 'en', 'Second segment']] as [$index, $start, $end, $language, $text]) {
    $transcription->segments()->create([
        'segment_index' => $index,
        'start_seconds' => $start,
        'end_seconds' => $end,
        'language' => $language,
        'text' => $text,
    ]);
}

file_put_contents(__DIR__.'/p7-006-fixtures.json', json_encode([
    'baseUrl' => 'http://127.0.0.1:8128',
    'email' => 'p7-006@example.test',
    'password' => 'password',
    'workspace' => ['transcriptionId' => $transcription->id],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

echo "P7-006 fixtures seeded: transcription {$transcription->id}\n";
