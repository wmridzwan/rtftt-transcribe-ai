<?php

use App\Editing\MergeComposer;
use App\Transcription\LanguageIdentifier;
use Tests\Support\EditingFixtures;

beforeEach(function (): void {
    $this->composer = new MergeComposer;
});

it('merges adjacent segments with a single-space join and earliest-to-latest timing', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 4.0, 'first', LanguageIdentifier::English),
        EditingFixtures::segment(1, 4.0, 9.0, 'second', LanguageIdentifier::English),
        EditingFixtures::segment(2, 9.0, 12.0, 'third', LanguageIdentifier::English),
    ];

    $result = $this->composer->compose($base, ['seg-0', 'seg-1']);

    expect($result)->toHaveCount(2)
        ->and($result[0]->position)->toBe(0)
        ->and($result[0]->text)->toBe('first second')
        ->and($result[0]->startSeconds)->toBe(0.0)
        ->and($result[0]->endSeconds)->toBe(9.0)
        ->and($result[0]->language)->toBe(LanguageIdentifier::English)
        ->and($result[0]->languageProvenance)->toBeNull()
        ->and($result[0]->identity->key())->toStartWith('struct:')
        ->and($result[1]->position)->toBe(1)
        ->and($result[1]->identity->key())->toBe('seg-2');
});

it('retains the language of same-language contributors', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 2.0, 'a', LanguageIdentifier::Malay),
        EditingFixtures::segment(1, 2.0, 4.0, 'b', LanguageIdentifier::Malay),
    ];

    $result = $this->composer->compose($base, ['seg-0', 'seg-1']);

    expect($result[0]->language)->toBe(LanguageIdentifier::Malay)
        ->and($result[0]->languageProvenance)->toBeNull();
});

it('marks mixed-language merges as und with explicit ordered provenance, never first-language', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 2.0, 'a', LanguageIdentifier::Malay),
        EditingFixtures::segment(1, 2.0, 4.0, 'b', LanguageIdentifier::English),
    ];

    // Contributor identities supplied out of order must still be processed in
    // position order.
    $result = $this->composer->compose($base, ['seg-1', 'seg-0']);

    expect($result[0]->language)->toBe(LanguageIdentifier::Undetermined)
        ->and($result[0]->languageProvenance)->not->toBeNull()
        ->and($result[0]->languageProvenance?->toArray())->toBe(['ms', 'en'])
        ->and($result[0]->text)->toBe('a b');
});

it('joins contributor text verbatim with one plain space, never trimming', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 2.0, 'Hello '),
        EditingFixtures::segment(1, 2.0, 4.0, ' world'),
    ];

    $result = $this->composer->compose($base, ['seg-0', 'seg-1']);

    expect($result[0]->text)->toBe('Hello   world');
});

it('uses earliest-position start and latest-position end', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 5.0, 'a'),
        EditingFixtures::segment(1, 5.0, 12.0, 'b'),
        EditingFixtures::segment(2, 12.0, 15.0, 'c'),
    ];

    $result = $this->composer->compose($base, ['seg-0', 'seg-1']);

    expect($result[0]->startSeconds)->toBe(0.0)
        ->and((float) $result[0]->endSeconds)->toBe(12.0);
});

it('rejects a merge whose resulting timing would violate the frozen invariants', function () {
    // Earliest-position start (10.0) would exceed latest-position end (3.0);
    // the merged segment must itself be a valid [start, end] interval.
    $base = [
        EditingFixtures::segment(0, 10.0, 12.0, 'a'),
        EditingFixtures::segment(1, 0.0, 3.0, 'b'),
    ];

    expect(fn () => $this->composer->compose($base, ['seg-0', 'seg-1']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects non-adjacent, duplicated, unknown, and too-few contributors', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 2.0, 'a'),
        EditingFixtures::segment(1, 2.0, 4.0, 'b'),
        EditingFixtures::segment(2, 4.0, 6.0, 'c'),
    ];

    expect(fn () => $this->composer->compose($base, ['seg-0']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->composer->compose($base, ['seg-0', 'seg-2']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->composer->compose($base, ['seg-0', 'seg-0']))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->composer->compose($base, ['seg-0', 'missing']))
        ->toThrow(InvalidArgumentException::class);
});
