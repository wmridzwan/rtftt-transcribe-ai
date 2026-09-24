<?php

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionService;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Support\EditingPersistenceFixtures;

/**
 * P6-005 structural split / merge HTTP surface and persistence over the frozen
 * P6-001..P6-004 revision foundation.
 */
function splitUrl(Transcription $transcription): string
{
    return route('transcriptions.revisions.split', $transcription);
}

function mergeUrl(Transcription $transcription): string
{
    return route('transcriptions.revisions.merge', $transcription);
}

function machineSnapshot(Transcription $transcription): array
{
    return $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();
}

it('materializes the initial revision and appends an interior split from the machine source', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $machineBefore = machineSnapshot($transcription);

    $response = $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ]);

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('structural_notice');

    $revisions = TranscriptRevisionModel::query()
        ->where('transcription_id', $transcription->getKey())
        ->orderBy('version')
        ->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions[0]->version)->toBe(1)
        ->and($revisions[1]->version)->toBe(2)
        ->and($revisions[1]->parent_revision_id)->toBe($revisions[0]->id)
        ->and($transcription->fresh()->active_revision_id)->toBe($revisions[1]->id);

    $active = app(RevisionService::class)->active($owner, $transcription);

    expect($active)->not->toBeNull()
        ->and(array_map(fn (RevisionSegmentData $s): int => $s->position, $active->segments))->toBe([0, 1, 2])
        ->and($active->segments[0]->text)->toBe('Hai ')
        ->and($active->segments[1]->text)->toBe('semua')
        ->and((float) $active->segments[0]->startSeconds)->toBe(0.0)
        ->and((float) $active->segments[0]->endSeconds)->toBe(2.0)
        ->and((float) $active->segments[1]->startSeconds)->toBe(2.0)
        ->and((float) $active->segments[1]->endSeconds)->toBe(4.5)
        ->and($active->segments[0]->language->value)->toBe('ms')
        ->and($active->segments[1]->language->value)->toBe('ms')
        ->and($active->segments[0]->identity->key())->toStartWith('struct:')
        ->and($active->segments[1]->identity->key())->toStartWith('struct:')
        ->and($active->segments[0]->identity->key())->not->toBe('machine:0');

    expect(machineSnapshot($transcription))->toBe($machineBefore);
});

it('splits an existing active revision without mutating the prior revision', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_notice');

    $v2 = TranscriptRevisionModel::query()
        ->where('transcription_id', $transcription->getKey())
        ->where('version', 2)
        ->firstOrFail();

    $v2Before = TranscriptRevisionSegment::query()->where('revision_id', $v2->id)->orderBy('position')->get()
        ->map->only(['segment_key', 'position', 'start_seconds', 'end_seconds', 'text', 'language', 'language_provenance'])->all();

    $response = $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => $v2->id,
        'segment' => 'machine:1',
        'boundary' => 7.0,
        'text_offset' => 4,
    ]);

    $response->assertSessionHas('structural_notice');

    $v2After = TranscriptRevisionSegment::query()->where('revision_id', $v2->id)->orderBy('position')->get()
        ->map->only(['segment_key', 'position', 'start_seconds', 'end_seconds', 'text', 'language', 'language_provenance'])->all();

    expect($v2After)->toBe($v2Before)
        ->and(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(3);
});

it('rejects a split at the beginning or end with no write', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 0.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_error');

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 9,
    ])->assertSessionHas('structural_error');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0)
        ->and($transcription->fresh()->active_revision_id)->toBeNull();
});

it('merges adjacent segments with a single-space join and mixed-language provenance', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $response = $this->actingAs($owner)->post(mergeUrl($transcription), [
        'expected_base' => '',
        'segments' => ['machine:0', 'machine:1'],
    ]);

    $response->assertSessionHas('structural_notice');

    $active = app(RevisionService::class)->active($owner, $transcription);

    expect($active->segmentCount())->toBe(1)
        ->and($active->segments[0]->text)->toBe('Hai semua Welcome 欢迎')
        ->and($active->segments[0]->language->value)->toBe('und')
        ->and($active->segments[0]->languageProvenance?->toArray())->toBe(['ms', 'en'])
        ->and((float) $active->segments[0]->startSeconds)->toBe(0.0)
        ->and((float) $active->segments[0]->endSeconds)->toBe(9.25)
        ->and($active->segments[0]->identity->key())->toStartWith('struct:');

    $persisted = TranscriptRevisionSegment::query()
        ->where('revision_id', $active->revisionId)
        ->firstOrFail();

    expect($persisted->language_provenance)->toBe(['ms', 'en']);
});

it('rejects a non-adjacent merge with no write', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(
        segments: [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 2.0, 'text' => 'a', 'language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 2.0, 'end_seconds' => 4.0, 'text' => 'b', 'language' => 'en'],
            ['segment_index' => 2, 'start_seconds' => 4.0, 'end_seconds' => 6.0, 'text' => 'c', 'language' => 'en'],
        ],
    );
    $owner = $transcription->user;

    $this->actingAs($owner)->post(mergeUrl($transcription), [
        'expected_base' => '',
        'segments' => ['machine:0', 'machine:2'],
    ])->assertSessionHas('structural_error');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0);
});

it('rejects a stale structural base without changing persistence', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_notice');

    $countBefore = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count();
    $activeBefore = $transcription->fresh()->active_revision_id;

    // Composed against the machine source (expected base null) after the pointer
    // already moved: stale.
    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:1',
        'boundary' => 7.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_conflict');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe($countBefore)
        ->and($transcription->fresh()->active_revision_id)->toBe($activeBefore);
});

it('forbids a non-owner from splitting or merging', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertForbidden();

    $this->actingAs($intruder)->post(mergeUrl($transcription), [
        'expected_base' => '',
        'segments' => ['machine:0', 'machine:1'],
    ])->assertForbidden();

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0);
});

it('shows the structurally edited segments after a reload (durability)', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ]);

    $response = $this->actingAs($owner)->get(route('transcriptions.show', $transcription));

    $response->assertOk()
        ->assertSee('data-struct-toolbar', false)
        ->assertSee('data-struct-revision-state="revision"', false)
        ->assertSee('data-struct-split', false)
        ->assertSee('data-struct-select', false)
        ->assertSee('Hai ', false)
        ->assertSee('semua', false)
        ->assertSee('data-seek-seconds', false)
        ->assertSee('data-filter-language', false)
        ->assertSee('data-edit-enter', false)
        ->assertSee('data-timing-enter', false);

    preg_match_all('/data-seek-seconds="([^"]+)"/', $response->getContent(), $seek);

    expect(array_map('floatval', $seek[1]))->toBe([0.0, 2.0, 4.5]);
});

it('keeps P6-006 navigation position-based after a structural edit', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_notice');

    $html = $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->getContent();

    preg_match_all('/data-nav-seconds="([^"]+)"/', $html, $nav);
    preg_match_all('/data-segment-row\s+data-segment-index="(\d+)"/', $html, $indices);

    expect(array_map('floatval', $nav[1]))->toBe([0.0, 2.0, 4.5])
        ->and(array_map('intval', $indices[1]))->toBe([0, 1, 2]);
});

it('leaves structurally created revision segments unaligned in the P6-007 comparison', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_notice');

    $response = $this->actingAs($owner)->get(route('transcriptions.show', $transcription));

    $response->assertOk()
        ->assertSee('data-comparison-revision-state="alignment-unavailable"', false);

    // No comparison revision cell is mapped onto a machine index for the
    // struct:-identity children.
    expect($response->getContent())->toContain('Alignment with the machine source');
});

it('does not mutate the immutable machine source across split and merge', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $before = machineSnapshot($transcription);
    $segmentIds = $transcription->segments()->orderBy('segment_index')->pluck('id')->all();

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ]);

    $active = app(RevisionService::class)->active($owner, $transcription);
    $keys = array_map(fn (RevisionSegmentData $s): string => $s->identity->key(), $active->orderedSegments());

    $this->actingAs($owner)->post(mergeUrl($transcription), [
        'expected_base' => $active->revisionId,
        'segments' => [$keys[1], $keys[2]],
    ])->assertSessionHas('structural_notice');

    expect(machineSnapshot($transcription))->toBe($before)
        ->and($transcription->fresh()->segments()->orderBy('segment_index')->pluck('id')->all())->toBe($segmentIds);
});

it('invalidates every persisted translation for the transcription on a structural edit', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $ms = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'ms']);
    $en = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey(), 'target_language' => 'en']);

    $this->actingAs($owner)->post(splitUrl($transcription), [
        'expected_base' => '',
        'segment' => 'machine:0',
        'boundary' => 2.0,
        'text_offset' => 4,
    ])->assertSessionHas('structural_notice');

    expect($ms->fresh()->stale_at)->not->toBeNull()
        ->and($ms->fresh()->staleness_reason?->value)->toBe('SEGMENT_STRUCTURE_CHANGED')
        ->and($en->fresh()->stale_at)->not->toBeNull()
        ->and($en->fresh()->staleness_reason?->value)->toBe('SEGMENT_STRUCTURE_CHANGED');
});

it('never rewrites or remaps Phase 5 translation segments', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $translation = Translation::factory()->completed()->create(['transcription_id' => $transcription->getKey()]);
    $translation->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0.0,
        'end_seconds' => 4.5,
        'text' => 'translated first',
        'source_language' => 'ms',
    ]);

    $before = DB::table('translation_segments')->where('translation_id', $translation->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

    $this->actingAs($owner)->post(mergeUrl($transcription), [
        'expected_base' => '',
        'segments' => ['machine:0', 'machine:1'],
    ])->assertSessionHas('structural_notice');

    $after = DB::table('translation_segments')->where('translation_id', $translation->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

    expect($after)->toBe($before);
});
