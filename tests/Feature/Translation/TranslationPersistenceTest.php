<?php

use App\Models\Transcription;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationException;
use App\Translation\TranslationResult;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Database\QueryException;

it('persists a translation with enum casts and ordered segments', function () {
    $translation = Translation::factory()->create(['target_language' => 'ms', 'status' => 'completed', 'source_language' => 'en']);

    TranslationSegment::factory()->create(['translation_id' => $translation->getKey(), 'segment_index' => 1]);
    TranslationSegment::factory()->create(['translation_id' => $translation->getKey(), 'segment_index' => 0]);

    $fresh = $translation->fresh();

    expect($fresh->target_language)->toBe(TranslationTarget::Malay)
        ->and($fresh->status)->toBe(TranslationStatus::Completed)
        ->and($fresh->source_language)->toBe(LanguageIdentifier::English)
        ->and($translation->segments()->pluck('segment_index')->all())->toBe([0, 1]);
});

it('completes a translating translation and copies authoritative source alignment', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    $before = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    // Provider echoes a wrong source language; the writer must ignore it.
    $written = writerPersist($transcription, writerResult(sourceLanguage: LanguageIdentifier::Chinese), $translation);

    expect($written->fresh()->status)->toBe(TranslationStatus::Completed)
        ->and($written->segments()->pluck('text')->all())->toBe(['Hai semua', 'Selamat datang'])
        ->and((float) $written->segments()->first()->start_seconds)->toBe(0.0)
        ->and((float) $written->segments()->first()->end_seconds)->toBe(4.999)
        ->and($written->segments()->pluck('source_language')->map->value->all())->toBe(['en', 'en']);

    $after = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();
    expect($after)->toBe($before);
});

it('is idempotent for repeated writes of the same translation row', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    $first = writerPersist($transcription, writerResult(), $translation);
    $second = writerPersist($transcription, writerResult(), $translation);

    expect($first->getKey())->toBe($second->getKey())
        ->and(TranslationSegment::query()->where('translation_id', $translation->getKey())->count())->toBe(2);
});

it('never overwrites a completed translation', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    writerPersist($transcription, writerResult(), $translation);
    writerPersist($transcription, writerResult(fullText: 'DIFFERENT'), $translation);

    expect($translation->fresh()->full_text)->toBe('Hai semua');
});

it('rejects a stale attempt token', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    expect(fn () => app(TranslationResultWriter::class)->persist(
        $transcription,
        writerResult(),
        $translation->getKey(),
        'a-different-token',
    ))->toThrow(TranslationException::class);

    expect($translation->fresh()->status)->toBe(TranslationStatus::Translating);
});

it('rejects a translation_id whose target differs from the result', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription, target: 'en');

    expect(fn () => writerPersist($transcription, writerResult(), $translation))
        ->toThrow(TranslationException::class);

    expect($translation->fresh()->status)->toBe(TranslationStatus::Translating);
});

it('rejects completing a failed translation directly', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription, status: 'failed');

    expect(fn () => writerPersist($transcription, writerResult(), $translation))
        ->toThrow(TranslationException::class);
});

it('rejects completing a queued translation without a claim', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription, status: 'queued');

    expect(fn () => writerPersist($transcription, writerResult(), $translation))
        ->toThrow(TranslationException::class);
});

it('rejects a result whose segment count differs from the source', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    $result = new TranslationResult(TranslationTarget::Malay, 'x', [], 'self-hosted', 'translation-test');

    expect(fn () => writerPersist($transcription, $result, $translation))
        ->toThrow(TranslationException::class);
});

it('rejects a result with foreign segment indices', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    $result = new TranslationResult(
        TranslationTarget::Malay,
        'x',
        [
            new TranslationSegmentData(77, 0.0, 4.999, 'x', LanguageIdentifier::English),
            new TranslationSegmentData(78, 4.999, 9.5, 'y', LanguageIdentifier::English),
        ],
        'self-hosted',
        'translation-test',
    );

    expect(fn () => writerPersist($transcription, $result, $translation))
        ->toThrow(TranslationException::class);
});

it('rejects a result with timestamps that do not match the source', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    $result = new TranslationResult(
        TranslationTarget::Malay,
        'x',
        [
            new TranslationSegmentData(0, 0.0, 3.0, 'x', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'y', LanguageIdentifier::English),
        ],
        'self-hosted',
        'translation-test',
    );

    expect(fn () => writerPersist($transcription, $result, $translation))
        ->toThrow(TranslationException::class);
});

it('rolls back the translation and all segments when segment persistence fails', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);
    $armed = true;
    $writes = 0;

    TranslationSegment::creating(function () use (&$armed, &$writes) {
        if (! $armed) {
            return;
        }

        $writes++;

        if ($writes >= 2) {
            throw new RuntimeException('forced segment persistence failure');
        }
    });

    expect(fn () => writerPersist($transcription, writerResult(), $translation))
        ->toThrow(RuntimeException::class);

    $armed = false;

    expect($translation->fresh()->status)->toBe(TranslationStatus::Translating)
        ->and(TranslationSegment::query()->where('translation_id', $translation->getKey())->count())->toBe(0);
});

it('prevents a second active translation for the same target', function () {
    $transcription = Transcription::factory()->completed()->create();

    Translation::factory()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'ms', 'status' => 'queued']);

    expect(fn () => Translation::factory()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'ms', 'status' => 'translating']))
        ->toThrow(QueryException::class);
});

it('allows a failed and a new active translation for the same target', function () {
    $transcription = Transcription::factory()->completed()->create();

    Translation::factory()->failed()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'ms']);
    Translation::factory()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'ms', 'status' => 'queued']);

    expect(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(2);
});

it('enforces unique segment index per translation', function () {
    $translation = Translation::factory()->create();

    TranslationSegment::factory()->create(['translation_id' => $translation->getKey(), 'segment_index' => 0]);

    expect(fn () => TranslationSegment::factory()->create(['translation_id' => $translation->getKey(), 'segment_index' => 0]))
        ->toThrow(QueryException::class);
});
