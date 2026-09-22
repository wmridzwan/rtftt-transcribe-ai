<?php

/**
 * P5-008 canonical real-gate browser fixture seeder (verification tooling,
 * ADR-024).
 *
 * Boots Laravel against a dedicated verification database (DB_DATABASE must be
 * set by the caller), migrates it fresh, and seeds one code-switched completed
 * transcript for the real browser-to-real-model end-to-end proof. It does NOT
 * seed a translation: the browser starts one and a real queue worker consumes
 * it through the canonical NLLB model.
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
use Illuminate\Support\Str;

$database = getenv('DB_DATABASE');

if ($database === false || $database === '' || ! str_contains($database, 'p5-008-browser')) {
    fwrite(STDERR, "Refusing to seed: DB_DATABASE must point at the p5-008 browser verification database.\n");
    exit(1);
}

@touch($database);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Artisan::call('migrate:fresh', ['--force' => true]);

function seed_user(string $name, string $email): User
{
    return User::factory()->create([
        'name' => $name,
        'email' => $email,
        'password' => 'password',
        'email_verified_at' => now(),
    ]);
}

$owner = seed_user('P5-008 UI Owner', 'p5-008-ui@example.test');
$other = seed_user('P5-008 UI Other', 'p5-008-ui-other@example.test');

$media = MediaFile::factory()->create([
    'user_id' => $owner->id,
    'uuid' => (string) Str::uuid(),
    'original_filename' => 'p5-008-ui-code-switch.wav',
    'media_type' => MediaType::Audio,
    'mime_type' => 'audio/wav',
    'extension' => 'wav',
    'duration_seconds' => 25,
    'status' => MediaStatus::Ready,
]);

$transcription = Transcription::factory()->completed()->create([
    'user_id' => $owner->id,
    'media_file_id' => $media->id,
    'title' => 'P5-008 UI code-switch transcript',
    'language' => 'en',
    'detected_language' => 'und',
    'model' => 'large-v3',
    'speech_detected' => true,
    'full_text' => 'Selamat pagi semua orang. Good morning everyone. 大家好. காலை வணக்கம். Welcome to the meeting.',
]);

// Code-switched source: ms, en, zh, ta, und.
$segments = [
    ['text' => 'Selamat pagi semua orang', 'language' => 'ms'],
    ['text' => 'Good morning everyone', 'language' => 'en'],
    ['text' => '大家好', 'language' => 'zh'],
    ['text' => 'காலை வணக்கம்', 'language' => 'ta'],
    ['text' => 'Welcome to the meeting', 'language' => 'und'],
];

foreach ($segments as $index => $segment) {
    $transcription->segments()->create([
        'segment_index' => $index,
        'start_seconds' => $index * 5,
        'end_seconds' => ($index + 1) * 5,
        'language' => $segment['language'],
        'text' => $segment['text'],
    ]);
}

$payload = [
    'email' => 'p5-008-ui@example.test',
    'password' => 'password',
    'otherEmail' => 'p5-008-ui-other@example.test',
    'main' => $transcription->getKey(),
    'segments' => $segments,
];

file_put_contents(
    __DIR__.'/p5-008-fixtures.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
);

echo "Seeded P5-008 browser verification database at {$database}\n";
