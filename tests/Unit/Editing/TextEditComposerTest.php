<?php

use App\Editing\RevisionSegmentData;
use App\Editing\TextEditComposer;
use App\Transcription\LanguageIdentifier;
use Tests\Support\EditingFixtures;

beforeEach(function (): void {
    $this->composer = new TextEditComposer;
});

it('replaces only text and preserves identity, position, timing, and language', function () {
    $base = EditingFixtures::sequence();

    $composed = $this->composer->compose($base, [0 => 'changed first', 1 => '']);

    expect($composed)->toHaveCount(2)
        ->and($composed[0])->toBeInstanceOf(RevisionSegmentData::class)
        ->and($composed[0]->text)->toBe('changed first')
        ->and($composed[0]->identity->key())->toBe($base[0]->identity->key())
        ->and($composed[0]->position)->toBe(0)
        ->and($composed[0]->startSeconds)->toBe(0.0)
        ->and($composed[0]->endSeconds)->toBe(5.0)
        ->and($composed[0]->language)->toBe(LanguageIdentifier::Malay)
        ->and($composed[1]->text)->toBe('')
        ->and($composed[1]->identity->key())->toBe($base[1]->identity->key())
        ->and($composed[1]->language)->toBe(LanguageIdentifier::English);
});

it('accepts an empty string as a legal distinct text value', function () {
    $composed = $this->composer->compose(EditingFixtures::sequence(), [0 => '', 1 => '']);

    expect($composed[0]->text)->toBe('')
        ->and($composed[1]->text)->toBe('');
});

it('orders the result by position ascending', function () {
    $base = [
        EditingFixtures::segment(2, 10.0, 20.0, 'c', identity: 'c'),
        EditingFixtures::segment(0, 0.0, 5.0, 'a', identity: 'a'),
        EditingFixtures::segment(1, 5.0, 10.0, 'b', identity: 'b'),
    ];

    $composed = $this->composer->compose($base, [2 => 'C', 0 => 'A', 1 => 'B']);

    expect(array_map(fn (RevisionSegmentData $s): int => $s->position, $composed))->toBe([0, 1, 2])
        ->and(array_map(fn (RevisionSegmentData $s): string => $s->text, $composed))->toBe(['A', 'B', 'C']);
});

it('rejects a submission that omits a segment', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [0 => 'only one']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a submission with an unknown position', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [0 => 'a', 1 => 'b', 9 => 'extra']))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a non-string text value', function () {
    expect(fn () => $this->composer->compose(EditingFixtures::sequence(), [0 => 123, 1 => 'b']))
        ->toThrow(InvalidArgumentException::class);
});
