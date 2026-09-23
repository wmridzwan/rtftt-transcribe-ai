<?php

use App\Editing\RevisionFactory;
use App\Editing\TranscriptRevision;
use Tests\Support\InMemoryRevisionRepository;

/**
 * @return array{0: RevisionFactory, 1: InMemoryRevisionRepository}
 */
function editingRedo(): array
{
    return [new RevisionFactory, new InMemoryRevisionRepository];
}

function editingRedoAppend(
    RevisionFactory $factory,
    InMemoryRevisionRepository $repository,
    TranscriptRevision $base,
): TranscriptRevision {
    $revision = $factory->derive($base, 7, [], $repository);
    $repository->append($revision, $base->revisionId);

    return $revision;
}

it('supports undo then redo before any branch (redo target is the unique child)', function () {
    [$factory, $repository] = editingRedo();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingRedoAppend($factory, $repository, $v1);
    $v3 = editingRedoAppend($factory, $repository, $v2);

    // Undo: active pointer moves to v2.
    $repository->activate(10, $v2->revisionId, $v3->revisionId);

    expect($repository->redoTargetFor(10)?->revisionId)->toBe($v3->revisionId);

    // Redo: activate the deterministic redo target.
    $repository->activate(10, $v3->revisionId, $v2->revisionId);

    expect($repository->activeFor(10)?->revisionId)->toBe($v3->revisionId)
        ->and($repository->redoTargetFor(10))->toBeNull();
});

it('invalidates the old redo path once a new edit branches from an undone revision', function () {
    [$factory, $repository] = editingRedo();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingRedoAppend($factory, $repository, $v1);
    $v3 = editingRedoAppend($factory, $repository, $v2);

    // Undo to v2, then edit again: v2 now has two children (v3 and v4).
    $repository->activate(10, $v2->revisionId, $v3->revisionId);
    $v4 = editingRedoAppend($factory, $repository, $v2);

    // The new branch is the active forward history.
    expect($repository->activeFor(10)?->revisionId)->toBe($v4->revisionId);

    // Undo back to the branch point: automatic redo must not choose between
    // the two children.
    $repository->activate(10, $v2->revisionId, $v4->revisionId);

    expect($repository->redoTargetFor(10))->toBeNull()
        ->and(count($repository->childrenOf($v2->revisionId)))->toBe(2);
});

it('keeps the abandoned branch durable and visible in history', function () {
    [$factory, $repository] = editingRedo();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingRedoAppend($factory, $repository, $v1);
    $v3 = editingRedoAppend($factory, $repository, $v2);

    $repository->activate(10, $v2->revisionId, $v3->revisionId);
    $v4 = editingRedoAppend($factory, $repository, $v2);

    $history = $repository->historyFor(10);

    expect($history)->toHaveCount(4)
        ->and(array_map(fn (TranscriptRevision $r): string => $r->revisionId, $history))
        ->toContain($v3->revisionId)
        ->toContain($v4->revisionId)
        ->and($repository->find($v3->revisionId)?->revisionId)->toBe($v3->revisionId)
        ->and($repository->find($v4->revisionId)?->revisionId)->toBe($v4->revisionId);
});

it('records branch ancestry through parent_revision_id', function () {
    [$factory, $repository] = editingRedo();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingRedoAppend($factory, $repository, $v1);
    $v3 = editingRedoAppend($factory, $repository, $v2);

    $repository->activate(10, $v2->revisionId, $v3->revisionId);
    $v4 = editingRedoAppend($factory, $repository, $v2);

    $children = $repository->childrenOf($v2->revisionId);

    expect(array_map(fn (TranscriptRevision $r): int => $r->version, $children))->toBe([3, 4])
        ->and($v3->parentRevisionId)->toBe($v2->revisionId)
        ->and($v4->parentRevisionId)->toBe($v2->revisionId)
        ->and($repository->childrenOf($v3->revisionId))->toBe([]);
});

it('reports no redo target for the machine source or a tip with no child', function () {
    [$factory, $repository] = editingRedo();

    expect($repository->redoTargetFor(10))->toBeNull();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);

    expect($repository->redoTargetFor(10))->toBeNull();
});

it('deterministically follows the most recent branch as the active forward history', function () {
    [$factory, $repository] = editingRedo();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);

    // Two historical branches from v1, created by undo-then-edit each time.
    $branchA = editingRedoAppend($factory, $repository, $v1);
    $repository->activate(10, $v1->revisionId, $branchA->revisionId);
    $branchB = editingRedoAppend($factory, $repository, $v1);

    expect($repository->activeFor(10)?->revisionId)->toBe($branchB->revisionId)
        ->and($branchA->version)->toBe(2)
        ->and($branchB->version)->toBe(3)
        ->and(count($repository->childrenOf($v1->revisionId)))->toBe(2);
});
