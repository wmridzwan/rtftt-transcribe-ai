<?php

use App\Editing\EditKind;
use App\Editing\RevisionSegmentData;
use App\Editing\RevisionService;
use App\Editing\TranslationInvalidationPolicy;
use App\Editing\TranslationStalenessReason;
use App\Models\Transcription;
use App\Models\TranscriptRevisionModel;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EditingPersistenceFixtures;
use ZipArchive;

/**
 * P6-009 FINAL_GATE_ONLY execution: fresh integrated verification of the
 * completed Phase 6 system. Each test maps to gate acceptance criteria.
 *
 * Rerun P6-009-RERUN-01 (2026-09-25): the original execution recorded AC6 as
 * gate finding F-001 with a committed skip (see
 * verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md §4, preserved as
 * historical evidence and never modified). P6-010 remediated F-001 and is
 * DONE; this rerun replaces the skip below with fresh integrated AC6 proof.
 */
function p6009Segments(): array
{
    return [
        ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.0, 'text' => 'Hai semua', 'language' => 'ms'],
        ['segment_index' => 1, 'start_seconds' => 4.0, 'end_seconds' => 8.0, 'text' => 'Hello world', 'language' => 'en'],
        ['segment_index' => 2, 'start_seconds' => 8.0, 'end_seconds' => 12.0, 'text' => '你好世界', 'language' => 'zh'],
        ['segment_index' => 3, 'start_seconds' => 12.0, 'end_seconds' => 16.0, 'text' => 'வணக்கம்', 'language' => 'ta'],
        ['segment_index' => 4, 'start_seconds' => 16.0, 'end_seconds' => 20.0, 'text' => ' undisclosed ', 'language' => 'und'],
    ];
}

function p6009MachineSnapshot(Transcription $transcription): array
{
    return $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();
}

function p6009ActiveRevisionId(Transcription $transcription): ?string
{
    return $transcription->fresh()->active_revision_id;
}

function p6009DocxXml(string $content): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'p6009').'.zip';
    file_put_contents($tmp, $content);
    $zip = new ZipArchive;
    $zip->open($tmp);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($tmp);

    return $xml;
}

it('AC1: text edit, timing edit, split, and merge round-trip through persistence and reload with a coherent active revision', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;
    $machineBefore = p6009MachineSnapshot($transcription);

    Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'source_language' => 'en',
    ]);

    // Text edit from the machine source (materializes v1, appends v2).
    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => '',
        'segments' => [0 => 'Hai semua!', 1 => 'Hello world', 2 => '你好世界', 3 => 'வணக்கம்', 4 => ' undisclosed '],
    ])->assertSessionHas('revision_notice');

    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();

    // Timing edit from v2 (appends v3).
    $this->actingAs($owner)->post(route('transcriptions.revisions.timing', $transcription), [
        'expected_base' => $v2->id,
        'timings' => [
            0 => ['start' => 0.0, 'end' => 4.0],
            1 => ['start' => 4.0, 'end' => 8.0],
            2 => ['start' => 8.0, 'end' => 12.5],
            3 => ['start' => 12.0, 'end' => 16.0],
            4 => ['start' => 16.0, 'end' => 20.0],
        ],
    ])->assertSessionHas('timing_notice');

    $v3 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 3)->firstOrFail();

    // Structural split of the carried machine:2 identity (appends v4).
    $this->actingAs($owner)->post(route('transcriptions.revisions.split', $transcription), [
        'expected_base' => $v3->id,
        'segment' => 'machine:2',
        'boundary' => 10.0,
        'text_offset' => 2,
    ])->assertSessionHas('structural_notice');

    $v4 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 4)->firstOrFail();

    // Merge the two structural children back (appends v5).
    $service = app(RevisionService::class);
    $active = $service->active($owner, $transcription);
    $children = array_values(array_filter(
        array_map(fn ($segment) => (string) $segment->identity, $active->segments),
        fn (string $key) => str_starts_with($key, 'struct:'),
    ));
    expect($children)->toHaveCount(2);

    $this->actingAs($owner)->post(route('transcriptions.revisions.merge', $transcription), [
        'expected_base' => $v4->id,
        'segments' => $children,
    ])->assertSessionHas('structural_notice');

    // Reload: five durable revisions, active pointer on v5, machine untouched.
    $reloaded = $transcription->fresh();
    $revisions = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->orderBy('version')->get();

    expect($revisions)->toHaveCount(5)
        ->and($revisions[0]->parent_revision_id)->toBeNull()
        ->and($revisions[4]->version)->toBe(5)
        ->and($reloaded->active_revision_id)->toBe($revisions[4]->id)
        ->and(p6009MachineSnapshot($transcription))->toBe($machineBefore);

    $final = $service->active($owner, $transcription);
    expect($final->segments)->toHaveCount(5)
        ->and($final->segments[0]->text)->toBe('Hai semua!')
        ->and($final->segments[2]->endSeconds)->toBe(12.5);

    // Workspace renders the integrated state.
    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->assertOk()
        ->assertSee('Revision v5', false)
        ->assertSee('Hai semua!', false)
        ->assertSee('data-history-list', false)
        ->assertSee('data-compare-view', false)
        ->assertSee('data-translation-stale="ms"', false);
});

it('AC2: strict-ancestor undo, unique-child redo, branch invalidation, sibling activation, and stale-base rejection', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;
    $service = app(RevisionService::class);

    $v1 = $service->materializeInitial($owner, $transcription);

    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => $v1->revisionId,
        'segments' => [0 => 'v2-a', 1 => 'v2-b', 2 => 'v2-c', 3 => 'v2-d', 4 => 'v2-e'],
    ])->assertSessionHas('revision_notice');

    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();

    // Undo to the strict ancestor v1.
    $this->actingAs($owner)->post(route('transcriptions.revisions.undo', $transcription), [
        'target' => $v1->revisionId,
        'expected_base' => $v2->id,
    ])->assertSessionHas('revision_notice');

    expect(p6009ActiveRevisionId($transcription))->toBe($v1->revisionId);

    // Redo is available while the unique child still exists.
    $this->actingAs($owner)->post(route('transcriptions.revisions.redo', $transcription), [
        'expected_base' => $v1->revisionId,
    ])->assertSessionHas('revision_notice');

    expect(p6009ActiveRevisionId($transcription))->toBe($v2->id);

    // Undo again, then branch: the prior redo path is invalidated.
    $this->actingAs($owner)->post(route('transcriptions.revisions.undo', $transcription), [
        'target' => $v1->revisionId,
        'expected_base' => $v2->id,
    ])->assertSessionHas('revision_notice');

    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => $v1->revisionId,
        'segments' => [0 => 'v3-a', 1 => 'v3-b', 2 => 'v3-c', 3 => 'v3-d', 4 => 'v3-e'],
    ])->assertSessionHas('revision_notice');

    expect($service->redoTarget($owner, $transcription))->toBeNull();

    $sibling = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();
    $branch = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 3)->firstOrFail();

    // Arbitrary sibling-branch activation reaches beyond undo range.
    $this->actingAs($owner)->post(route('transcriptions.revisions.activate', $transcription), [
        'target' => $sibling->id,
        'expected_base' => $branch->id,
    ])->assertSessionHas('history_notice');

    expect(p6009ActiveRevisionId($transcription))->toBe($sibling->id);

    // Stale base fails closed: no new revision, pointer unmoved.
    $countBefore = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count();

    $this->actingAs($owner)->post(route('transcriptions.revisions.activate', $transcription), [
        'target' => $branch->id,
        'expected_base' => 'stale-base-revision-id',
    ])->assertSessionHas('history_conflict');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe($countBefore)
        ->and(p6009ActiveRevisionId($transcription))->toBe($sibling->id);
});

it('AC3: the machine source survives structural edits, undo branches, and activation without mutation', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;
    $machineBefore = p6009MachineSnapshot($transcription);
    $service = app(RevisionService::class);

    $v1 = $service->materializeInitial($owner, $transcription);
    $service->split($owner, $transcription, $v1->revisionId, 'machine:0', 2.0, 3);
    $service->undo($owner, $transcription, $v1->revisionId);

    $branch = $service->edit($owner, $transcription, $v1->revisionId, array_map(
        fn ($segment) => new RevisionSegmentData(
            identity: $segment->identity,
            position: $segment->position,
            startSeconds: $segment->startSeconds,
            endSeconds: $segment->endSeconds,
            text: $segment->text.' (branch)',
            language: $segment->language,
        ),
        $v1->segments,
    ));

    $moved = $service->activateHistorical($owner, $transcription, $v1->revisionId, $branch->revisionId);
    expect($moved)->toBeTrue();

    expect(p6009MachineSnapshot($transcription))->toBe($machineBefore);

    // The machine-source state is product-visible on a revision-free transcript.
    $plain = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $this->actingAs($plain->user)->get(route('transcriptions.show', $plain))->assertOk()
        ->assertSee('Machine transcript', false)
        ->assertSee('Hai semua', false);
});

it('AC4: structural edits persist P6-005 staleness with precedence while text/timing edits persist none (HPO-008-C)', function () {
    expect(TranslationInvalidationPolicy::reasonForKinds(EditKind::Structural, EditKind::Timing, EditKind::Textual))
        ->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and(TranslationInvalidationPolicy::reasonForKinds(EditKind::Timing, EditKind::Textual))
        ->toBe(TranslationStalenessReason::TimingChanged);

    // Structural path: split marks the persisted translation stale.
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;

    $translation = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'source_language' => 'en',
    ]);
    $translation->segments()->create([
        'segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.0,
        'text' => 'Persisted translated text', 'source_language' => 'ms',
    ]);
    $segmentsBefore = $translation->segments()->orderBy('id')->get()->map(fn ($row) => $row->toArray())->all();

    $service = app(RevisionService::class);
    $v1 = $service->materializeInitial($owner, $transcription);
    $split = $service->split($owner, $transcription, $v1->revisionId, 'machine:0', 2.0, 3);

    $fresh = $translation->fresh();
    expect($fresh->stale_at)->not->toBeNull()
        ->and($fresh->staleness_reason)->toBe(TranslationStalenessReason::SegmentStructureChanged)
        ->and($fresh->stale_caused_by_revision_id)->toBe($split->revisionId)
        ->and($fresh->segments()->orderBy('id')->get()->map(fn ($row) => $row->toArray())->all())->toBe($segmentsBefore);

    // HPO-008-C preserved: text and timing edits classify but persist nothing.
    $textual = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $textualOwner = $textual->user;
    $textualTranslation = Translation::factory()->completed()->create([
        'transcription_id' => $textual->getKey(),
        'target_language' => 'en',
        'source_language' => 'ms',
    ]);

    $this->actingAs($textualOwner)->post(route('transcriptions.revisions.store', $textual), [
        'expected_base' => '',
        'segments' => [0 => 'changed', 1 => 'Hello world', 2 => '你好世界', 3 => 'வணக்கம்', 4 => ' undisclosed '],
    ])->assertSessionHas('revision_notice');

    $textualV2 = TranscriptRevisionModel::query()->where('transcription_id', $textual->getKey())->where('version', 2)->firstOrFail();
    $this->actingAs($textualOwner)->post(route('transcriptions.revisions.timing', $textual), [
        'expected_base' => $textualV2->id,
        'timings' => [
            0 => ['start' => 0.0, 'end' => 4.0],
            1 => ['start' => 4.0, 'end' => 8.0],
            2 => ['start' => 8.0, 'end' => 12.0],
            3 => ['start' => 12.0, 'end' => 16.5],
            4 => ['start' => 16.0, 'end' => 20.0],
        ],
    ])->assertSessionHas('timing_notice');

    expect($textualTranslation->fresh()->stale_at)->toBeNull();
});

it('AC5: the workspace and comparison reflect the active revision truthfully with no false staleness claims', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;

    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => '',
        'segments' => [0 => 'Gate edited first', 1 => 'Hello world', 2 => '你好世界', 3 => 'வணக்கம்', 4 => ' undisclosed '],
    ])->assertSessionHas('revision_notice');

    // No translation persisted: no chronology claim may render.
    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->assertOk()
        ->assertSee('Revision v2', false)
        ->assertSee('Gate edited first', false)
        ->assertSee('data-comparison-state="revision"', false)
        ->assertSee('No translation available', false)
        ->assertDontSee('Edited after the translation was produced', false);

    Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'source_language' => 'en',
    ]);

    // With a persisted translation and an edited active revision, the
    // mismatch note is truthful and the per-row provenance note is gated.
    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->assertOk()
        ->assertSee('data-comparison-state="revision"', false)
        ->assertSee('Translation: MS', false)
        ->assertSee('is not a translation of the edited text', false);
});

it('AC6: TXT/SRT/VTT/DOCX exports reflect the active revision with machine fallback (P6-009-RERUN-01)', function () {
    // Fresh integrated proof for the remediated F-001 (P6-010 DONE): exports
    // derive content from the canonical active revision on the product path.
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;
    $machineBefore = p6009MachineSnapshot($transcription);
    $service = app(RevisionService::class);

    // Text edit through the product path (materializes v1, appends v2).
    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => '',
        'segments' => [0 => 'Rerun edited pertama ✓', 1 => 'Rerun edited second ✓', 2 => '重写第三 ✓', 3 => 'திருத்தப்பட்டது ✓', 4 => 'rerun und ✓'],
    ])->assertSessionHas('revision_notice');

    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();

    // Timing edit through the product path (appends v3; position 2 ends 12.5).
    $this->actingAs($owner)->post(route('transcriptions.revisions.timing', $transcription), [
        'expected_base' => $v2->id,
        'timings' => [
            0 => ['start' => 0.0, 'end' => 4.0],
            1 => ['start' => 4.0, 'end' => 8.0],
            2 => ['start' => 8.0, 'end' => 12.5],
            3 => ['start' => 12.0, 'end' => 16.0],
            4 => ['start' => 16.0, 'end' => 20.0],
        ],
    ])->assertSessionHas('timing_notice');

    // TXT reflects the active revision text/order (unicode intact).
    $txt = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk()->getContent();
    expect($txt)->toContain('Rerun edited pertama ✓')
        ->and($txt)->toContain('Rerun edited second ✓')
        ->and($txt)->toContain('重写第三 ✓')
        ->and($txt)->toContain('திருத்தப்பட்டது ✓')
        ->and($txt)->toContain('rerun und ✓')
        ->and($txt)->not->toContain('Hai semua')
        ->and($txt)->not->toContain('Hello world');

    // SRT reflects active text + active timing at millisecond precision.
    $srt = $this->actingAs($owner)->get(route('transcriptions.export.srt', $transcription))->assertOk()->getContent();
    expect($srt)->toContain('1'."\n00:00:00,000 --> 00:00:04,000\nRerun edited pertama ✓")
        ->and($srt)->toContain('00:00:08,000 --> 00:00:12,500'."\n重写第三 ✓")
        ->and($srt)->not->toContain('Hello world');

    // VTT carries the WEBVTT header and the same active rows.
    $vtt = $this->actingAs($owner)->get(route('transcriptions.export.vtt', $transcription))->assertOk()->getContent();
    expect($vtt)->toStartWith('WEBVTT')
        ->and($vtt)->toContain('00:00:08.000 --> 00:00:12.500'."\n重写第三 ✓")
        ->and($vtt)->toContain('திருத்தப்பட்டது ✓')
        ->and($vtt)->not->toContain('Hello world');

    // DOCX is a valid document carrying the active revision text.
    $docxResponse = $this->actingAs($owner)->get(route('transcriptions.export.docx', $transcription))->assertOk();
    $docxResponse->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $xml = p6009DocxXml($docxResponse->getContent());
    expect($xml)->toContain('Rerun edited pertama ✓')
        ->and($xml)->toContain('重写第三 ✓')
        ->and($xml)->toContain('திருத்தப்பட்டது ✓')
        ->and($xml)->not->toContain('Hello world');

    // Structural case: split the active revision; exports follow the new
    // segmentation (6 SRT entries with the split interior boundary).
    $v3 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 3)->firstOrFail();
    $service->split($owner, $transcription, $v3->id, 'machine:0', 2.0, 6);

    $splitSrt = $this->actingAs($owner)->get(route('transcriptions.export.srt', $transcription))->assertOk()->getContent();
    expect(substr_count($splitSrt, '-->'))->toBe(6)
        ->and($splitSrt)->toContain('00:00:00,000 --> 00:00:02,000')
        ->and($splitSrt)->toContain('00:00:02,000 --> 00:00:04,000');

    // Historical activation changes subsequent export without new rows and
    // without touching the machine source.
    $countBefore = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count();
    $history = $service->history($owner, $transcription);
    $activeId = p6009ActiveRevisionId($transcription);
    $sibling = $v2->id === $activeId ? $history[0] : $history[1];
    expect($service->activateHistorical($owner, $transcription, $sibling->revisionId, $activeId))->toBeTrue();

    $afterActivation = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk()->getContent();
    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe($countBefore);

    if ($sibling->revisionId === $v2->id) {
        expect($afterActivation)->toContain('Rerun edited second ✓');
    } else {
        expect($afterActivation)->toContain('Hai semua');
    }

    // Machine-source fallback: a revision-free transcript exports machine rows.
    $plain = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    expect($plain->fresh()->active_revision_id)->toBeNull();

    $fallbackTxt = $this->actingAs($plain->user)->get(route('transcriptions.export.txt', $plain))->assertOk()->getContent();
    $fallbackSrt = $this->actingAs($plain->user)->get(route('transcriptions.export.srt', $plain))->assertOk()->getContent();
    expect($fallbackTxt)->toContain('Hai semua')
        ->and($fallbackTxt)->not->toContain('Rerun edited')
        ->and($fallbackSrt)->toContain('00:00:00,000 --> 00:00:04,000'."\nHai semua");

    // Machine rows were never mutated by editing or exporting.
    expect(p6009MachineSnapshot($transcription))->toBe($machineBefore);
});

it('AC7: stale and conflicting writes fail closed with no partial mutation', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;

    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => '',
        'segments' => [0 => 'v2-a', 1 => 'v2-b', 2 => 'v2-c', 3 => 'v2-d', 4 => 'v2-e'],
    ])->assertSessionHas('revision_notice');

    $v2 = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->where('version', 2)->firstOrFail();
    $countBefore = TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count();

    // Stale text-edit base.
    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => 'stale-base-revision-id',
        'segments' => [0 => 'lost-a', 1 => 'lost-b', 2 => 'lost-c', 3 => 'lost-d', 4 => 'lost-e'],
    ])->assertSessionHas('revision_conflict');

    // Simulated concurrent double-submit: the second write with the same
    // consumed base also fails closed.
    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => $v2->id,
        'segments' => [0 => 'v3-a', 1 => 'v3-b', 2 => 'v3-c', 3 => 'v3-d', 4 => 'v3-e'],
    ])->assertSessionHas('revision_notice');

    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => $v2->id,
        'segments' => [0 => 'lost-a', 1 => 'lost-b', 2 => 'lost-c', 3 => 'lost-d', 4 => 'lost-e'],
    ])->assertSessionHas('revision_conflict');

    expect(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe($countBefore + 1);

    $active = app(RevisionService::class)->active($owner, $transcription);
    expect($active->version)->toBe(3)
        ->and($active->segments[0]->text)->toBe('v3-a');
});

it('AC8: non-owners, cross-transcription targets, and invalid revisions are rejected; admins retain policy access', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;
    $intruder = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $other = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $otherOwner = $other->user;
    $otherRevision = app(RevisionService::class)->materializeInitial($otherOwner, $other);

    $intruderPayload = ['expected_base' => '', 'segments' => [0 => 'x', 1 => 'x', 2 => 'x', 3 => 'x', 4 => 'x']];

    $this->actingAs($intruder)->get(route('transcriptions.show', $transcription))->assertForbidden();
    $this->actingAs($intruder)->post(route('transcriptions.revisions.store', $transcription), $intruderPayload)->assertForbidden();
    $this->actingAs($intruder)->post(route('transcriptions.revisions.activate', $transcription), [
        'target' => $otherRevision->revisionId,
    ])->assertForbidden();
    $this->actingAs($intruder)->get(route('transcriptions.export.txt', $transcription))->assertForbidden();

    // Cross-transcription activation is rejected without moving the pointer.
    $mine = app(RevisionService::class)->materializeInitial($owner, $transcription);
    $this->actingAs($owner)->post(route('transcriptions.revisions.activate', $transcription), [
        'target' => $otherRevision->revisionId,
        'expected_base' => $mine->revisionId,
    ])->assertSessionHas('history_error');

    expect($transcription->fresh()->active_revision_id)->toBe($mine->revisionId);

    // Unknown revision ids are rejected.
    $this->actingAs($owner)->post(route('transcriptions.revisions.undo', $transcription), [
        'target' => '00000000-0000-0000-0000-000000000000',
        'expected_base' => $mine->revisionId,
    ])->assertSessionHas('revision_error');

    // Admin path follows the existing owner-or-admin policy.
    $this->actingAs($admin)->get(route('transcriptions.show', $transcription))->assertOk();
    $this->actingAs($admin)->post(route('transcriptions.revisions.activate', $transcription), [
        'target' => $mine->revisionId,
        'expected_base' => $mine->revisionId,
    ])->assertSessionHas('history_notice');
});

it('AC9: ms, en, zh, ta, and und content round-trips through editing, rendering, and export without corruption', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $owner = $transcription->user;

    $edited = [0 => 'Hai semua ✓', 1 => 'Hello world ✓', 2 => '你好世界 ✓', 3 => 'வணக்கம் ✓', 4 => 'und ✓'];

    $this->actingAs($owner)->post(route('transcriptions.revisions.store', $transcription), [
        'expected_base' => '',
        // Note: the P6-003 edit path trims input whitespace, so ' und ✓ '
        // persists as 'und ✓'. Observed frozen behaviour, not a gate finding.
        'segments' => [0 => 'Hai semua ✓', 1 => 'Hello world ✓', 2 => '你好世界 ✓', 3 => 'வணக்கம் ✓', 4 => ' und ✓ '],
    ])->assertSessionHas('revision_notice');

    $active = app(RevisionService::class)->active($owner, $transcription);
    $texts = array_map(fn ($segment) => $segment->text, $active->segments);
    expect(array_values($texts))->toBe(array_values($edited));

    $languages = array_map(fn ($segment) => $segment->language, $active->segments);
    expect(array_map(fn ($language) => $language->value, $languages))->toBe(['ms', 'en', 'zh', 'ta', 'und']);

    $response = $this->actingAs($owner)->get(route('transcriptions.show', $transcription))->assertOk();
    foreach (['Hai semua ✓', '你好世界 ✓', 'வணக்கம் ✓'] as $needle) {
        $response->assertSee($needle, false);
    }

    // Revision-aware export path (P6-010) preserves the active-revision
    // Unicode bytes: the export is authoritative for the active revision,
    // whose ✓-marked strings must appear verbatim in every payload.
    $export = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk();
    foreach (['Hai semua ✓', 'Hello world ✓', '你好世界 ✓', 'வணக்கம் ✓', 'und ✓'] as $needle) {
        $export->assertSee($needle, false);
    }

    $srtExport = $this->actingAs($owner)->get(route('transcriptions.export.srt', $transcription))->assertOk()->getContent();
    expect($srtExport)->toContain('Hai semua ✓')
        ->and($srtExport)->toContain('你好世界 ✓')
        ->and($srtExport)->toContain('வணக்கம் ✓');

    $vttExport = $this->actingAs($owner)->get(route('transcriptions.export.vtt', $transcription))->assertOk()->getContent();
    expect($vttExport)->toStartWith('WEBVTT')
        ->and($vttExport)->toContain('Hello world ✓')
        ->and($vttExport)->toContain('你好世界 ✓');

    $docxXml = p6009DocxXml($this->actingAs($owner)->get(route('transcriptions.export.docx', $transcription))->assertOk()->getContent());
    expect($docxXml)->toContain('Hai semua ✓')
        ->and($docxXml)->toContain('你好世界 ✓')
        ->and($docxXml)->toContain('வணக்கம் ✓');
});

it('AC10: no D6-08 or D6-09 surface exists in routes, schema, or workspace', function () {
    foreach (['speaker', 'speakers', 'diarization', 'waveform', 'waveforms', 'annotations', 'chapters', 'bookmarks'] as $name) {
        expect(Route::has($name))->toBeFalse();
    }

    foreach (['speaker_labels', 'speakers', 'waveforms', 'annotations', 'chapters', 'bookmarks'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }

    $transcription = EditingPersistenceFixtures::completedTranscription(segments: p6009Segments());
    $this->actingAs($transcription->user)->get(route('transcriptions.show', $transcription))->assertOk()
        ->assertDontSee('data-speaker', false)
        ->assertDontSee('data-waveform', false)
        ->assertDontSee('data-annotation', false);
});
