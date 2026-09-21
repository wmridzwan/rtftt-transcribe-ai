<?php

use App\Models\Transcription;
use App\Models\TranscriptionSegment;
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
use Illuminate\Support\Facades\DB;

function sourceTranscription(): Transcription
{
    $transcription = Transcription::factory()->completed()->create([
        'detected_language' => 'en',
        'full_text' => 'Original source text',
    ]);

    TranscriptionSegment::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 4.999,
        'text' => 'Hello',
        'language' => 'en',
    ]);

    TranscriptionSegment::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'segment_index' => 1,
        'start_seconds' => 4.999,
        'end_seconds' => 9.5,
        'text' => 'Welcome',
        'language' => 'en',
    ]);

    return $transcription;
}

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
    $transcription = sourceTranscription();

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
    $transcription = sourceTranscription();
    $writer = app(TranslationResultWriter::class);

    $first = $writer->persist($transcription, translationResultFixture());
    $second = $writer->persist($transcription, translationResultFixture());

    expect($first->getKey())->toBe($second->getKey())
        ->and(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1)
        ->and(TranslationSegment::query()->where('translation_id', $first->getKey())->count())->toBe(2);
});

it('never overwrites a completed translation', function () {
    $transcription = sourceTranscription();
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

it('rejects a result whose segment count differs from the source', function () {
    $transcription = sourceTranscription();

    $result = new TranslationResult(
        targetLanguage: TranslationTarget::Malay,
        fullText: 'x',
        segments: [],
        provider: 'self-hosted',
        model: 'translation-test',
    );

    expect(fn () => app(TranslationResultWriter::class)->persist($transcription, $result))
        ->toThrow(TranslationException::class);

    expect(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0);
});

it('rejects a result with foreign segment indices', function () {
    $transcription = sourceTranscription();

    $result = new TranslationResult(
        targetLanguage: TranslationTarget::Malay,
        fullText: 'x',
        segments: [
            new TranslationSegmentData(77, 0.0, 4.999, 'x', LanguageIdentifier::English),
            new TranslationSegmentData(78, 4.999, 9.5, 'y', LanguageIdentifier::English),
        ],
        provider: 'self-hosted',
        model: 'translation-test',
    );

    expect(fn () => app(TranslationResultWriter::class)->persist($transcription, $result))
        ->toThrow(TranslationException::class);
});

it('rejects a result with timestamps that do not match the source', function () {
    $transcription = sourceTranscription();

    $result = new TranslationResult(
        targetLanguage: TranslationTarget::Malay,
        fullText: 'x',
        segments: [
            new TranslationSegmentData(0, 0.0, 3.0, 'x', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'y', LanguageIdentifier::English),
        ],
        provider: 'self-hosted',
        model: 'translation-test',
    );

    expect(fn () => app(TranslationResultWriter::class)->persist($transcription, $result))
        ->toThrow(TranslationException::class);
});

it('completes a matching active row through the translation_id path', function () {
    $transcription = sourceTranscription();

    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $result = app(TranslationResultWriter::class)->persist(
        $transcription,
        translationResultFixture(),
        $translation->getKey(),
    );

    expect($result->getKey())->toBe($translation->getKey())
        ->and($result->fresh()->status)->toBe(TranslationStatus::Completed)
        ->and($result->segments()->count())->toBe(2);
});

it('rejects a translation_id whose target differs from the result', function () {
    $transcription = sourceTranscription();

    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'en',
        'status' => 'queued',
    ]);

    expect(fn () => app(TranslationResultWriter::class)->persist(
        $transcription,
        translationResultFixture(target: TranslationTarget::Malay),
        $translation->getKey(),
    ))->toThrow(TranslationException::class);

    expect($translation->fresh()->status)->toBe(TranslationStatus::Queued);
});

it('rejects completing a failed translation directly', function () {
    $transcription = sourceTranscription();

    $translation = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
    ]);

    expect(fn () => app(TranslationResultWriter::class)->persist(
        $transcription,
        translationResultFixture(),
        $translation->getKey(),
    ))->toThrow(TranslationException::class);

    expect($translation->fresh()->status)->toBe(TranslationStatus::Failed);
});

it('rolls back the whole write when persistence fails mid-transaction', function () {
    $transcription = sourceTranscription();
    $armed = true;

    Translation::creating(function () use (&$armed) {
        if ($armed) {
            $armed = false;
            throw new RuntimeException('forced translation create failure');
        }
    });

    expect(fn () => app(TranslationResultWriter::class)->persist($transcription, translationResultFixture()))
        ->toThrow(RuntimeException::class);

    expect(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0)
        ->and(TranslationSegment::query()->count())->toBe(0);
});

it('wraps a unique-index collision as a persistence failure', function () {
    $transcription = sourceTranscription();
    $fired = false;

    Translation::creating(function (Translation $model) use (&$fired, $transcription) {
        if ($fired) {
            return;
        }

        $fired = true;

        DB::table('translations')->insert([
            'transcription_id' => $transcription->getKey(),
            'target_language' => $model->target_language->value,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    expect(fn () => app(TranslationResultWriter::class)->persist($transcription, translationResultFixture()))
        ->toThrow(TranslationException::class);

    expect(Translation::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0);
});
