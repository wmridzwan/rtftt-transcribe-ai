<?php

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptSegmentData;
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
