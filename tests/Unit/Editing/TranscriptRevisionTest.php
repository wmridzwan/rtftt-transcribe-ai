<?php

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Editing\TranscriptRevision;
use Tests\Support\EditingFixtures;

/**
 * @param  list<RevisionSegmentData>  $segments
 */
function editingRevision(array $segments = [], ?string $parent = null, int $version = 1): TranscriptRevision
{
    return new TranscriptRevision(
        revisionId: 'rev-1',
        transcriptionId: 10,
        version: $version,
        parentRevisionId: $parent,
        createdBy: 7,
        createdAt: new DateTimeImmutable('2026-09-23 00:00:00'),
        segments: $segments,
    );
}

it('constructs an initial revision and exposes ordered segments', function () {
    $revision = editingRevision(EditingFixtures::sequence());

    expect($revision->segmentCount())->toBe(2)
        ->and($revision->isInitial())->toBeTrue()
        ->and($revision->isEmpty())->toBeFalse()
        ->and($revision->orderedSegments()[0]->position)->toBe(0)
        ->and($revision->segmentByPosition(1)?->text)->toBe('second')
        ->and($revision->segmentByPosition(9))->toBeNull()
        ->and($revision->segmentByIdentity(RevisionSegmentIdentity::fromString('seg-0'))?->position)->toBe(0)
        ->and($revision->segmentByIdentity(RevisionSegmentIdentity::fromString('missing')))->toBeNull();
});

it('allows an empty revision and rejects invalid identity fields', function () {
    expect(editingRevision([])->isEmpty())->toBeTrue()
        ->and(fn () => new TranscriptRevision(
            revisionId: '',
            transcriptionId: 1,
            version: 1,
            parentRevisionId: null,
            createdBy: 1,
            createdAt: new DateTimeImmutable,
            segments: [],
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TranscriptRevision(
            revisionId: 'r',
            transcriptionId: 0,
            version: 1,
            parentRevisionId: null,
            createdBy: 1,
            createdAt: new DateTimeImmutable,
            segments: [],
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TranscriptRevision(
            revisionId: 'r',
            transcriptionId: 1,
            version: 0,
            parentRevisionId: null,
            createdBy: 1,
            createdAt: new DateTimeImmutable,
            segments: [],
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TranscriptRevision(
            revisionId: 'r',
            transcriptionId: 1,
            version: 1,
            parentRevisionId: '',
            createdBy: 1,
            createdAt: new DateTimeImmutable,
            segments: [],
        ))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TranscriptRevision(
            revisionId: 'r',
            transcriptionId: 1,
            version: 1,
            parentRevisionId: null,
            createdBy: 0,
            createdAt: new DateTimeImmutable,
            segments: [],
        ))->toThrow(InvalidArgumentException::class);
});

it('enforces sequence invariants at construction', function () {
    $duplicateIdentity = [
        EditingFixtures::segment(0, 0.0, 1.0, 'a', identity: 'same'),
        EditingFixtures::segment(1, 1.0, 2.0, 'b', identity: 'same'),
    ];

    expect(fn () => editingRevision($duplicateIdentity))->toThrow(InvalidArgumentException::class);
});

it('is not initial when it has a parent or a higher version', function () {
    expect(editingRevision([], 'rev-parent', 2)->isInitial())->toBeFalse()
        ->and(editingRevision([], null, 2)->isInitial())->toBeFalse();
});
