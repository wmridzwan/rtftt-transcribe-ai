<?php

use App\Editing\RevisionConflictException;
use App\Editing\RevisionFactory;
use App\Editing\TranscriptRevision;
use Tests\Support\InMemoryRevisionRepository;

function editingRepository(): InMemoryRevisionRepository
{
    return new InMemoryRevisionRepository;
}

it('appends revisions additively and tracks the active revision via compare-and-set', function () {
    $repository = editingRepository();
    $factory = new RevisionFactory;

    $initial = $factory->materializeInitial(10, 7, []);
    $repository->append($initial, null);

    expect($repository->activeFor(10)?->revisionId)->toBe($initial->revisionId)
        ->and($repository->find($initial->revisionId))->toBe($initial)
        ->and($repository->find('missing'))->toBeNull();

    $derived = $factory->derive($initial, 7, []);
    $repository->append($derived, $initial->revisionId);

    expect($repository->activeFor(10)?->revisionId)->toBe($derived->revisionId)
        ->and($repository->historyFor(10))->toHaveCount(2)
        ->and($repository->historyFor(10)[0]->version)->toBe(1)
        ->and($repository->historyFor(10)[1]->version)->toBe(2);
});

it('rejects a stale append without mutating history', function () {
    $repository = editingRepository();
    $factory = new RevisionFactory;

    $initial = $factory->materializeInitial(10, 7, []);
    $repository->append($initial, null);

    $derived = $factory->derive($initial, 7, []);

    expect(fn () => $repository->append($derived, 'some-other-revision'))
        ->toThrow(RevisionConflictException::class);

    expect($repository->historyFor(10))->toHaveCount(1)
        ->and($repository->activeFor(10)?->revisionId)->toBe($initial->revisionId);
});

it('activates a prior revision only against the expected current revision', function () {
    $repository = editingRepository();
    $factory = new RevisionFactory;

    $initial = $factory->materializeInitial(10, 7, []);
    $repository->append($initial, null);
    $derived = $factory->derive($initial, 7, []);
    $repository->append($derived, $initial->revisionId);

    // Undo-style activation of the earlier revision (append-only history kept).
    $repository->activate(10, $initial->revisionId, $derived->revisionId);

    expect($repository->activeFor(10)?->revisionId)->toBe($initial->revisionId)
        ->and($repository->historyFor(10))->toHaveCount(2);

    expect(fn () => $repository->activate(10, $derived->revisionId, 'stale-token'))
        ->toThrow(RevisionConflictException::class);
});

it('rejects activation of an unknown revision', function () {
    $repository = editingRepository();

    expect(fn () => $repository->activate(10, 'unknown', null))->toThrow(InvalidArgumentException::class);
});

it('keeps history independent per transcription', function () {
    $repository = editingRepository();
    $factory = new RevisionFactory;

    $a = $factory->materializeInitial(1, 7, []);
    $b = $factory->materializeInitial(2, 7, []);
    $repository->append($a, null);
    $repository->append($b, null);

    expect($repository->historyFor(1))->toHaveCount(1)
        ->and($repository->historyFor(2))->toHaveCount(1)
        ->and($repository->activeFor(1)?->transcriptionId)->toBe(1)
        ->and($repository->activeFor(2)?->transcriptionId)->toBe(2)
        ->and($repository->activeFor(3))->toBeNull();
});

it('is satisfied by the TranscriptRevision value object contract', function () {
    $revision = (new RevisionFactory)->materializeInitial(10, 7, []);

    expect($revision)->toBeInstanceOf(TranscriptRevision::class);
});
