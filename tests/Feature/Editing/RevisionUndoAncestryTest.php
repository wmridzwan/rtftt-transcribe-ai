<?php

use App\Editing\Persistence\MachineSourceMaterializer;
use App\Editing\RevisionConflictException;
use App\Editing\RevisionFactory;
use App\Editing\RevisionRepository;
use App\Editing\RevisionService;
use App\Editing\TranscriptRevision;
use App\Editing\UndoUnavailableException;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\EditingPersistenceFixtures;
use Tests\Support\InMemoryRevisionRepository;

/**
 * P6-002 corrective — strict-ancestor undo contract.
 *
 * Every scenario is exercised against both the durable Eloquent repository and
 * the in-memory reference repository through the same authorization-fenced
 * {@see RevisionService}, so the two backends cannot diverge observably and the
 * in-memory double can never be more permissive than production persistence.
 */
dataset('undo backends', ['eloquent', 'in-memory']);

/**
 * @return array{0: RevisionRepository, 1: RevisionService, 2: Transcription, 3: User, 4: Transcription}
 */
function undoBackend(string $backend): array
{
    $owner = User::factory()->create();
    $transcription = EditingPersistenceFixtures::completedTranscription($owner);
    $foreign = EditingPersistenceFixtures::completedTranscription();

    $repository = $backend === 'eloquent'
        ? app(RevisionRepository::class)
        : new InMemoryRevisionRepository;

    $service = new RevisionService($repository, new RevisionFactory, app(MachineSourceMaterializer::class));

    return [$repository, $service, $transcription, $owner, $foreign];
}

function undoAppend(RevisionFactory $factory, RevisionRepository $repository, TranscriptRevision $base): TranscriptRevision
{
    $revision = $factory->derive($base, $base->createdBy, [], $repository);
    $repository->append($revision, $base->revisionId);

    return $revision;
}

/**
 * Build a branched graph inside one transcription:
 *
 *   v1 -> v2 -> v3 -> v5        (active branch, v5 is active)
 *          \-> v4               (sibling branch of v3, born from a v2 undo)
 *   v1 -> v2r -> v3r            (cousin line, born from a v1 undo)
 *
 * Final active revision: v5.
 *
 * @return array<string, TranscriptRevision>
 */
function undoAncestryGraph(RevisionRepository $repository, int $transcriptionId, int $actorId): array
{
    $factory = new RevisionFactory;

    $v1 = $factory->materializeInitial($transcriptionId, $actorId, []);
    $repository->append($v1, null);

    $v2 = undoAppend($factory, $repository, $v1);
    $v3 = undoAppend($factory, $repository, $v2);
    $v5 = undoAppend($factory, $repository, $v3);

    // Undo to v2 and branch: v3 and v4 become siblings under v2.
    $repository->activate($transcriptionId, $v2->revisionId, $v5->revisionId);
    $v4 = undoAppend($factory, $repository, $v2);

    // Undo to v1 and branch a cousin line: v2r and v2 become siblings under v1.
    $repository->activate($transcriptionId, $v1->revisionId, $v4->revisionId);
    $v2r = undoAppend($factory, $repository, $v1);
    $v3r = undoAppend($factory, $repository, $v2r);

    // Restore the active pointer to the tip of the main branch.
    $repository->activate($transcriptionId, $v5->revisionId, $v3r->revisionId);

    return compact('v1', 'v2', 'v3', 'v4', 'v5', 'v2r', 'v3r');
}

it('undoes to a direct parent, a grandparent, and a deep ancestor', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    $service->undo($owner, $transcription, $graph['v3']->revisionId);
    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v3']->revisionId);

    $service->redo($owner, $transcription);

    $service->undo($owner, $transcription, $graph['v2']->revisionId);
    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v2']->revisionId);

    $service->undo($owner, $transcription, $graph['v1']->revisionId);
    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v1']->revisionId);
})->with('undo backends');

it('undoes to an ancestor on the active branch after a sibling branch exists', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    $service->undo($owner, $transcription, $graph['v2']->revisionId);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v2']->revisionId)
        ->and($repository->historyFor($transcription->getKey()))->toHaveCount(7);
})->with('undo backends');

it('accepts undo when the explicit expected pointer matches the active revision', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    $service->undo($owner, $transcription, $graph['v1']->revisionId, $graph['v5']->revisionId);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v1']->revisionId);
})->with('undo backends');

it('rejects undoing to the current revision itself and leaves state intact', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());
    $before = $repository->historyFor($transcription->getKey());

    expect(fn () => $service->undo($owner, $transcription, $graph['v5']->revisionId))
        ->toThrow(UndoUnavailableException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v5']->revisionId)
        ->and($repository->historyFor($transcription->getKey()))->toEqual($before);
})->with('undo backends');

it('rejects undoing to a sibling revision', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    // Move the active pointer onto v3 so v4 is a genuine sibling of the active.
    $service->undo($owner, $transcription, $graph['v3']->revisionId);
    $before = $repository->historyFor($transcription->getKey());

    expect(fn () => $service->undo($owner, $transcription, $graph['v4']->revisionId))
        ->toThrow(UndoUnavailableException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v3']->revisionId)
        ->and($repository->historyFor($transcription->getKey()))->toEqual($before);
})->with('undo backends');

it('rejects undoing to a cousin revision', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    expect(fn () => $service->undo($owner, $transcription, $graph['v3r']->revisionId))
        ->toThrow(UndoUnavailableException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v5']->revisionId);
})->with('undo backends');

it('rejects undoing to a descendant revision', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    $service->undo($owner, $transcription, $graph['v3']->revisionId);

    expect(fn () => $service->undo($owner, $transcription, $graph['v5']->revisionId))
        ->toThrow(UndoUnavailableException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v3']->revisionId);
})->with('undo backends');

it('rejects undoing to an old descendant from an abandoned branch', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    // Raw pointer primitive: move active onto the sibling branch tip v4.
    $repository->activate($transcription->getKey(), $graph['v4']->revisionId, $graph['v5']->revisionId);
    $before = $repository->historyFor($transcription->getKey());

    expect(fn () => $service->undo($owner, $transcription, $graph['v3']->revisionId))
        ->toThrow(UndoUnavailableException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v4']->revisionId)
        ->and($repository->historyFor($transcription->getKey()))->toEqual($before);
})->with('undo backends');

it('rejects undoing to an unrelated revision from the same transcription', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    // v4 is neither an ancestor of the active v5 nor a descendant: unrelated.
    expect(fn () => $service->undo($owner, $transcription, $graph['v4']->revisionId))
        ->toThrow(UndoUnavailableException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v5']->revisionId);
})->with('undo backends');

it('rejects undoing to a revision from another transcription', function (string $backend) {
    [$repository, $service, $transcription, $owner, $foreign] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    $factory = new RevisionFactory;
    $foreignRevision = $factory->materializeInitial($foreign->getKey(), $owner->getKey(), []);
    $repository->append($foreignRevision, null);

    expect(fn () => $service->undo($owner, $transcription, $foreignRevision->revisionId))
        ->toThrow(InvalidArgumentException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v5']->revisionId)
        ->and($repository->activeFor($foreign->getKey())?->revisionId)->toBe($foreignRevision->revisionId);
})->with('undo backends');

it('rejects undoing to an unknown revision id', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());

    expect(fn () => $service->undo($owner, $transcription, 'missing-revision-id'))
        ->toThrow(InvalidArgumentException::class);
})->with('undo backends');

it('forbids a non-owner from undoing', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());
    $intruder = User::factory()->create();

    expect(fn () => $service->undo($intruder, $transcription, $graph['v3']->revisionId))
        ->toThrow(AuthorizationException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v5']->revisionId)
        ->and($repository->historyFor($transcription->getKey()))->toHaveCount(7);
})->with('undo backends');

it('refuses undo while the machine source is active', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);

    expect($repository->activeFor($transcription->getKey()))->toBeNull();

    expect(fn () => $service->undo($owner, $transcription, 'some-revision-id'))
        ->toThrow(UndoUnavailableException::class);
})->with('undo backends');

it('rejects a stale expected active pointer without moving it', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $graph = undoAncestryGraph($repository, $transcription->getKey(), $owner->getKey());
    $before = $repository->historyFor($transcription->getKey());

    expect(fn () => $service->undo($owner, $transcription, $graph['v1']->revisionId, $graph['v3']->revisionId))
        ->toThrow(RevisionConflictException::class);

    expect($repository->activeFor($transcription->getKey())?->revisionId)->toBe($graph['v5']->revisionId)
        ->and($repository->historyFor($transcription->getKey()))->toEqual($before);
})->with('undo backends');

it('keeps redo semantics unchanged around a valid undo', function (string $backend) {
    [$repository, $service, $transcription, $owner] = undoBackend($backend);
    $factory = new RevisionFactory;

    $v1 = $factory->materializeInitial($transcription->getKey(), $owner->getKey(), []);
    $repository->append($v1, null);
    $v2 = undoAppend($factory, $repository, $v1);
    $v3 = undoAppend($factory, $repository, $v2);

    expect($service->redoTarget($owner, $transcription))->toBeNull();

    $service->undo($owner, $transcription, $v2->revisionId);

    expect($repository->redoTargetFor($transcription->getKey())?->revisionId)->toBe($v3->revisionId);

    $redone = $service->redo($owner, $transcription);

    expect($redone->revisionId)->toBe($v3->revisionId)
        ->and($repository->activeFor($transcription->getKey())?->revisionId)->toBe($v3->revisionId)
        ->and($service->redoTarget($owner, $transcription))->toBeNull();

    $service->undo($owner, $transcription, $v1->revisionId);

    expect($service->redo($owner, $transcription)->revisionId)->toBe($v2->revisionId);
})->with('undo backends');
