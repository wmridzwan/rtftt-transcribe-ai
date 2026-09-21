<?php

/**
 * P5-006 translation-workspace browser-verification fixture seeder
 * (verification tooling, ADR-021).
 *
 * Boots Laravel against a dedicated verification database (DB_DATABASE must be
 * set by the caller), migrates it fresh, and seeds deterministic completed
 * transcripts plus retryable / non-retryable failed translations. Writes
 * verification/p5-006-fixtures.json for the Playwright spec.
 *
 * Not application code. Dedicated local DB only; never production data. The
 * translation worker used by this harness is a deterministic test double
 * (verification/p5-006-fake-translation-worker.mjs); it never claims the real
 * provider/model gate, which remains P5-008.
 */

use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

$database = getenv('DB_DATABASE');

if ($database === false || $database === '' || ! str_contains($database, 'p5-006-verification')) {
    fwrite(STDERR, "Refusing to seed: DB_DATABASE must point at the p5-006 verification database.\n");
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

function seed_transcription(User $user, string $title, array $sentences, string $status = 'completed'): Transcription
{
    $media = MediaFile::factory()->create([
        'user_id' => $user->id,
        'uuid' => (string) Str::uuid(),
        'original_filename' => Str::slug($title).'.wav',
        'media_type' => MediaType::Audio,
        'mime_type' => 'audio/wav',
        'extension' => 'wav',
        'duration_seconds' => count($sentences) * 5,
        'status' => MediaStatus::Ready,
    ]);

    $factory = $status === 'completed'
        ? Transcription::factory()->completed()
        : Transcription::factory()->transcribing();

    $transcription = $factory->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'title' => $title,
        'language' => 'en',
        'detected_language' => 'en',
        'model' => 'large-v3',
        'speech_detected' => true,
        'full_text' => $status === 'completed' ? implode("\n", $sentences) : null,
    ]);

    if ($status === 'completed') {
        foreach ($sentences as $index => $text) {
            $transcription->segments()->create([
                'segment_index' => $index,
                'start_seconds' => $index * 5 + 0.5,
                'end_seconds' => ($index + 1) * 5,
                'language' => 'en',
                'text' => $text,
            ]);
        }
    }

    return $transcription;
}

function seed_failed(Transcription $transcription, string $target, string $code): Translation
{
    return Translation::query()->create([
        'transcription_id' => $transcription->id,
        'target_language' => $target,
        'status' => 'failed',
        'attempt_token' => (string) Str::uuid(),
        'dispatched_at' => now()->subMinute(),
        'failure_code' => $code,
        'started_at' => now()->subMinutes(2),
        'completed_at' => now()->subMinute(),
    ]);
}

$owner = seed_user('P5-006 Owner', 'p5-006@example.test');
$other = seed_user('P5-006 Other', 'p5-006-other@example.test');

// The fake worker translates these exact sentences into ms/zh/ta.
$standard = ['Good morning everyone', 'Welcome to the weekly meeting', 'Thank you for joining'];

$main = seed_transcription($owner, 'P5-006 main transcript', $standard);
$slow = seed_transcription($owner, 'P5-006 slow transcript', ['[[SLOW]] Good morning everyone', 'Thank you for joining']);
$retryable = seed_transcription($owner, 'P5-006 retryable failure', $standard);
$final = seed_transcription($owner, 'P5-006 non-retryable failure', $standard);
$liveFinal = seed_transcription($owner, 'P5-006 live configuration failure', ['[[FAKE_401]] Good morning everyone']);
$double = seed_transcription($owner, 'P5-006 double submit', $standard);
$invalid = seed_transcription($owner, 'P5-006 invalid target', $standard);
$processing = seed_transcription($owner, 'P5-006 processing transcript', $standard, 'processing');
$foreign = seed_transcription($other, 'P5-006 other user transcript', $standard);

$retryableFailure = seed_failed($retryable, 'ms', 'PROVIDER_TIMEOUT');
$finalFailure = seed_failed($final, 'ms', 'CONFIGURATION_ERROR');

// A completed translation owned by the other user, used for cross-user denial.
$foreignTranslation = Translation::query()->create([
    'transcription_id' => $foreign->id,
    'target_language' => 'ms',
    'status' => 'completed',
    'attempt_token' => (string) Str::uuid(),
    'dispatched_at' => now(),
    'full_text' => 'Selamat pagi semua orang',
    'provider' => 'self-hosted',
    'model' => 'p5-006-test-double',
    'completed_at' => now(),
]);

foreach ($foreign->segments()->orderBy('segment_index')->get() as $segment) {
    $foreignTranslation->segments()->create([
        'segment_index' => $segment->segment_index,
        'start_seconds' => $segment->start_seconds,
        'end_seconds' => $segment->end_seconds,
        'text' => 'Terjemahan '.$segment->segment_index,
        'source_language' => 'en',
    ]);
}

$payload = [
    'baseUrl' => 'http://127.0.0.1:8123',
    'email' => 'p5-006@example.test',
    'password' => 'password',
    'otherEmail' => 'p5-006-other@example.test',
    'standardSentences' => $standard,
    'main' => $main->id,
    'slow' => $slow->id,
    'retryable' => ['transcription' => $retryable->id, 'translation' => $retryableFailure->id],
    'final' => ['transcription' => $final->id, 'translation' => $finalFailure->id],
    'liveFinal' => $liveFinal->id,
    'double' => $double->id,
    'invalid' => $invalid->id,
    'processing' => $processing->id,
    'foreign' => ['transcription' => $foreign->id, 'translation' => $foreignTranslation->id],
];

file_put_contents(__DIR__.'/p5-006-fixtures.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo "Seeded P5-006 verification database at {$database}\n";
