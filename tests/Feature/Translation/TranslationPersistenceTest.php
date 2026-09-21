<?php

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationResult;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Database\QueryException;

function translationResultFixture(
    TranslationTarget $target = TranslationTarget::Malay,
    string $fullText = 'Hai semua',
): TranslationResult {
    return new TranslationResult(
        targetLanguage: $target,
        fullText: $fullText,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hai semua', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'Selamat datang', LanguageIdentifier::English),
        ],
        provider: 'self-hosted',
        model: 'translation-test',
    );
}

it('persists a translation with enum casts and ordered segments', function () {
    $transcription = Transcription::factory()->completed()->create();

    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'completed',
        'source_language' => 'en',
    ]);

    TranslationSegment::factory()->create([
        'translation_id' => $translation->getKey(),
        'segment_index' => 1,
    ]);
    TranslationSegment::factory()->create([
        'translation_id' => $translation->getKey(),
        'segment_index' => 0,
    ]);

    $fresh = $translation->fresh();

    expect($fresh->target_language)->toBe(TranslationTarget::Malay)
        ->and($fresh->status)->toBe(TranslationStatus::Completed)
        ->and($fresh->source_language)->toBe(LanguageIdentifier::English)
        ->and($translation->segments()->pluck('segment_index')->all())->toBe([0, 1]);
});

it('writes an aligned translation without mutating the source transcript', function () {
    $transcription = Transcription::factory()->completed()->create([
        'detected_language' => 'en',
        'full_text' => 'Original source text',
    ]);
    TranscriptionSegment::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Original source text',
        'language' => 'en',
    ]);

    $beforeTranscript = $transcription->fresh()->only(['full_text', 'status', 'detected_language']);
    $beforeSegments = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    $translation = app(TranslationResultWriter::class)->persist($transcription, translationResultFixture());

    expect($translation->fresh()->status)->toBe(TranslationStatus::Completed)
        ->and($translation->segments()->pluck('text')->all())->toBe(['Hai semua', 'Selamat datang'])
        ->and((float) $translation->segments()->first()->start_seconds)->toBe(0.0)
        ->and((float) $translation->segments()->first()->end_seconds)->toBe(4.999);

    $afterTranscript = $transcription->fresh()->only(['full_text', 'status', 'detected_language']);
    $afterSegments = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    expect($afterTranscript)->toBe($beforeTranscript)
        ->and($afterSegments)->toBe($beforeSegments);
});

it('is idempotent for repeated writes of the same target', function () {
    $transcription = Transcription::factory()->completed()->create();
    $writer = app(TranslationResultWriter::class);

    $first = $writer->persist($transcription, translationResultFixture());
    $second = $writer->persist($transcription, translationResultFixture());

    expect($first->getKey())->toBe($second->getKey())
        ->and(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1)
        ->and(TranslationSegment::query()->where('translation_id', $first->getKey())->count())->toBe(2);
});

it('never overwrites a completed translation', function () {
    $transcription = Transcription::factory()->completed()->create();
    $writer = app(TranslationResultWriter::class);

    $writer->persist($transcription, translationResultFixture());
    $writer->persist($transcription, translationResultFixture(fullText: 'DIFFERENT'));

    $translations = Translation::query()->where('transcription_id', $transcription->getKey())->get();

    expect($translations)->toHaveCount(1)
        ->and($translations->first()->full_text)->toBe('Hai semua');
});

it('prevents a second active translation for the same target', function () {
    $transcription = Transcription::factory()->completed()->create();

    Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    expect(fn () => Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'translating',
    ]))->toThrow(QueryException::class);
});

it('allows a failed and a new active translation for the same target', function () {
    $transcription = Transcription::factory()->completed()->create();

    Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
    ]);
    Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    expect(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(2);
});

it('enforces unique segment index per translation', function () {
    $translation = Translation::factory()->create();

    TranslationSegment::factory()->create([
        'translation_id' => $translation->getKey(),
        'segment_index' => 0,
    ]);

    expect(fn () => TranslationSegment::factory()->create([
        'translation_id' => $translation->getKey(),
        'segment_index' => 0,
    ]))->toThrow(QueryException::class);
});
