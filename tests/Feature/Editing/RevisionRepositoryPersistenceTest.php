<?php

use App\Editing\RevisionConflictException;
use App\Editing\RevisionRepository;
use App\Editing\RevisionService;
use App\Models\TranscriptRevisionModel;
use App\Models\TranscriptRevisionSegment;
use App\Transcription\LanguageIdentifier;
use Tests\Support\EditingFixtures;
use Tests\Support\EditingPersistenceFixtures;

beforeEach(function (): void {
    $this->service = app(RevisionService::class);
    $this->repository = app(RevisionRepository::class);
});

it('materializes the initial revision from the immutable machine source and round-trips it', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    expect($this->repository->activeFor($transcription->getKey()))->toBeNull();

    $revision = $this->service->materializeInitial($user, $transcription);

    expect($revision->version)->toBe(1)
        ->and($revision->parentRevisionId)->toBeNull()
        ->and($revision->createdBy)->toBe($user->getKey())
        ->and($revision->segmentCount())->toBe(2)
        ->and($revision->orderedSegments()[0]->text)->toBe('Hai semua')
        ->and($revision->orderedSegments()[0]->language)->toBe(LanguageIdentifier::Malay)
        ->and((float) $revision->orderedSegments()[0]->endSeconds)->toBe(4.5)
        ->and($revision->orderedSegments()[0]->identity->key())->toBe('machine:0');

    $reloaded = $this->repository->find($revision->revisionId);

    expect($reloaded)->not->toBeNull()
        ->and($reloaded->revisionId)->toBe($revision->revisionId)
        ->and($reloaded->transcriptionId)->toBe($transcription->getKey())
        ->and($reloaded->version)->toBe(1)
        ->and($reloaded->orderedSegments()[1]->text)->toBe('Welcome 欢迎')
        ->and($reloaded->orderedSegments()[1]->language)->toBe(LanguageIdentifier::English)
        ->and($reloaded->orderedSegments()[1]->identity->key())->toBe('machine:1')
        ->and($reloaded->orderedSegments()[1]->position)->toBe(1);

    expect($transcription->fresh()->active_revision_id)->toBe($revision->revisionId)
        ->and($this->repository->activeFor($transcription->getKey())?->revisionId)->toBe($revision->revisionId);
});

it('keeps history ordered by version and appends monotonically across undo-then-branch', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $v2 = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());
    $v3 = $this->service->edit($user, $transcription, $v2->revisionId, EditingFixtures::sequence());

    // Undo one step.
    $this->service->undo($user, $transcription, $v2->revisionId);

    // Edit again from the now-active v2: must not reuse v3's version.
    $v4 = $this->service->edit($user, $transcription, $v2->revisionId, EditingFixtures::sequence());

    $history = $this->repository->historyFor($transcription->getKey());
    $versions = array_map(fn ($r): int => $r->version, $history);

    expect($versions)->toBe([1, 2, 3, 4])
        ->and(count($versions))->toBe(count(array_unique($versions)))
        ->and($v4->version)->toBe(4)
        ->and($v4->parentRevisionId)->toBe($v2->revisionId)
        ->and($this->repository->activeFor($transcription->getKey())?->revisionId)->toBe($v4->revisionId);
});

it('preserves multiple historical branches and exposes branch ancestry', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $branchA = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    $this->service->undo($user, $transcription, $v1->revisionId);
    $branchB = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    $this->service->undo($user, $transcription, $v1->revisionId);
    $branchC = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    $children = $this->repository->childrenOf($v1->revisionId);

    expect(array_map(fn ($r): int => $r->version, $children))->toBe([2, 3, 4])
        ->and($branchA->parentRevisionId)->toBe($v1->revisionId)
        ->and($branchB->parentRevisionId)->toBe($v1->revisionId)
        ->and($branchC->parentRevisionId)->toBe($v1->revisionId)
        ->and($this->repository->historyFor($transcription->getKey()))->toHaveCount(4);
});

it('supports deterministic redo before branching and invalidates it at a branch point', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $v2 = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());
    $v3 = $this->service->edit($user, $transcription, $v2->revisionId, EditingFixtures::sequence());

    $this->service->undo($user, $transcription, $v2->revisionId);

    expect($this->repository->redoTargetFor($transcription->getKey())?->revisionId)->toBe($v3->revisionId);

    $redone = $this->service->redo($user, $transcription);

    expect($redone->revisionId)->toBe($v3->revisionId);

    // Branch from v2, then undo back to the branch point: redo is unavailable.
    $this->service->undo($user, $transcription, $v2->revisionId);
    $v4 = $this->service->edit($user, $transcription, $v2->revisionId, EditingFixtures::sequence());
    $this->service->undo($user, $transcription, $v2->revisionId);

    expect($this->repository->redoTargetFor($transcription->getKey()))->toBeNull()
        ->and(count($this->repository->childrenOf($v2->revisionId)))->toBe(2)
        ->and($this->repository->find($v3->revisionId))->not->toBeNull()
        ->and($this->repository->find($v4->revisionId))->not->toBeNull();
});

it('rejects a stale base append without a partial write', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $v2 = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    // v1 is no longer active: editing from it must be a stale-write conflict.
    expect(fn () => $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence()))
        ->toThrow(RevisionConflictException::class);

    expect($this->repository->historyFor($transcription->getKey()))->toHaveCount(2)
        ->and($this->repository->activeFor($transcription->getKey())?->revisionId)->toBe($v2->revisionId);
});

it('compare-and-sets the active pointer and rejects a stale activation', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);
    $v2 = $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence());

    $this->service->undo($user, $transcription, $v1->revisionId);

    expect($this->repository->activeFor($transcription->getKey())?->revisionId)->toBe($v1->revisionId);

    // A caller holding the now-stale v2 token cannot move the pointer.
    expect(fn () => $this->repository->activate($transcription->getKey(), $v1->revisionId, $v2->revisionId))
        ->toThrow(RevisionConflictException::class);

    expect(fn () => $this->repository->activate($transcription->getKey(), $v2->revisionId, 'stale-token'))
        ->toThrow(RevisionConflictException::class);

    expect($this->repository->activeFor($transcription->getKey())?->revisionId)->toBe($v1->revisionId);
});

it('rejects activation of an unknown revision or one from another transcription', function () {
    $a = EditingPersistenceFixtures::completedTranscription();
    $b = EditingPersistenceFixtures::completedTranscription();

    $this->service->materializeInitial($a->user, $a);
    $revisionB = $this->service->materializeInitial($b->user, $b);

    $activeA = $a->fresh()->active_revision_id;

    expect(fn () => $this->repository->activate($a->getKey(), 'missing', $activeA))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->repository->activate($a->getKey(), $revisionB->revisionId, $activeA))
        ->toThrow(InvalidArgumentException::class);
});

it('round-trips multilingual Unicode text, overlap, and zero-length timings', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription(segments: [
        ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 1.0, 'text' => 'ms', 'language' => 'ms'],
        ['segment_index' => 1, 'start_seconds' => 1.0, 'end_seconds' => 2.0, 'text' => 'en', 'language' => 'en'],
        ['segment_index' => 2, 'start_seconds' => 2.0, 'end_seconds' => 3.0, 'text' => '中文', 'language' => 'zh'],
        ['segment_index' => 3, 'start_seconds' => 3.0, 'end_seconds' => 4.0, 'text' => 'தமிழ்', 'language' => 'ta'],
        ['segment_index' => 4, 'start_seconds' => 4.0, 'end_seconds' => 5.0, 'text' => 'und', 'language' => 'und'],
    ]);
    $user = $transcription->user;

    $initial = $this->service->materializeInitial($user, $transcription);

    $edited = $this->service->edit($user, $transcription, $initial->revisionId, [
        EditingFixtures::segment(0, 0.0, 2.0, 'overlapping 中文', identity: 'new-0'),
        EditingFixtures::segment(1, 1.0, 1.0, 'zero-length தமிழ்', identity: 'new-1'),
        EditingFixtures::segment(2, 5.0, 9.5, 'later', identity: 'new-2'),
    ]);

    $reloaded = $this->repository->find($edited->revisionId);

    expect($reloaded->segmentCount())->toBe(3)
        ->and($reloaded->orderedSegments()[0]->text)->toBe('overlapping 中文')
        ->and($reloaded->orderedSegments()[1]->isZeroLength())->toBeTrue()
        ->and($reloaded->orderedSegments()[1]->text)->toBe('zero-length தமிழ்')
        ->and($reloaded->orderedSegments()[2]->identity->key())->toBe('new-2');
});

it('rolls back the whole append when segment persistence fails', function () {
    $transcription = EditingPersistenceFixtures::completedTranscription();
    $user = $transcription->user;

    $v1 = $this->service->materializeInitial($user, $transcription);

    $writes = 0;
    $armed = true;

    TranscriptRevisionSegment::creating(function () use (&$writes, &$armed): void {
        if (! $armed) {
            return;
        }

        $writes++;

        if ($writes >= 2) {
            throw new RuntimeException('forced segment persistence failure');
        }
    });

    expect(fn () => $this->service->edit($user, $transcription, $v1->revisionId, EditingFixtures::sequence()))
        ->toThrow(RuntimeException::class);

    $armed = false;

    // No orphan revision row and the active pointer is unchanged.
    expect($this->repository->historyFor($transcription->getKey()))->toHaveCount(1)
        ->and($this->repository->activeFor($transcription->getKey())?->revisionId)->toBe($v1->revisionId)
        ->and(TranscriptRevisionModel::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1)
        ->and(TranscriptRevisionSegment::query()->count())->toBe(2);
});

it('returns null for an unknown revision id', function () {
    expect($this->repository->find('does-not-exist'))->toBeNull();
});
