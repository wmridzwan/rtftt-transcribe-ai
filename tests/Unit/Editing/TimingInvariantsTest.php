<?php

use App\Editing\TimingInvariants;
use Tests\Support\EditingFixtures;

it('accepts finite non-negative timings with start before end', function () {
    TimingInvariants::assertValidTiming(0.0, 5.0);
    TimingInvariants::assertValidTiming(1.234, 1.234);

    expect(TimingInvariants::isValidTiming(0.0, 5.0))->toBeTrue()
        ->and(TimingInvariants::isValidTiming(3.0, 2.999))->toBeFalse()
        ->and(TimingInvariants::isValidTiming(-0.001, 5.0))->toBeFalse()
        ->and(TimingInvariants::isValidTiming(NAN, 5.0))->toBeFalse()
        ->and(TimingInvariants::isValidTiming(0.0, INF))->toBeFalse();
});

it('rejects start after end and negative or non-finite values', function () {
    expect(fn () => TimingInvariants::assertValidTiming(5.0, 4.999))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimingInvariants::assertValidTiming(-1.0, 1.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimingInvariants::assertValidTiming(0.0, NAN))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimingInvariants::assertValidTiming(0.0, INF))->toThrow(InvalidArgumentException::class);
});

it('treats equal start and end as legal zero-length', function () {
    expect(TimingInvariants::isZeroLength(2.0, 2.0))->toBeTrue()
        ->and(TimingInvariants::isZeroLength(2.0, 2.001))->toBeFalse();
});

it('treats overlaps as legal and resolves them by lowest position', function () {
    $segments = [
        EditingFixtures::segment(0, 0.5, 6.0, 'overlap zero'),
        EditingFixtures::segment(1, 3.0, 9.0, 'overlap one'),
        EditingFixtures::segment(2, 9.0, 12.0, 'later'),
    ];

    expect(TimingInvariants::overlaps(0.5, 6.0, 3.0, 9.0))->toBeTrue()
        ->and(TimingInvariants::overlaps(0.0, 1.0, 1.0, 2.0))->toBeFalse()
        ->and(TimingInvariants::overlaps(0.0, 0.0, 0.0, 1.0))->toBeFalse();

    // Position 0 wins inside the overlapping window, not the array's last item.
    expect(TimingInvariants::activeAt($segments, 4.0)?->position)->toBe(0);
});

it('never activates a zero-length segment', function () {
    $segments = [
        EditingFixtures::segment(0, 0.0, 2.0, 'first'),
        EditingFixtures::segment(1, 2.0, 2.0, 'zero length'),
    ];

    expect(TimingInvariants::activeAt($segments, 2.0)?->position)->toBeNull();
});

it('rejects duplicate identities, duplicate positions, and non-contiguous positions', function () {
    $duplicateIdentity = [
        EditingFixtures::segment(0, 0.0, 1.0, 'a', identity: 'dup'),
        EditingFixtures::segment(1, 1.0, 2.0, 'b', identity: 'dup'),
    ];

    $duplicatePosition = [
        EditingFixtures::segment(0, 0.0, 1.0, 'a', identity: 'a'),
        EditingFixtures::segment(0, 1.0, 2.0, 'b', identity: 'b'),
    ];

    $gapPosition = [
        EditingFixtures::segment(0, 0.0, 1.0, 'a', identity: 'a'),
        EditingFixtures::segment(2, 1.0, 2.0, 'b', identity: 'b'),
    ];

    expect(fn () => TimingInvariants::assertValidSequence($duplicateIdentity))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimingInvariants::assertValidSequence($duplicatePosition))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TimingInvariants::assertValidSequence($gapPosition))->toThrow(InvalidArgumentException::class);

    TimingInvariants::assertValidSequence(EditingFixtures::sequence());
    TimingInvariants::assertValidSequence([]);
});

it('does not require cross-segment timestamp monotonicity', function () {
    // Overlapping and out-of-order timings are legal: ordering is by position.
    $segments = [
        EditingFixtures::segment(0, 10.0, 12.0, 'later start, first position'),
        EditingFixtures::segment(1, 1.0, 3.0, 'earlier start, second position'),
    ];

    TimingInvariants::assertValidSequence($segments);

    expect($segments[0]->position)->toBe(0)
        ->and($segments[1]->position)->toBe(1);
});
