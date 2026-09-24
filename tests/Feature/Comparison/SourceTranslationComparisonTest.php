<?php

use App\Comparison\ComparisonBuilder;
use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Editing\RevisionService;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Models\User;
use App\Transcription\LanguageIdentifier;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EditingPersistenceFixtures;

/**
 * P6-007 presentation-only source / active-revision / persisted-translation
 * comparison. Read-only; no translation-staleness ownership.
 */
function showUrl(Transcription $transcription): string
{
    return route('transcriptions.show', $transcription);
}

function completedTranslation(Transcription $transcription, array $texts, string $target = 'zh'): Translation
{
    $translation = Translation::factory()->completed()->for($transcription)->create([
        'target_language' => $target,
        'source_language' => 'en',
    ]);

    $machine = $transcription->segments()->orderBy('segment_index')->get();

    foreach ($machine as $index => $segment) {
        $translation->segments()->create([
            'segment_index' => $segment->segment_index,
            'start_seconds' => $segment->start_seconds,
            'end_seconds' => $segment->end_seconds,
            'text' => $texts[$index] ?? '',
            'source_language' => $segment->language->value,
        ]);
    }

    return $translation;
}

function editActiveRevision(Transcription $transcription, array $texts): void
{
    $service = app(RevisionService::class);
    $owner = $transcription->user;

    $initial = $service->materializeInitial($owner, $transcription);

    $segments = [];
    foreach ($initial->segments as $segment) {
        $segments[] = new RevisionSegmentData(
            identity: $segment->identity,
            position: $segment->position,
            startSeconds: $segment->startSeconds,
            endSeconds: $segment->endSeconds,
            text: $texts[$segment->position] ?? $segment->text,
            language: $segment->language,
        );
    }

    $service->edit($owner, $transcription, $initial->revisionId, $segments);
}

it('presents the machine source as authoritative and shows the no-translation state when no revision or translation exists', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $response = $this->actingAs($owner)->get(showUrl($transcription));

    $response->assertOk()
        ->assertSee('data-transcript-comparison', false)
        ->assertSee('data-compare-view="compare"', false)
        ->assertSee('data-comparison-state="machine"', false)
        ->assertSee('data-comparison-no-translation', false)
        ->assertSee('data-comparison-revision-state="machine-authoritative"', false)
        ->assertSee('Hai semua')
        ->assertSee('Welcome 欢迎');
});

it('aligns the persisted translation to the machine source by segment_index', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    completedTranslation($transcription, ['Terjemahan satu', 'Translation two']);

    $response = $this->actingAs($owner)->get(showUrl($transcription));

    $response->assertOk()
        ->assertSee('data-comparison-state="machine"', false)
        ->assertSee('data-comparison-translation-state', false)
        ->assertSee('ZH')
        ->assertSee('Terjemahan satu')
        ->assertSee('Translation two')
        ->assertDontSee('data-comparison-mismatch-note', false);
});

it('shows the active revision next to the machine source when the revision is textually identical', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    editActiveRevision($transcription, ['Hai semua', 'Welcome 欢迎']);
    completedTranslation($transcription, ['Terjemahan satu', 'Translation two']);

    $response = $this->actingAs($owner)->get(showUrl($transcription));

    $response->assertOk()
        ->assertSee('data-comparison-state="revision"', false)
        ->assertSee('Active revision v2')
        ->assertSee('data-comparison-revision-text', false)
        ->assertDontSee('data-comparison-revision-edited-note', false)
        ->assertDontSee('data-comparison-mismatch-note', false);
});

it('shows only the factual no-translation state and never a false edited-after-translation note when an edited revision has no translation', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    editActiveRevision($transcription, ['Edited without translation one', 'Edited without translation two']);

    $response = $this->actingAs($owner)->get(showUrl($transcription));

    $response->assertOk()
        ->assertSee('data-comparison-state="revision"', false)
        ->assertSee('data-comparison-no-translation', false)
        ->assertSee('data-comparison-revision-text', false)
        ->assertSee('Edited without translation one')
        // No translation was ever persisted, so no provenance/chronology note
        // may be rendered at any level.
        ->assertDontSee('data-comparison-revision-edited-note', false)
        ->assertDontSee('data-comparison-mismatch-note', false)
        ->assertDontSee('Edited after the translation was produced');

    $comparison = app(ComparisonBuilder::class)->build(
        $transcription->fresh(),
        app(RevisionService::class)->active($owner, $transcription),
    );

    expect($comparison->hasTranslation)->toBeFalse();

    foreach ($comparison->rows as $row) {
        expect($row->hasTranslation())->toBeFalse()
            ->and($row->hasEditedRevisionWithPersistedTranslation())->toBeFalse();
    }
});

it('labels the translation as belonging to the machine source when the active revision has been edited', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    editActiveRevision($transcription, ['Edited machine one', 'Edited machine two']);
    completedTranslation($transcription, ['Terjemahan satu', 'Translation two']);

    $response = $this->actingAs($owner)->get(showUrl($transcription));

    $response->assertOk()
        ->assertSee('data-comparison-mismatch-note', false)
        ->assertSee('data-comparison-revision-edited-note', false)
        ->assertSee('Edited after the translation was produced')
        // The translation is still shown as the translation of the machine source.
        ->assertSee('Terjemahan satu')
        ->assertSee('Translation two')
        // And the edited revision text is shown.
        ->assertSee('Edited machine one');
});

it('presents alignment as unavailable for a structurally changed revision and does not remap the translation by index', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;
    $service = app(RevisionService::class);

    $initial = $service->materializeInitial($owner, $transcription);

    $service->edit($owner, $transcription, $initial->revisionId, [
        new RevisionSegmentData(RevisionSegmentIdentity::fromString('new-0'), 0, 0.0, 4.5, 'Restructured A', LanguageIdentifier::Malay),
        new RevisionSegmentData(RevisionSegmentIdentity::fromString('new-1'), 1, 4.5, 9.25, 'Restructured B', LanguageIdentifier::English),
    ]);

    completedTranslation($transcription, ['Terjemahan satu', 'Translation two']);

    $response = $this->actingAs($owner)->get(showUrl($transcription));

    $response->assertOk()
        ->assertSee('data-comparison-revision-state="alignment-unavailable"', false)
        ->assertSee('Restructured A')
        ->assertSee('Restructured B')
        // A structurally incompatible revision is never aligned to the machine
        // translation, so no provenance/edited note is silently attached.
        ->assertDontSee('data-comparison-revision-edited-note', false)
        ->assertDontSee('data-comparison-mismatch-note', false);

    // The machine-aligned translation is not mapped onto the unaligned revision.
    $builder = app(ComparisonBuilder::class);
    $comparison = $builder->build($transcription->fresh(), $service->active($owner, $transcription));

    expect($comparison->hasUnalignedRevisionSegments())->toBeTrue();

    foreach ($comparison->rows as $row) {
        if ($row->machineIndex === null) {
            expect($row->translatedText)->toBeNull()
                ->and($row->revisionAligned)->toBeFalse();
        }
    }
});

it('performs no database writes and does not infer staleness', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    editActiveRevision($transcription, ['Edited one', 'Edited two']);
    completedTranslation($transcription, ['Terjemahan satu', 'Translation two']);

    $fingerprint = function (): array {
        return [
            TranscriptRevisionModel::query()->count(),
            TranscriptRevisionSegment::query()->count(),
            Translation::query()->count(),
            TranslationSegment::query()->count(),
            Translation::query()->orderBy('id')->pluck('updated_at')->map(fn ($value): string => (string) $value)->all(),
        ];
    };

    $before = $fingerprint();

    $this->actingAs($owner)->get(showUrl($transcription))->assertOk();
    $this->actingAs($owner)->get(showUrl($transcription))->assertOk();

    expect($fingerprint())->toBe($before);

    // P6-007 must not introduce or read a P6-005 staleness marker.
    expect(Schema::hasColumn('translations', 'stale_at'))->toBeFalse()
        ->and(Schema::hasColumn('translations', 'staleness_reason'))->toBeFalse();
});

it('does not expose another user comparison to a non-owner', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(showUrl($transcription))->assertForbidden();
});

it('keeps the P6-006 and reserved Phase 4 hooks intact alongside the comparison', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    completedTranslation($transcription, ['Terjemahan satu', 'Translation two']);

    $this->actingAs($owner)->get(showUrl($transcription))
        ->assertOk()
        ->assertSee('data-filter-language', false)
        ->assertSee('data-nav-seconds', false)
        ->assertSee('data-seek-seconds', false)
        ->assertSee('data-segment-language', false)
        ->assertSee('data-transcript-region', false);
});
