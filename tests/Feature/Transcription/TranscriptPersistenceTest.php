<?php

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptSegmentData;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TranscriptionFixtures;

beforeEach(function (): void {
    Storage::fake('local');
});

function p3004Result(): NormalizedTranscript
{
    return new NormalizedTranscript(
        text: 'Hello world.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 5.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 5.0, 'Hello world.', LanguageIdentifier::English),
        ],
    );
}

test('persists full transcript text, detected language, model and timestamps', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, p3004Result(), 'large-v3');

    $transcription->refresh();
    $attempt->refresh();

    expect($transcription->full_text)->toBe('Hello world.')
        ->and($transcription->detected_language)->toBe('en')
        ->and($transcription->model)->toBe('large-v3')
        ->and($transcription->speech_detected)->toBeTrue()
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->started_at)->not->toBeNull()
        ->and($transcription->completed_at)->not->toBeNull()
        ->and($transcription->processing_seconds)->toBeGreaterThanOrEqual(0)
        ->and($attempt->status)->toBe(ProcessingStatus::Completed)
        ->and($attempt->progress_percentage)->toBe(100);
});

test('requested language hint is not overwritten by detected language', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );
    $transcription->forceFill(['language' => 'ms'])->save();

    $result = new NormalizedTranscript(
        text: 'Saya faham English sedikit.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 4.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 4.0, 'Saya faham English sedikit.', LanguageIdentifier::Malay),
        ],
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, $result, 'large-v3');

    $transcription->refresh();

    expect($transcription->language)->toBe('ms')
        ->and($transcription->detected_language)->toBe('en');
});

test('persists a successful no-speech result without converting it into a failure', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    app(TranscriptionResultWriter::class)->persist(
        $transcription,
        $attempt,
        NormalizedTranscript::noSpeech(3.5),
        'large-v3',
    );

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('')
        ->and($transcription->detected_language)->toBe('und')
        ->and($transcription->speech_detected)->toBeFalse()
        ->and($transcription->segments()->count())->toBe(0);
});

test('preserves unicode and mixed-script transcript text', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $text = 'Selamat datang. 欢迎光临. வணக்கம்.';

    $result = new NormalizedTranscript(
        text: $text,
        detectedLanguage: LanguageIdentifier::Malay,
        durationSeconds: 8.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 3.0, 'Selamat datang.', LanguageIdentifier::Malay),
            new TranscriptSegmentData(1, 3.0, 5.5, '欢迎光临.', LanguageIdentifier::Chinese),
            new TranscriptSegmentData(2, 5.5, 8.0, 'வணக்கம்.', LanguageIdentifier::Tamil),
        ],
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, $result, 'large-v3');

    $transcription->refresh();

    expect($transcription->full_text)->toBe($text)
        ->and($transcription->segments()->pluck('text')->all())->toBe([
            'Selamat datang.',
            '欢迎光临.',
            'வணக்கம்.',
        ]);
});

test('supports long transcripts within repository constraints', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $text = str_repeat('Bahasa Melayu dan English. ', 5000);

    $result = new NormalizedTranscript(
        text: $text,
        detectedLanguage: LanguageIdentifier::Malay,
        durationSeconds: 600.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 600.0, $text, LanguageIdentifier::Malay),
        ],
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, $result, 'large-v3');

    expect($transcription->refresh()->full_text)->toBe($text);
});

test('repeated persistence for the same attempt is idempotent', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $writer = app(TranscriptionResultWriter::class);

    $writer->persist($transcription, $attempt, p3004Result(), 'large-v3');
    $writer->persist($transcription, $attempt, p3004Result(), 'large-v3');

    expect($transcription->refresh()->segments()->count())->toBe(1)
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed);
});

test('rejects an invalid normalized result with duplicate segment indices', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $result = new NormalizedTranscript(
        text: 'Duplicate indices.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 4.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'A', LanguageIdentifier::English),
            new TranscriptSegmentData(0, 2.0, 4.0, 'B', LanguageIdentifier::English),
        ],
    );

    expect(fn () => app(TranscriptionResultWriter::class)->persist($transcription, $attempt, $result, 'large-v3'))
        ->toThrow(TranscriptionException::class);

    expect($transcription->refresh()->status)->toBe(TranscriptionStatus::Transcribing)
        ->and($transcription->segments()->count())->toBe(0);
});

test('cross-user transcript writes are impossible', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
        consistentOwnership: false,
    );

    expect(fn () => app(TranscriptionResultWriter::class)->persist($transcription, $attempt, p3004Result(), 'large-v3'))
        ->toThrow(TranscriptionException::class);

    expect($transcription->refresh()->status)->toBe(TranscriptionStatus::Transcribing)
        ->and($transcription->full_text)->toBeNull();
});

test('a processing attempt from a different transcription is rejected', function () {
    ['transcription' => $transcription] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );
    ['attempt' => $otherAttempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    expect(fn () => app(TranscriptionResultWriter::class)->persist($transcription, $otherAttempt, p3004Result(), 'large-v3'))
        ->toThrow(TranscriptionException::class);

    expect($transcription->refresh()->status)->toBe(TranscriptionStatus::Transcribing);
});

/*
 * P7-009-CORR-01 corrective cycle 2.
 *
 * Production AC14 failed on PostgreSQL with `invalid input syntax for type
 * integer: "185.832677"`. The writer reads started_at back from the database at
 * whole-second precision but stamps completed_at with `now()` at microsecond
 * precision, so the elapsed time is fractional; Carbon 3 returns it as a float,
 * Laravel binds a float as a string, and an `integer` column rejects it. SQLite
 * accepts the same value (type affinity) and the model's `integer` cast hides it
 * on read-back, so these tests assert what reaches the driver and what is stored
 * instead of the cast model attribute. PostgreSQL itself is not available here.
 */

/**
 * @return array{transcription: Transcription, attempt: ProcessingJob, bindings: list<list<mixed>>}
 */
function p7009Cycle2Persist(string $completedAt): array
{
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );
    $transcription->forceFill(['started_at' => '2026-10-02 08:00:00'])->save();

    test()->travelTo(Carbon::parse($completedAt));

    $bindings = [];

    DB::listen(function (QueryExecuted $query) use (&$bindings): void {
        if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'processing_seconds')) {
            $bindings[] = $query->bindings;
        }
    });

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, p3004Result(), 'large-v3');

    return ['transcription' => $transcription, 'attempt' => $attempt, 'bindings' => $bindings];
}

test('persists a fractional elapsed time as integer processing seconds and still completes the transcription', function () {
    $run = p7009Cycle2Persist('2026-10-02 08:03:05.832677');
    $transcription = $run['transcription'];
    $attempt = $run['attempt'];

    expect(now()->diffInSeconds(Carbon::parse('2026-10-02 08:00:00'), true))->toBeBetween(185.83, 185.84);

    $boundValues = array_merge(...$run['bindings']);

    expect($run['bindings'])->toHaveCount(2)
        ->and($boundValues)->each->not->toBeFloat()
        ->and($boundValues)->toContain(186);

    expect(DB::table('transcriptions')->where('id', $transcription->id)->value('processing_seconds'))->toBe(186)
        ->and(DB::table('processing_jobs')->where('id', $attempt->id)->value('processing_seconds'))->toBe(186);

    $transcription->refresh();
    $attempt->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Hello world.')
        ->and($transcription->completed_at->toDateTimeString())->toBe('2026-10-02 08:03:05')
        ->and($transcription->segments()->count())->toBe(1)
        ->and($attempt->status)->toBe(ProcessingStatus::Completed)
        ->and($attempt->progress_percentage)->toBe(100)
        ->and($attempt->failure_code)->toBeNull();
});

test('rounds the elapsed time to the nearest whole second when persisting processing seconds', function (string $completedAt, int $expectedSeconds) {
    $run = p7009Cycle2Persist($completedAt);

    expect(DB::table('transcriptions')->where('id', $run['transcription']->id)->value('processing_seconds'))->toBe($expectedSeconds)
        ->and(DB::table('processing_jobs')->where('id', $run['attempt']->id)->value('processing_seconds'))->toBe($expectedSeconds);
})->with([
    'whole seconds stay unchanged' => ['2026-10-02 08:03:05.000000', 185],
    'a tenth of a second rounds down' => ['2026-10-02 08:03:05.100000', 185],
    'just under half a second rounds down' => ['2026-10-02 08:03:05.499999', 185],
    'exactly half a second rounds up' => ['2026-10-02 08:03:05.500000', 186],
    'just under a whole second rounds up' => ['2026-10-02 08:03:05.999999', 186],
    'a sub-second run is zero' => ['2026-10-02 08:00:00.400000', 0],
]);
