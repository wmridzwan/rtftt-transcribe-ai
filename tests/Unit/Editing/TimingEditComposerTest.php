<?php

use App\Editing\RevisionSegmentData;
use App\Editing\TimingEditComposer;
use App\Transcription\LanguageIdentifier;
use Tests\Support\EditingFixtures;

beforeEach(function (): void {
    $this->composer = new TimingEditComposer;
});

it('replaces only timing and preserves identity, position, text, and language', function () {
    $base = EditingFixtures::sequence();

    $composed = $this->composer->compose($base, [
        0 => ['start' => 1.5, 'end' => 4.25],
        1 => ['start' => 6.0, 'end' => 9.0],
    ]);

    expect($composed)->toHaveCount(2)
        ->and($composed[0])->toBeInstanceOf(RevisionSegmentData::class)
        ->and($composed[0]->startSeconds)->toBe(1.5)
        ->and($composed[0]->endSeconds)->toBe(4.25)
        ->and($composed[0]->identity->key())->toBe($base[0]->identity->key())
        ->and($composed[0]->position)->toBe(0)
        ->and($composed[0]->text)->toBe('first')
        ->and($composed[0]->language)->toBe(LanguageIdentifier::Malay)
        ->and($composed[1]->startSeconds)->toBe(6.0)
        ->and($composed[1]->endSeconds)->toBe(9.0)
        ->and($composed[1]->identity->key())->toBe($base[1]->identity->key())
        ->and($composed[1]->text)->toBe('second')
        ->and($composed[1]->language)->toBe(LanguageIdentifier::English);
});

it('orders the result by position ascending regardless of submitted order', function () {
    $base = [
        EditingFixtures::segment(2, 10.0, 20.0, 'c', identity: 'c'),
        EditingFixtures::segment(0, 0.0, 5.0, 'a', identity: 'a'),
        EditingFixtures::segment(1, 5.0, 10.0, 'b', identity: 'b'),
    ];

    $composed = $this->composer->compose($base, [
        2 => ['start' => 21.0, 'end' => 22.0],
        0 => ['start' => 1.0, 'end' => 2.0],
        1 => ['start' => 11.0, 'end' => 12.0],
    ]);

    expect(array_map(fn (RevisionSegmentData $s): int => $s->position, $composed))->toBe([0, 1, 2])
        ->and(array_map(fn (RevisionSegmentData $s): float => $s->startSeconds, $composed))->toBe([1.0, 11.0, 21.0])
        ->and(array_map(fn (RevisionSegmentData $s): string => $s->text, $composed))->toBe(['a', 'b', 'c']);
});

it('accepts overlap, nested overlap, equal starts, and equal ends', function () {
    $composed = $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 2.0, 'end' => 6.0],
        1 => ['start' => 2.0, 'end' => 6.0],
    ]);

    expect($composed[0]->startSeconds)->toBe(2.0)
        ->and($composed[0]->endSeconds)->toBe(6.0)
        ->and($composed[1]->startSeconds)->toBe(2.0)
        ->and($composed[1]->endSeconds)->toBe(6.0);
});

it('accepts a zero-length segment', function () {
    $composed = $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 5.0, 'end' => 5.0],
        1 => ['start' => 5.0, 'end' => 7.0],
    ]);

    expect($composed[0]->isZeroLength())->toBeTrue()
        ->and($composed[1]->isZeroLength())->toBeFalse();
});

it('accepts out-of-time-order positions (no cross-segment monotonicity)', function () {
    $composed = $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 50.0, 'end' => 60.0],
        1 => ['start' => 0.0, 'end' => 1.0],
    ]);

    expect($composed[0]->startSeconds)->toBe(50.0)
        ->and($composed[1]->startSeconds)->toBe(0.0);
});

it('accepts extending a segment past a neighboring segment', function () {
    $composed = $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 4.0, 'end' => 20.0],
        1 => ['start' => 5.0, 'end' => 10.0],
    ]);

    expect($composed[0]->endSeconds)->toBe(20.0);
});

it('normalizes numeric strings to canonical millisecond values', function () {
    $composed = $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => '1.500', 'end' => '4.250'],
        1 => ['start' => '4.250', 'end' => 9],
    ]);

    expect($composed[0]->startSeconds)->toBe(1.5)
        ->and($composed[0]->endSeconds)->toBe(4.25)
        ->and($composed[1]->startSeconds)->toBe(4.25)
        ->and($composed[1]->endSeconds)->toBe(9.0);
});

it('rejects a submission that omits a segment', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 0.0, 'end' => 1.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a submission with an unknown position', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 0.0, 'end' => 1.0],
        1 => ['start' => 1.0, 'end' => 2.0],
        9 => ['start' => 0.0, 'end' => 1.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a non-array timing entry', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => 1.0,
        1 => ['start' => 1.0, 'end' => 2.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects a timing entry missing start or end', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 0.0],
        1 => ['start' => 1.0, 'end' => 2.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects non-numeric timing values', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 'abc', 'end' => '1.0'],
        1 => ['start' => 1.0, 'end' => 2.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects NaN and infinite timing values', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => INF, 'end' => 1.0],
        1 => ['start' => 1.0, 'end' => 2.0],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 0.0, 'end' => NAN],
        1 => ['start' => 1.0, 'end' => 2.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects negative timestamps', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => -0.001, 'end' => 1.0],
        1 => ['start' => 1.0, 'end' => 2.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects start greater than end', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 5.0, 'end' => 1.0],
        1 => ['start' => 1.0, 'end' => 2.0],
    ]))->toThrow(InvalidArgumentException::class);
});

it('rejects sub-millisecond precision without silent rounding', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [
        0 => ['start' => 1.2345, 'end' => 2.0],
        1 => ['start' => 2.0, 'end' => 3.0],
    ]))->toThrow(InvalidArgumentException::class);
});
