<?php

use App\Editing\MachineSegmentSnapshot;
use App\Editing\RevisionFactory;
use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Transcription\LanguageIdentifier;
use Illuminate\Support\Str;

function editingSnapshot(int $index, float $start, float $end, string $text, LanguageIdentifier $language): MachineSegmentSnapshot
{
    return new MachineSegmentSnapshot(
        segmentIndex: $index,
        startSeconds: $start,
        endSeconds: $end,
        text: $text,
        language: $language,
    );
}

it('materializes the initial revision from machine segments in index order', function () {
    $factory = new RevisionFactory;
    $createdAt = new DateTimeImmutable('2026-09-23 12:00:00');

    // Deliberately unsorted input.
    $revision = $factory->materializeInitial(42, 7, [
        editingSnapshot(1, 5.0, 10.0, 'second', LanguageIdentifier::English),
        editingSnapshot(0, 0.0, 5.0, 'first', LanguageIdentifier::Malay),
    ], $createdAt);

    expect(Str::isUuid($revision->revisionId))->toBeTrue()
        ->and($revision->transcriptionId)->toBe(42)
        ->and($revision->version)->toBe(1)
        ->and($revision->parentRevisionId)->toBeNull()
        ->and($revision->createdBy)->toBe(7)
        ->and($revision->createdAt)->toBe($createdAt)
        ->and($revision->isInitial())->toBeTrue()
        ->and($revision->segmentCount())->toBe(2);

    $ordered = $revision->orderedSegments();

    expect($ordered[0]->position)->toBe(0)
        ->and($ordered[0]->text)->toBe('first')
        ->and($ordered[0]->language)->toBe(LanguageIdentifier::Malay)
        ->and($ordered[0]->identity->equals(RevisionSegmentIdentity::forMachineSegment(0)))->toBeTrue()
        ->and($ordered[1]->position)->toBe(1)
        ->and($ordered[1]->text)->toBe('second')
        ->and($ordered[1]->identity->equals(RevisionSegmentIdentity::forMachineSegment(1)))->toBeTrue();
});

it('materializes an empty revision when the machine source has no segments', function () {
    $revision = (new RevisionFactory)->materializeInitial(1, 1, []);

    expect($revision->isEmpty())->toBeTrue()
        ->and($revision->version)->toBe(1);
});

it('derives a new revision linked to its parent with an incremented version', function () {
    $factory = new RevisionFactory;
    $base = $factory->materializeInitial(42, 7, [
        editingSnapshot(0, 0.0, 5.0, 'first', LanguageIdentifier::Malay),
    ]);

    $derived = $factory->derive($base, 9, [
        new RevisionSegmentData(
            identity: RevisionSegmentIdentity::fromString('seg-new'),
            position: 0,
            startSeconds: 0.0,
            endSeconds: 5.0,
            text: 'edited',
            language: LanguageIdentifier::Malay,
        ),
    ]);

    expect($derived->revisionId)->not->toBe($base->revisionId)
        ->and($derived->version)->toBe(2)
        ->and($derived->parentRevisionId)->toBe($base->revisionId)
        ->and($derived->transcriptionId)->toBe(42)
        ->and($derived->createdBy)->toBe(9)
        ->and($derived->isInitial())->toBeFalse()
        ->and($derived->orderedSegments()[0]->text)->toBe('edited');
});
