<?php

use App\Editing\RevisionSegmentIdentity;

it('requires a non-empty identity key', function () {
    expect(fn () => RevisionSegmentIdentity::fromString(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => RevisionSegmentIdentity::fromString('   '))->toThrow(InvalidArgumentException::class);
});

it('exposes equality and string form', function () {
    $a = RevisionSegmentIdentity::fromString('seg-a');
    $b = RevisionSegmentIdentity::fromString('seg-a');
    $c = RevisionSegmentIdentity::fromString('seg-b');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and((string) $a)->toBe('seg-a')
        ->and($a->key())->toBe('seg-a');
});

it('derives a deterministic identity for a machine segment', function () {
    expect(RevisionSegmentIdentity::forMachineSegment(3)->key())->toBe('machine:3')
        ->and(
            RevisionSegmentIdentity::forMachineSegment(3)->equals(
                RevisionSegmentIdentity::forMachineSegment(3),
            )
        )->toBeTrue()
        ->and(fn () => RevisionSegmentIdentity::forMachineSegment(-1))->toThrow(InvalidArgumentException::class);
});
