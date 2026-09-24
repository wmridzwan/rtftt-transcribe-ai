<?php

use App\Editing\RevisionService;
use App\Editing\TranslationStalenessReason;
use App\Editing\TranslationStalenessWriter;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\Translation;
use Illuminate\Support\Facades\DB;
use Tests\Support\EditingPersistenceFixtures;

/**
 * P6-005 persisted translation-invalidation lifecycle
 * (DECISION-P6-005-STALENESS-LIFECYCLE-001; DECISION-P6-005-SCHEMA-001).
 */
it('records stale_at, reason, and the causing revision on first invalidation', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $translation = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
    ]);

    $changed = app(TranslationStalenessWriter::class)->invalidate(
        $transcription,
        TranslationStalenessReason::SegmentStructureChanged,
        'revision-1',
    );

    $fresh = $translation->fresh();

    expect($changed)->toBe(1)
        ->and($fresh->stale_at)->not->toBeNull()
        ->and($fresh->staleness_reason)->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and($fresh->stale_caused_by_revision_id)->toBe('revision-1');
});

it('preserves the first stale_at and never downgrades a stronger reason', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $translation = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
    ]);

    $writer = app(TranslationStalenessWriter::class);

    $writer->invalidate($transcription, TranslationStalenessReason::SourceTextChanged, 'rev-1');
    $firstStaleAt = $translation->fresh()->stale_at;

    // Repeated weaker/equal invalidation: no downgrade, no cause change, no
    // stale_at refresh.
    expect($writer->invalidate($transcription, TranslationStalenessReason::SourceTextChanged, 'rev-2'))->toBe(0);

    $afterEqual = $translation->fresh();

    expect($afterEqual->staleness_reason)->toBe(TranslationStalenessReason::SourceTextChanged)
        ->and($afterEqual->stale_caused_by_revision_id)->toBe('rev-1')
        ->and($afterEqual->stale_at->equalTo($firstStaleAt))->toBeTrue();

    // Stronger invalidation upgrades the reason and the causing revision while
    // preserving the original stale_at.
    expect($writer->invalidate($transcription, TranslationStalenessReason::SegmentStructureChanged, 'rev-3'))->toBe(1);

    $afterUpgrade = $translation->fresh();

    expect($afterUpgrade->staleness_reason)->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and($afterUpgrade->stale_caused_by_revision_id)->toBe('rev-3')
        ->and($afterUpgrade->stale_at->equalTo($firstStaleAt))->toBeTrue();

    // A weaker reason after a stronger one is ignored entirely.
    expect($writer->invalidate($transcription, TranslationStalenessReason::TimingChanged, 'rev-4'))->toBe(0);

    $afterWeaker = $translation->fresh();

    expect($afterWeaker->staleness_reason)->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and($afterWeaker->stale_caused_by_revision_id)->toBe('rev-3');
});

it('invalidates every target-language translation and handles the no-translation case', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();

    $none = app(TranslationStalenessWriter::class)->invalidate($transcription, TranslationStalenessReason::TimingChanged, 'rev-0');

    expect($none)->toBe(0);

    $ms = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'ms']);
    $en = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'en']);
    $zh = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'zh']);

    $changed = app(TranslationStalenessWriter::class)->invalidate($transcription, TranslationStalenessReason::TimingChanged, 'rev-1');

    expect($changed)->toBe(3)
        ->and($ms->fresh()->stale_at)->not->toBeNull()
        ->and($en->fresh()->stale_at)->not->toBeNull()
        ->and($zh->fresh()->stale_at)->not->toBeNull();
});

it('does not invalidate translations on a rejected structural edit', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;
    $translation = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey()]);

    $this->actingAs($owner)->post(route('transcriptions.revisions.split', $transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 0.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_error');

    expect($translation->fresh()->stale_at)->toBeNull()
        ->and(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0);
});

it('rolls the structural revision back when invalidation fails', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;
    Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey()]);

    $initial = app(RevisionService::class)->materializeInitial($owner, $transcription);

    $this->app->bind(TranslationStalenessWriter::class, fn (): TranslationStalenessWriter => new class implements TranslationStalenessWriter
    {
        public function invalidate(
            Transcription $transcription,
            TranslationStalenessReason $reason,
            ?string $causingRevisionId = null,
            ?DateTimeInterface $at = null,
        ): int {
            throw new RuntimeException('simulated invalidation failure');
        }
    });

    $service = app(RevisionService::class);

    expect(fn () => $service->split($owner, $transcription, $initial->revisionId, 'machine:0', 2.0, 4))
        ->toThrow(RuntimeException::class);

    // The structural revision append rolled back with the failed invalidation:
    // no new revision, and the active pointer remains on the initial revision.
    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1)
        ->and($transcription->fresh()->active_revision_id)->toBe($initial->revisionId);
});

it('never deletes or mutates historical translation segment content', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $translation = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey()]);
    $translation->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0.0,
        'end_seconds' => 4.5,
        'text' => 'historical translated text',
        'source_language' => 'ms',
    ]);

    $before = DB::table('translation_segments')->where('translation_id', $translation->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    $this->actingAs($owner)->post(route('transcriptions.revisions.split', $transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_notice');

    $after = DB::table('translation_segments')->where('translation_id', $translation->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

    expect($after)->toBe($before)
        ->and($translation->fresh()->stale_at)->not->toBeNull();
});
