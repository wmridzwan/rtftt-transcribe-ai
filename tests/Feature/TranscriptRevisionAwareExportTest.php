<?php

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionService;
use App\Enums\TranscriptionStatus;
use App\Models\Transcription;
use App\Models\User;
use Tests\Support\EditingPersistenceFixtures;
use ZipArchive;

/**
 * P6-010 revision-aware export remediation: supported exports derive content
 * from the canonical active revision with machine-source fallback.
 */
function rwx10Segments(): array
{
    return [
        ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.0, 'text' => 'Hai semua ✓', 'language' => 'ms'],
        ['segment_index' => 1, 'start_seconds' => 4.0, 'end_seconds' => 8.0, 'text' => 'Hello world ✓', 'language' => 'en'],
        ['segment_index' => 2, 'start_seconds' => 8.0, 'end_seconds' => 12.0, 'text' => '你好世界 ✓', 'language' => 'zh'],
        ['segment_index' => 3, 'start_seconds' => 12.0, 'end_seconds' => 16.0, 'text' => 'வணக்கம் ✓', 'language' => 'ta'],
        ['segment_index' => 4, 'start_seconds' => 16.0, 'end_seconds' => 20.0, 'text' => 'undetermined ✓', 'language' => 'und'],
    ];
}

function rwx10MachineSnapshot(Transcription $transcription): array
{
    return $transcription->segments()->orderBy('segment_index')->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();
}

function rwx10DocxXml(string $content): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'rwx10').'.zip';
    file_put_contents($tmp, $content);
    $zip = new ZipArchive;
    $zip->open($tmp);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($tmp);

    return $xml;
}

function rwx10EditedTranscription(): array
{
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: rwx10Segments());
    $owner = $transcription->user;
    $service = app(RevisionService::class);

    $v1 = $service->materializeInitial($owner, $transcription);
    $service->edit($owner, $transcription, $v1->revisionId, array_map(
        fn ($segment) => new RevisionSegmentData(
            identity: $segment->identity,
            position: $segment->position,
            startSeconds: $segment->startSeconds,
            endSeconds: $segment->endSeconds,
            text: $segment->text.' (edited)',
            language: $segment->language,
        ),
        $v1->segments,
    ));

    return [$transcription->fresh(), $owner];
}

it('AC1/AC8: TXT export reflects the active revision and leaves machine rows unchanged', function () {
    [$transcription, $owner] = rwx10EditedTranscription();
    $machineBefore = rwx10MachineSnapshot($transcription);

    $response = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk();
    $body = $response->getContent();

    expect($body)->toContain('Hai semua ✓ (edited)')
        ->and($body)->toContain('வணக்கம் ✓ (edited)')
        ->and($body)->not->toContain('Hai semua ✓'."\n\nHello world ✓")
        ->and(rwx10MachineSnapshot($transcription))->toBe($machineBefore);
});

it('AC2: SRT export reflects active revision text, order, and timing', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: rwx10Segments());
    $owner = $transcription->user;
    $service = app(RevisionService::class);

    $v1 = $service->materializeInitial($owner, $transcription);
    $service->edit($owner, $transcription, $v1->revisionId, array_map(
        fn ($segment) => new RevisionSegmentData(
            identity: $segment->identity,
            position: $segment->position,
            startSeconds: $segment->startSeconds,
            endSeconds: $segment->position === 2 ? 12.5 : $segment->endSeconds,
            text: $segment->text.' (edited)',
            language: $segment->language,
        ),
        $v1->segments,
    ));

    $body = $this->actingAs($owner)->get(route('transcriptions.export.srt', $transcription))->assertOk()->getContent();

    expect($body)->toContain('1'."\n00:00:00,000 --> 00:00:04,000\nHai semua ✓ (edited)")
        ->and($body)->toContain('00:00:08,000 --> 00:00:12,500'."\n你好世界 ✓ (edited)");
});

it('AC3: VTT export reflects active revision text, order, and timing', function () {
    [$transcription, $owner] = rwx10EditedTranscription();

    $body = $this->actingAs($owner)->get(route('transcriptions.export.vtt', $transcription))->assertOk()->getContent();

    expect($body)->toStartWith('WEBVTT')
        ->and($body)->toContain('00:00:12.000 --> 00:00:16.000'."\nவணக்கம் ✓ (edited)");
});

it('AC4/AC10: DOCX export reflects the active revision with unicode intact and opens valid', function () {
    [$transcription, $owner] = rwx10EditedTranscription();

    $response = $this->actingAs($owner)->get(route('transcriptions.export.docx', $transcription))->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $xml = rwx10DocxXml($response->getContent());

    expect($xml)->toContain('Hai semua ✓ (edited)')
        ->and($xml)->toContain('你好世界 ✓ (edited)')
        ->and($xml)->toContain('வணக்கம் ✓ (edited)');
});

it('AC5: split and merge active revisions export with corrected segmentation', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: rwx10Segments());
    $owner = $transcription->user;
    $service = app(RevisionService::class);

    $v1 = $service->materializeInitial($owner, $transcription);
    $service->split($owner, $transcription, $v1->revisionId, 'machine:0', 2.0, 3);

    $splitBody = $this->actingAs($owner)->get(route('transcriptions.export.srt', $transcription))->assertOk()->getContent();

    // Six entries: the split children share the original interval.
    expect(substr_count($splitBody, '-->'))->toBe(6)
        ->and($splitBody)->toContain('00:00:00,000 --> 00:00:02,000')
        ->and($splitBody)->toContain('00:00:02,000 --> 00:00:04,000');

    $active = $service->active($owner, $transcription);
    $children = array_values(array_filter(
        array_map(fn ($segment) => (string) $segment->identity, $active->segments),
        fn (string $key) => str_starts_with($key, 'struct:'),
    ));
    $service->merge($owner, $transcription, $active->revisionId, $children);

    $mergedBody = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk()->getContent();
    $mergedSrt = $this->actingAs($owner)->get(route('transcriptions.export.srt', $transcription))->assertOk()->getContent();

    // Approved merge semantics (DECISION-P6-005-MERGE-JOIN-001, plain-space
    // join): the split preserved the interior space on the second child, so
    // the merged text carries a double space. Frozen behaviour, not a finding.
    expect($mergedBody)->toContain('Hai  semua ✓')
        ->and(substr_count($mergedSrt, '-->'))->toBe(5);
});

it('AC5/AC6: branch and historical activation change export output accordingly', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: rwx10Segments());
    $owner = $transcription->user;
    $service = app(RevisionService::class);

    $v1 = $service->materializeInitial($owner, $transcription);
    $service->edit($owner, $transcription, $v1->revisionId, array_map(
        fn ($segment) => new RevisionSegmentData(
            identity: $segment->identity, position: $segment->position,
            startSeconds: $segment->startSeconds, endSeconds: $segment->endSeconds,
            text: 'branch-A', language: $segment->language,
        ),
        $v1->segments,
    ));
    $service->undo($owner, $transcription, $v1->revisionId);
    $branch = $service->edit($owner, $transcription, $v1->revisionId, array_map(
        fn ($segment) => new RevisionSegmentData(
            identity: $segment->identity, position: $segment->position,
            startSeconds: $segment->startSeconds, endSeconds: $segment->endSeconds,
            text: 'branch-B', language: $segment->language,
        ),
        $v1->segments,
    ));

    $branchBody = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk()->getContent();
    expect($branchBody)->toContain('branch-B')->and($branchBody)->not->toContain('branch-A');

    // Activate the sibling (v2): export follows the newly active revision.
    $sibling = $service->history($owner, $transcription)[1];
    $service->activateHistorical($owner, $transcription, $sibling->revisionId, $branch->revisionId);

    $siblingBody = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk()->getContent();
    expect($siblingBody)->toContain('branch-A')->and($siblingBody)->not->toContain('branch-B');
});

it('AC7: machine-source fallback applies only when no valid active revision exists', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: rwx10Segments());
    $owner = $transcription->user;

    expect($transcription->active_revision_id)->toBeNull();

    $txt = $this->actingAs($owner)->get(route('transcriptions.export.txt', $transcription))->assertOk()->getContent();
    $srt = $this->actingAs($owner)->get(route('transcriptions.export.srt', $transcription))->assertOk()->getContent();

    expect($txt)->toContain('Hai semua ✓')
        ->and($txt)->not->toContain('(edited)')
        ->and($srt)->toContain('00:00:00,000 --> 00:00:04,000'."\nHai semua ✓");
});

it('AC9: authorization, isolation, and completion gating are unchanged on all formats', function () {
    [$transcription] = rwx10EditedTranscription();
    $intruder = User::factory()->create();

    foreach (['txt', 'srt', 'vtt', 'docx'] as $format) {
        $this->actingAs($intruder)->get(route("transcriptions.export.{$format}", $transcription))->assertForbidden();
    }

    $draft = EditingPersistenceFixtures::completedTranscription(segments: rwx10Segments());
    $draft->update(['status' => TranscriptionStatus::Draft]);

    foreach (['txt', 'srt', 'vtt', 'docx'] as $format) {
        $this->actingAs($draft->user)->get(route("transcriptions.export.{$format}", $draft))->assertForbidden();
    }
});
