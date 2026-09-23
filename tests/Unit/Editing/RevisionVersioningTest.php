<?php

use App\Editing\RevisionConflictException;
use App\Editing\RevisionFactory;
use App\Editing\TranscriptRevision;
use Illuminate\Support\Str;
use Tests\Support\InMemoryRevisionRepository;

/**
 * @return array{0: RevisionFactory, 1: InMemoryRevisionRepository}
 */
function editingVersioning(): array
{
    return [new RevisionFactory, new InMemoryRevisionRepository];
}

/**
 * Append a fresh derived revision to the repository and return it.
 */
function editingAppendDerived(
    RevisionFactory $factory,
    InMemoryRevisionRepository $repository,
    TranscriptRevision $base,
): TranscriptRevision {
    $revision = $factory->derive($base, 7, [], $repository);
    $repository->append($revision, $base->revisionId);

    return $revision;
}

it('allocates strictly increasing transcription-scoped versions for a linear edit sequence', function () {
    [$factory, $repository] = editingVersioning();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingAppendDerived($factory, $repository, $v1);
    $v3 = editingAppendDerived($factory, $repository, $v2);

    expect([$v1->version, $v2->version, $v3->version])->toBe([1, 2, 3])
        ->and(array_map(fn (TranscriptRevision $r): int => $r->version, $repository->historyFor(10)))
        ->toBe([1, 2, 3]);
});

it('does not reuse a version when branching after a single undo (original blocker regression)', function () {
    [$factory, $repository] = editingVersioning();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingAppendDerived($factory, $repository, $v1);
    $v3 = editingAppendDerived($factory, $repository, $v2);

    // Undo one step: active pointer moves back to v2.
    $repository->activate(10, $v2->revisionId, $v3->revisionId);

    // Edit again from the now-active v2 — must NOT reuse v3's version.
    $v4 = $factory->derive($v2, 7, [], $repository);
    $repository->append($v4, $v2->revisionId);

    $versions = array_map(fn (TranscriptRevision $r): int => $r->version, $repository->historyFor(10));

    expect($versions)->toBe([1, 2, 3, 4])
        ->and(count($versions))->toBe(count(array_unique($versions)))
        ->and($v4->version)->toBe(4)
        ->and($v4->parentRevisionId)->toBe($v2->revisionId);
});

it('does not reuse a version when branching after multiple undos', function () {
    [$factory, $repository] = editingVersioning();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingAppendDerived($factory, $repository, $v1);
    $v3 = editingAppendDerived($factory, $repository, $v2);
    $v4 = editingAppendDerived($factory, $repository, $v3);

    // Undo multiple steps: active pointer moves back to v1.
    $repository->activate(10, $v1->revisionId, $v4->revisionId);

    $v5 = $factory->derive($v1, 7, [], $repository);
    $repository->append($v5, $v1->revisionId);

    $versions = array_map(fn (TranscriptRevision $r): int => $r->version, $repository->historyFor(10));

    expect($versions)->toBe([1, 2, 3, 4, 5])
        ->and(count($versions))->toBe(count(array_unique($versions)))
        ->and($v5->version)->toBe(5)
        ->and($v5->parentRevisionId)->toBe($v1->revisionId);
});

it('keeps versions unique across multiple historical branches', function () {
    [$factory, $repository] = editingVersioning();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);

    // Branch A from v1.
    $branchA = editingAppendDerived($factory, $repository, $v1);

    // Undo to v1 and branch again.
    $repository->activate(10, $v1->revisionId, $branchA->revisionId);
    $branchB = editingAppendDerived($factory, $repository, $v1);

    // Undo to v1 and branch a third time.
    $repository->activate(10, $v1->revisionId, $branchB->revisionId);
    $branchC = editingAppendDerived($factory, $repository, $v1);

    $versions = array_map(fn (TranscriptRevision $r): int => $r->version, $repository->historyFor(10));

    expect($versions)->toBe([1, 2, 3, 4])
        ->and(count($versions))->toBe(count(array_unique($versions)))
        ->and($branchA->parentRevisionId)->toBe($v1->revisionId)
        ->and($branchB->parentRevisionId)->toBe($v1->revisionId)
        ->and($branchC->parentRevisionId)->toBe($v1->revisionId)
        ->and(count($repository->childrenOf($v1->revisionId)))->toBe(3);
});

it('rejects a non-monotonic version rather than allowing a (transcription_id, version) collision', function () {
    [$factory, $repository] = editingVersioning();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingAppendDerived($factory, $repository, $v1);
    $v3 = editingAppendDerived($factory, $repository, $v2);

    // Undo to v2, then hand-craft a revision that reuses v3's version. The
    // active-pointer CAS passes, so only the monotonic-version rule can stop
    // the duplicate — exactly the original BLOCKER scenario.
    $repository->activate(10, $v2->revisionId, $v3->revisionId);

    $duplicate = new TranscriptRevision(
        revisionId: (string) Str::uuid(),
        transcriptionId: 10,
        version: $v3->version,
        parentRevisionId: $v2->revisionId,
        createdBy: 7,
        createdAt: new DateTimeImmutable('2026-09-23 00:00:00'),
        segments: [],
    );

    expect(fn () => $repository->append($duplicate, $v2->revisionId))
        ->toThrow(RevisionConflictException::class);

    expect($repository->historyFor(10))->toHaveCount(3)
        ->and($repository->activeFor(10)?->revisionId)->toBe($v2->revisionId);
});

it('reports the next version as one past the transcription maximum and one when empty', function () {
    [$factory, $repository] = editingVersioning();

    expect($repository->nextVersionFor(10))->toBe(1);

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);

    expect($repository->nextVersionFor(10))->toBe(2);

    editingAppendDerived($factory, $repository, $v1);

    expect($repository->nextVersionFor(10))->toBe(3);
});

it('allows the same version number in different transcriptions', function () {
    [$factory, $repository] = editingVersioning();

    $a1 = $factory->materializeInitial(1, 7, []);
    $repository->append($a1, null);
    $a2 = editingAppendDerived($factory, $repository, $a1);

    $b1 = $factory->materializeInitial(2, 7, []);
    $repository->append($b1, null);
    $b2 = editingAppendDerived($factory, $repository, $b1);

    expect($a1->version)->toBe(1)
        ->and($b1->version)->toBe(1)
        ->and($a2->version)->toBe(2)
        ->and($b2->version)->toBe(2)
        ->and($a2->transcriptionId)->toBe(1)
        ->and($b2->transcriptionId)->toBe(2);
});

it('rejects a revision whose parent does not match the expected active revision', function () {
    [$factory, $repository] = editingVersioning();

    $v1 = $factory->materializeInitial(10, 7, []);
    $repository->append($v1, null);
    $v2 = editingAppendDerived($factory, $repository, $v1);

    // Hand-craft a revision that claims v1 as parent but is appended as if v2
    // were the active base. The active-pointer CAS passes; only the ancestry
    // rule catches the inconsistency.
    $orphan = new TranscriptRevision(
        revisionId: (string) Str::uuid(),
        transcriptionId: 10,
        version: $repository->nextVersionFor(10),
        parentRevisionId: $v1->revisionId,
        createdBy: 7,
        createdAt: new DateTimeImmutable('2026-09-23 00:00:00'),
        segments: [],
    );

    expect(fn () => $repository->append($orphan, $v2->revisionId))
        ->toThrow(InvalidArgumentException::class);

    expect($repository->historyFor(10))->toHaveCount(2);
});
