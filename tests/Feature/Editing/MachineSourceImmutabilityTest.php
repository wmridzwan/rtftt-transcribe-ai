<?php

use App\Editing\RevisionService;
use App\Models\TranscriptRevisionModel;
use App\Models\Translation;
use Tests\Support\EditingFixtures;
use Tests\Support\EditingPersistenceFixtures;

beforeEach(function (): void {
    $this->service = app(RevisionService::class);
});

it('never mutates machine source timing or text when materializing and editing', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $before = $transcription->segments()->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])
        ->all();

    $v1 = $this->service->materializeInitial($user, $transcription);
    $v2 = $this->service->edit($user, $transcription, $v1->revisionId, [
        EditingFixtures::segment(0, 100.0, 200.0, 'rewritten', identity: 'new-0'),
        EditingFixtures::segment(1, 200.0, 300.0, 'rewritten 2', identity: 'new-1'),
    ]);
    $this->service->undo($user, $transcription, $v1->revisionId);

    $after = $transcription->segments()->get()
        ->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])
        ->all();

    expect($after)->toBe($before)
        ->and($v2->orderedSegments()[0]->startSeconds)->toBe(100.0);
});

it('keeps revision timing edits confined to the revision layer', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $machineStart = (float) $transcription->segments()->first()->start_seconds;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $this->service->edit($user, $transcription, $v1->revisionId, [
        EditingFixtures::segment(0, 50.0, 60.0, 'moved', identity: 'new-0'),
    ]);

    expect((float) $transcription->segments()->first()->fresh()->start_seconds)->toBe($machineStart)
        ->and($machineStart)->toBe(0.0);
});

it('does not create or move translation data into the revision tables', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    expect(Translation::query()->count())->toBe(0)
        ->and(TranscriptRevisionModel::query()->count())->toBe(2);
});

it('leaves machine segment row counts and update timestamps unchanged', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $segmentsBefore = $transcription->segments()->get()->map(fn ($s) => [$s->getKey(), (string) $s->updated_at])->all();

    $v1 = $this->service->materializeInitial($user, $transcription);
    $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    $segmentsAfter = $transcription->segments()->get()->map(fn ($s) => [$s->getKey(), (string) $s->updated_at])->all();

    expect($segmentsAfter)->toBe($segmentsBefore);
});
