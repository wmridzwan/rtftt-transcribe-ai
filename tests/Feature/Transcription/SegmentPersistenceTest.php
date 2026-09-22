<?php

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\TranscriptionSegment;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptSegmentData;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TranscriptionFixtures;

beforeEach(function (): void {
    Storage::fake('local');
});

test('segments persist in deterministic segment_index order', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $result = new NormalizedTranscript(
        text: 'C B A',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 6.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(2, 4.0, 6.0, 'C', LanguageIdentifier::English),
            new TranscriptSegmentData(0, 0.0, 2.0, 'A', LanguageIdentifier::English),
            new TranscriptSegmentData(1, 2.0, 4.0, 'B', LanguageIdentifier::English),
        ],
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, $result, 'large-v3');

    expect($transcription->segments()->pluck('text')->all())->toBe(['A', 'B', 'C'])
        ->and($transcription->segments()->pluck('segment_index')->all())->toBe([0, 1, 2]);
});

test('segment timestamps preserve sub-second precision', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $result = new NormalizedTranscript(
        text: 'Precise.',
        detectedLanguage: LanguageIdentifier::Tamil,
        durationSeconds: 4.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.18, 3.98, 'Precise.', LanguageIdentifier::Tamil),
        ],
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, $result, 'large-v3');

    $segment = $transcription->segments()->firstOrFail();

    expect((float) $segment->start_seconds)->toBeGreaterThanOrEqual(0.17)
        ->and((float) $segment->start_seconds)->toBeLessThanOrEqual(0.19)
        ->and((float) $segment->end_seconds)->toBeGreaterThanOrEqual(3.97)
        ->and((float) $segment->end_seconds)->toBeLessThanOrEqual(3.99);
});

test('each segment stores one language including und and mixed languages', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $result = new NormalizedTranscript(
        text: 'Mixed',
        detectedLanguage: LanguageIdentifier::Malay,
        durationSeconds: 10.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'Selamat.', LanguageIdentifier::Malay),
            new TranscriptSegmentData(1, 2.0, 4.0, 'Hello.', LanguageIdentifier::English),
            new TranscriptSegmentData(2, 4.0, 6.0, '你好.', LanguageIdentifier::Chinese),
            new TranscriptSegmentData(3, 6.0, 8.0, 'வணக்கம்.', LanguageIdentifier::Tamil),
            new TranscriptSegmentData(4, 8.0, 10.0, '...', LanguageIdentifier::Undetermined),
        ],
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, $result, 'large-v3');

    $languages = $transcription->segments()->get()
        ->map(fn (TranscriptionSegment $segment): string => $segment->language->value)
        ->all();

    expect($languages)->toBe(['ms', 'en', 'zh', 'ta', 'und']);
});

test('the database enforces uniqueness of transcription_id and segment_index', function () {
    ['transcription' => $transcription] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 1,
        'text' => 'First.',
        'language' => 'en',
    ]);

    expect(fn () => $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 1,
        'end_seconds' => 2,
        'text' => 'Duplicate.',
        'language' => 'en',
    ]))->toThrow(QueryException::class);
});

test('retry/replay does not duplicate the segment set', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $writer = app(TranscriptionResultWriter::class);

    $result = new NormalizedTranscript(
        text: 'Version one.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 4.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'One A', LanguageIdentifier::English),
            new TranscriptSegmentData(1, 2.0, 4.0, 'One B', LanguageIdentifier::English),
        ],
    );

    $writer->persist($transcription, $attempt, $result, 'large-v3');
    $writer->persist($transcription, $attempt, $result, 'large-v3');

    expect($transcription->segments()->count())->toBe(2)
        ->and($transcription->segments()->pluck('text')->all())->toBe(['One A', 'One B']);
});

test('a subsequent attempt replaces the previous segment set deterministically', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $writer = app(TranscriptionResultWriter::class);

    $writer->persist($transcription, $attempt, new NormalizedTranscript(
        text: 'Version one.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 4.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'One A', LanguageIdentifier::English),
            new TranscriptSegmentData(1, 2.0, 4.0, 'One B', LanguageIdentifier::English),
        ],
    ), 'large-v3');

    expect($transcription->segments()->count())->toBe(2);

    $transcription->refresh();
    $transcription->forceFill(['status' => TranscriptionStatus::Transcribing])->save();

    $writer->persist($transcription, $attempt, new NormalizedTranscript(
        text: 'Version two.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 4.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'Two A', LanguageIdentifier::English),
        ],
    ), 'large-v3');

    expect($transcription->segments()->count())->toBe(1)
        ->and($transcription->segments()->pluck('text')->all())->toBe(['Two A'])
        ->and($transcription->refresh()->full_text)->toBe('Version two.');
});

test('completed transcriptions cannot be overwritten by a stale write', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $writer = app(TranscriptionResultWriter::class);

    $writer->persist($transcription, $attempt, new NormalizedTranscript(
        text: 'Original.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 2.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'Original.', LanguageIdentifier::English),
        ],
    ), 'large-v3');

    $writer->persist($transcription, $attempt, new NormalizedTranscript(
        text: 'Stale overwrite attempt.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 2.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'Stale.', LanguageIdentifier::English),
        ],
    ), 'large-v3');

    expect($transcription->refresh()->full_text)->toBe('Original.')
        ->and($transcription->segments()->pluck('text')->all())->toBe(['Original.']);
});
