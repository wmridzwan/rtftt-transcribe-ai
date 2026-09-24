<?php

use App\Editing\EditKind;
use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Editing\RevisionService;
use App\Editing\TimingInvariants;
use App\Editing\TranslationInvalidationPolicy;
use App\Editing\TranslationStalenessReason;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EditingPersistenceFixtures;

/**
 * P6-004 workspace HTTP surface: timing editing + validation over the frozen
 * P6-001/P6-002 revision foundation.
 */
function timingEditUrl(Transcription $transcription): string
{
    return route('transcriptions.revisions.timing', $transcription);
}

/**
 * @param  array<int, array{start: int|float|string, end: int|float|string}>  $timings
 */
function timingPayload(array $timings, ?string $expectedBase): array
{
    return ['expected_base' => $expectedBase ?? '', 'timings' => $timings];
}

function timingMachineSnapshot(Transcription $transcription): array
{
    return $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();
}

it('materializes the initial revision and appends the timing edit when editing the machine source', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $machineBefore = timingMachineSnapshot($transcription);

    $response = $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 1.0, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null));

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('timing_notice');

    $revisions = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->orderBy('version')->get();

    expect($revisions)->toHaveCount(2)
        ->and($revisions[0]->version)->toBe(1)
        ->and($revisions[0]->parent_revision_id)->toBeNull()
        ->and($revisions[1]->version)->toBe(2)
        ->and($revisions[1]->parent_revision_id)->toBe($revisions[0]->id)
        ->and($transcription->fresh()->active_revision_id)->toBe($revisions[1]->id);

    expect(timingMachineSnapshot($transcription))->toBe($machineBefore);

    $active = app(RevisionService::class)->active($owner, $transcription);

    expect($active->segments[0]->startSeconds)->toBe(1.0)
        ->and($active->segments[0]->endSeconds)->toBe(3.0)
        ->and($active->segments[0]->text)->toBe('Hai semua')
        ->and($active->segments[0]->language->value)->toBe('ms')
        ->and($active->segments[0]->identity->key())->toBe('machine:0')
        ->and($active->segments[0]->position)->toBe(0)
        ->and($active->segments[1]->startSeconds)->toBe(4.5)
        ->and($active->segments[1]->text)->toBe('Welcome 欢迎');
});

it('appends a new revision from an existing active revision and changes only timing', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 1.0, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null));

    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();
    $v2Before = TranscriptRevisionSegment::query()->where('revision_id', $v2->id)->orderBy('position')->get()
        ->map->only(['segment_key', 'position', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    $response = $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 0.25, 'end' => 2.5],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], $v2->id));

    $response->assertSessionHas('timing_notice');

    $revisions = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->orderBy('version')->get();

    expect($revisions)->toHaveCount(3)
        ->and($revisions[2]->parent_revision_id)->toBe($v2->id)
        ->and($transcription->fresh()->active_revision_id)->toBe($revisions[2]->id);

    $v2After = TranscriptRevisionSegment::query()->where('revision_id', $v2->id)->orderBy('position')->get()
        ->map->only(['segment_key', 'position', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    expect($v2After)->toBe($v2Before);

    $active = app(RevisionService::class)->active($owner, $transcription);

    expect($active->segments[0]->startSeconds)->toBe(0.25)
        ->and($active->segments[0]->text)->toBe('Hai semua')
        ->and($active->segments[0]->language->value)->toBe('ms')
        ->and($active->segments[0]->identity->key())->toBe('machine:0');
});

it('accepts overlap, nested overlap, equal ends, zero-length, and out-of-time-order positions', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    // Segment 0 is nested inside/overlapping segment 1; segment 1 is moved
    // earlier than segment 0 (out of time order); both end together; segment 0
    // is not zero-length here.
    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 6.0, 'end' => 8.0],
        1 => ['start' => 2.0, 'end' => 8.0],
    ], null))->assertSessionHas('timing_notice');

    $active = app(RevisionService::class)->active($owner, $transcription);

    expect($active->segments[0]->startSeconds)->toBe(6.0)
        ->and($active->segments[0]->endSeconds)->toBe(8.0)
        ->and($active->segments[1]->startSeconds)->toBe(2.0)
        ->and($active->segments[1]->endSeconds)->toBe(8.0);
});

it('persists a zero-length segment without making it active', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 5.0, 'end' => 5.0],
        1 => ['start' => 5.0, 'end' => 9.0],
    ], null))->assertSessionHas('timing_notice');

    $active = app(RevisionService::class)->active($owner, $transcription);

    expect($active->segments[0]->isZeroLength())->toBeTrue()
        ->and(TimingInvariants::activeAt($active->segments, 5.0)?->position)->toBe(1);
});

it('rejects a negative timestamp with an accessible error and no write', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $response = $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => -1.0, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null));

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('timing_error');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0)
        ->and($transcription->fresh()->active_revision_id)->toBeNull();
});

it('rejects start greater than end with no write', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 8.0, 'end' => 2.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null))->assertSessionHas('timing_error');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0);
});

it('rejects sub-millisecond precision with no silent rounding', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 1.2345, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null))->assertSessionHas('timing_error');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0);
});

it('rejects a stale base without changing persistence and surfaces the conflict', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 1.0, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null))->assertSessionHas('timing_notice');

    $countBefore = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count();
    $activeBefore = $transcription->fresh()->active_revision_id;

    // Composed against the machine source (expected base null) but the pointer
    // has already moved to the first timing edit: stale.
    $response = $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 2.0, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null));

    $response->assertRedirect(route('transcriptions.show', $transcription))
        ->assertSessionHas('timing_conflict');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe($countBefore)
        ->and($transcription->fresh()->active_revision_id)->toBe($activeBefore);
});

it('forbids a non-owner from saving a timing edit', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 1.0, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null))->assertForbidden();

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(0)
        ->and($transcription->fresh()->active_revision_id)->toBeNull();
});

it('classifies timing edits as TimingChanged and persists no translation staleness', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $translation = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
    ]);

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 1.0, 'end' => 3.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null));

    expect(EditKind::Timing->stalenessReason())->toBe(TranslationStalenessReason::TimingChanged)
        ->and(TranslationInvalidationPolicy::reasonFor(EditKind::Timing))->toBe(TranslationStalenessReason::TimingChanged)
        ->and(TranslationInvalidationPolicy::mustMarkStale(EditKind::Timing))->toBeTrue();

    // P6-005 owns the persisted marker; the timing path deliberately does not
    // write staleness (structural split/merge is P6-005's invalidation trigger).
    expect(Schema::hasColumn('translations', 'stale_at'))->toBeTrue()
        ->and($translation->fresh()->stale_at)->toBeNull()
        ->and($translation->fresh()->staleness_reason)->toBeNull();
});

it('projects playback seek/navigation from active-revision timing and leaves machine timing authoritative without a revision', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    // Machine source drives playback before any edit.
    $machineHtml = $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->getContent();

    preg_match_all('/data-nav-seconds="([^"]+)"/', $machineHtml, $machineNav);
    preg_match_all('/data-seek-seconds="([^"]+)"/', $machineHtml, $machineSeek);

    expect(array_map('floatval', $machineNav[1]))->toBe([0.0, 4.5])
        ->and(array_map('floatval', $machineSeek[1]))->toBe([0.0, 4.5]);

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 5.0, 'end' => 9.0],
        1 => ['start' => 4.5, 'end' => 9.25],
    ], null))->assertSessionHas('timing_notice');

    $html = $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->getContent();

    preg_match_all('/data-nav-seconds="([^"]+)"/', $html, $nav);
    preg_match_all('/data-seek-seconds="([^"]+)"/', $html, $seek);
    preg_match_all('/data-timing-current-start="([^"]+)"/', $html, $currentStart);
    preg_match_all('/data-timing-current-end="([^"]+)"/', $html, $currentEnd);

    expect(array_map('floatval', $nav[1]))->toBe([5.0, 4.5])
        ->and(array_map('floatval', $seek[1]))->toBe([5.0, 4.5])
        ->and(array_map('floatval', $currentStart[1]))->toBe([5.0, 4.5])
        ->and(array_map('floatval', $currentEnd[1]))->toBe([9.0, 9.25]);

    // Active-segment resolution uses the active revision timing: at t=5.0 the
    // lowest-position overlapping segment (position 0) wins, whereas the machine
    // source would resolve position 1. Machine rows themselves are untouched.
    $active = app(RevisionService::class)->active($owner, $transcription);
    expect(TimingInvariants::activeAt($active->segments, 5.0)?->position)->toBe(0);

    $machineSegments = $transcription->fresh()->segments()->orderBy('segment_index')->get();
    $machineRevisionData = array_map(fn ($segment): RevisionSegmentData => new RevisionSegmentData(
        identity: RevisionSegmentIdentity::forMachineSegment($segment->segment_index),
        position: $segment->segment_index,
        startSeconds: $segment->start_seconds,
        endSeconds: $segment->end_seconds,
        text: $segment->text,
        language: $segment->language,
    ), $machineSegments->all());

    expect(TimingInvariants::activeAt($machineRevisionData, 5.0)?->position)->toBe(1);
});

it('renders dedicated data-timing-* hooks while preserving reserved Phase 4, P6-003, and P6-006 hooks', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))
        ->assertOk()
        ->assertSee('data-timing-toolbar', false)
        ->assertSee('data-timing-enter', false)
        ->assertSee('data-timing-form', false)
        ->assertSee('data-timing-save', false)
        ->assertSee('data-timing-cancel', false)
        ->assertSee('data-timing-start-input', false)
        ->assertSee('data-timing-end-input', false)
        ->assertSee('data-timing-current', false)
        ->assertSee('data-timing-revision-indicator', false)
        ->assertSee('data-seek-seconds', false)
        ->assertSee('data-segment-language', false)
        ->assertSee('data-edit-enter', false)
        ->assertSee('data-edit-form', false)
        ->assertSee('data-revision-indicator', false)
        ->assertSee('data-filter-language', false)
        ->assertSee('data-nav-seconds', false);
});

it('shows the edited timing and active revision after a reload (durability)', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $owner = $transcription->user;

    $this->actingAs($owner)->post(timingEditUrl($transcription), timingPayload([
        0 => ['start' => 2.25, 'end' => 6.5],
        1 => ['start' => 6.5, 'end' => 11.0],
    ], null));

    $response = $this->actingAs($owner)->get(route('transcriptions.show', $transcription));

    $response->assertOk()
        ->assertSee('data-timing-revision-state="revision"', false)
        ->assertSee('data-timing-revision-version="2"', false);

    $html = $response->getContent();
    preg_match_all('/data-timing-current-start="([^"]+)"/', $html, $starts);
    preg_match_all('/data-timing-current-end="([^"]+)"/', $html, $ends);

    expect(array_map('floatval', $starts[1]))->toBe([2.25, 6.5])
        ->and(array_map('floatval', $ends[1]))->toBe([6.5, 11.0]);
});
