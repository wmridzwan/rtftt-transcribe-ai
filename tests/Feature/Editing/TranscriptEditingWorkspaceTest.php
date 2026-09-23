<?php

use App\Editing\EditKind;
use App\Editing\RevisionService;
use App\Editing\TranslationInvalidationPolicy;
use App\Editing\TranslationStalenessReason;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EditingPersistenceFixtures;

/**
 * P6-003 workspace HTTP surface: text editing + undo/redo over the frozen
 * P6-001/P6-002 revision foundation.
 */
function editUrl(Transcription $transcription): string
{
    return route('transcriptions.revisions.store', $transcription);
}

function payload(array $texts, ?string $expectedBase): array
{
    return ['expected_base' => $expectedBase ?? '', 'segments' => $texts];
}

it('materializes the initial revision and appends the edit when editing the machine source', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $machineBefore = $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    $response = $this->actingAs($owner)->post(editUrl($transcription), payload([
        0 => 'Edited first',
        1 => 'Edited second',
    ], null));

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('revision_notice');

    $revisions = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->orderBy('version')->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions[0]->version)->toBe(1)
        ->and($revisions[0]->parent_revision_id)->toBeNull()
        ->and($revisions[1]->version)->toBe(2)
        ->and($revisions[1]->parent_revision_id)->toBe($revisions[0]->id)
        ->and($transcription->fresh()->active_revision_id)->toBe($revisions[1]->id);

    $machineAfter = $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    expect($machineAfter)->toBe($machineBefore);

    $active = app(RevisionService::class)->active($owner, $transcription);
    expect($active->segments[0]->text)->toBe('Edited first')
        ->and($active->segments[1]->text)->toBe('Edited second');
});

it('appends a new revision from an existing active revision without mutating prior rows', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'v2 first', 1 => 'v2 second'], null));

    // The first edit produced the initial revision (v1) and the active edit (v2).
    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();
    $v2SegmentsBefore = TranscriptRevisionSegment::query()->where('revision_id', $v2->id)->orderBy('position')->get()
        ->map->only(['segment_key', 'position', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    $response = $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'v3 first', 1 => 'v3 second'], $v2->id));
    $response->assertSessionHas('revision_notice');

    $revisions = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->orderBy('version')->get();

    expect($revisions)->toHaveCount(3)
        ->and($revisions[2]->parent_revision_id)->toBe($v2->id)
        ->and($transcription->fresh()->active_revision_id)->toBe($revisions[2]->id);

    $v2SegmentsAfter = TranscriptRevisionSegment::query()->where('revision_id', $v2->id)->orderBy('position')->get()
        ->map->only(['segment_key', 'position', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    expect($v2SegmentsAfter)->toBe($v2SegmentsBefore);
});

it('preserves identity, position, timing, and language when only text changes', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'text only', 1 => 'text only 2'], null));

    $active = app(RevisionService::class)->active($owner, $transcription);

    expect($active->segments[0]->identity->key())->toBe('machine:0')
        ->and($active->segments[0]->position)->toBe(0)
        ->and($active->segments[0]->startSeconds)->toBe(0.0)
        ->and($active->segments[0]->endSeconds)->toBe(4.5)
        ->and($active->segments[0]->language->value)->toBe('ms')
        ->and($active->segments[1]->identity->key())->toBe('machine:1')
        ->and($active->segments[1]->position)->toBe(1)
        ->and($active->segments[1]->startSeconds)->toBe(4.5)
        ->and($active->segments[1]->language->value)->toBe('en');
});

it('rejects a stale base without changing persistence and surfaces the conflict', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'saved', 1 => 'saved 2'], null));

    $countBefore = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count();
    $activeBefore = $transcription->fresh()->active_revision_id;

    // A second save composed against the machine source (expected base null) is
    // stale: the active pointer already moved to the first edit.
    $response = $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'stale', 1 => 'stale 2'], null));

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('revision_conflict');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe($countBefore)
        ->and($transcription->fresh()->active_revision_id)->toBe($activeBefore);
});

it('undoes to a strict ancestor and redoes to the unique child', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'v2', 1 => 'v2'], null));

    $v1 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 1)->firstOrFail();
    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();

    $this->actingAs($owner)->post(route('transcriptions.revisions.undo', $transcription), ['target' => $v1->id, 'expected_base' => $v2->id])
        ->assertSessionHas('revision_notice');

    expect($transcription->fresh()->active_revision_id)->toBe($v1->id);

    $this->actingAs($owner)->post(route('transcriptions.revisions.redo', $transcription), ['expected_base' => $v1->id])
        ->assertSessionHas('revision_notice');

    expect($transcription->fresh()->active_revision_id)->toBe($v2->id);
});

it('rejects a non-ancestor undo target and leaves the active pointer unchanged', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'v2', 1 => 'v2'], null));

    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();

    // Self is not a strict ancestor.
    $this->actingAs($owner)->post(route('transcriptions.revisions.undo', $transcription), ['target' => $v2->id, 'expected_base' => $v2->id])
        ->assertSessionHas('revision_error');

    expect($transcription->fresh()->active_revision_id)->toBe($v2->id);
});

it('branches after undo, invalidates automatic redo, and keeps old history durable', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'v2', 1 => 'v2'], null));

    $v1 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 1)->firstOrFail();

    // Undo to v1, then edit: this branches from v1 (a new sibling of v2).
    $this->actingAs($owner)->post(route('transcriptions.revisions.undo', $transcription), ['target' => $v1->id, 'expected_base' => TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->value('id')]);
    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'v3 branch', 1 => 'v3 branch'], $v1->id));

    $revisions = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->orderBy('version')->get();

    expect($revisions)->toHaveCount(3)
        ->and($revisions[2]->parent_revision_id)->toBe($v1->id)
        ->and(TranscriptRevisionModel::query()->where('parent_revision_id', $v1->id)->count())->toBe(2);

    // Branch point has two children: automatic redo is unavailable, not guessed.
    $this->actingAs($owner)->post(route('transcriptions.revisions.redo', $transcription), ['expected_base' => $revisions[2]->id])
        ->assertSessionHas('revision_error');

    expect($transcription->fresh()->active_revision_id)->toBe($revisions[2]->id);
});

it('classifies text edits as SourceTextChanged and persists no translation staleness', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'edited', 1 => 'edited'], null));

    expect(EditKind::Textual->stalenessReason())->toBe(TranslationStalenessReason::SourceTextChanged)
        ->and(TranslationInvalidationPolicy::reasonFor(EditKind::Textual))->toBe(TranslationStalenessReason::SourceTextChanged)
        ->and(TranslationInvalidationPolicy::mustMarkStale(EditKind::Textual))->toBeTrue();

    // P6-003 must not introduce the P6-005 staleness marker schema.
    expect(Schema::hasColumn('translations', 'stale_at'))->toBeFalse()
        ->and(Schema::hasColumn('translations', 'staleness_reason'))->toBeFalse();
});

it('surfaces the active-revision indicator and edit controls while keeping P6-006 and Phase 4 hooks', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))
        ->assertOk()
        ->assertSee('data-revision-indicator', false)
        ->assertSee('data-revision-state="machine"', false)
        ->assertSee('data-edit-enter', false)
        ->assertSee('data-edit-form', false)
        ->assertSee('data-filter-language', false)
        ->assertSee('data-nav-seconds', false)
        ->assertSee('data-seek-seconds', false)
        ->assertSee('data-segment-language', false)
        ->assertSee('data-transcript-region', false);
});

it('shows the edited text and active revision after a reload (durability)', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'Durable first', 1 => 'Durable second'], null));

    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))
        ->assertOk()
        ->assertSee('Durable first')
        ->assertSee('Durable second')
        ->assertSee('data-revision-state="revision"', false)
        ->assertSee('data-revision-version="2"', false);
});

it('forbids a non-owner from editing, undoing, or redoing', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;
    $intruder = User::factory()->create();

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'v2', 1 => 'v2'], null));

    $v1 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 1)->firstOrFail();
    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();

    $this->actingAs($intruder)->post(editUrl($transcription), payload([0 => 'hack', 1 => 'hack'], $v2->id))->assertForbidden();
    $this->actingAs($intruder)->post(route('transcriptions.revisions.undo', $transcription), ['target' => $v1->id, 'expected_base' => $v2->id])->assertForbidden();
    $this->actingAs($intruder)->post(route('transcriptions.revisions.redo', $transcription), ['expected_base' => $v1->id])->assertForbidden();

    expect($transcription->fresh()->active_revision_id)->toBe($v2->id);
});

it('does not change the machine source across edit, undo, and redo', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $before = $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    $this->actingAs($owner)->post(editUrl($transcription), payload([0 => 'edit', 1 => 'edit'], null));

    $v1 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 1)->firstOrFail();
    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();

    $this->actingAs($owner)->post(route('transcriptions.revisions.undo', $transcription), ['target' => $v1->id, 'expected_base' => $v2->id]);
    $this->actingAs($owner)->post(route('transcriptions.revisions.redo', $transcription), ['expected_base' => $v1->id]);

    $after = $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    expect($after)->toBe($before);
});
