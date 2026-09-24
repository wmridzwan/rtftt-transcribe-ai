<?php

use App\Editing\SplitComposer;
use App\Transcription\LanguageIdentifier;
use Tests\Support\EditingFixtures;

beforeEach(function (): void {
    $this->composer = new SplitComposer;
});

it('splits a segment at a strict interior boundary into two new-identity children', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 10.0, 'hello world', LanguageIdentifier::Malay),
        EditingFixtures::segment(1, 10.0, 20.0, 'tail', LanguageIdentifier::English),
    ];

    $result = $this->composer->compose($base, 'seg-0', 4.0, 5);

    expect($result)->toHaveCount(3)
        ->and($result[0]->position)->toBe(0)
        ->and($result[0]->startSeconds)->toBe(0.0)
        ->and($result[0]->endSeconds)->toBe(4.0)
        ->and($result[0]->text)->toBe('hello')
        ->and($result[0]->language)->toBe(LanguageIdentifier::Malay)
        ->and($result[1]->position)->toBe(1)
        ->and($result[1]->startSeconds)->toBe(4.0)
        ->and($result[1]->endSeconds)->toBe(10.0)
        ->and($result[1]->text)->toBe(' world')
        ->and($result[1]->language)->toBe(LanguageIdentifier::Malay)
        ->and($result[2]->position)->toBe(2)
        ->and($result[2]->identity->key())->toBe('seg-1');

    expect($result[0]->identity->key())->not->toBe('machine:0')
        ->and($result[1]->identity->key())->not->toBe('machine:0')
        ->and($result[0]->identity->key())->not->toBe($result[1]->identity->key())
        ->and($result[0]->identity->key())->toStartWith('struct:')
        ->and($result[1]->identity->key())->toStartWith('struct:');
});

it('distributes multi-byte text on code-point boundaries and clears merge provenance', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 6.0, '你好世界', LanguageIdentifier::Chinese, identity: 'seg-0'),
    ];

    $result = $this->composer->compose($base, 'seg-0', 3.0, 2);

    expect($result[0]->text)->toBe('你好')
        ->and($result[1]->text)->toBe('世界')
        ->and($result[0]->languageProvenance)->toBeNull()
        ->and($result[1]->languageProvenance)->toBeNull();
});

it('rejects a split at the beginning', function () {
    $base = [EditingFixtures::segment(0, 0.0, 10.0, 'hello world')];

    expect(fn () => $this->composer->compose($base, 'seg-0', 0.0, 5))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->composer->compose($base, 'seg-0', 4.0, 0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a split at the end', function () {
    $base = [EditingFixtures::segment(0, 0.0, 10.0, 'hello world')];

    expect(fn () => $this->composer->compose($base, 'seg-0', 10.0, 5))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->composer->compose($base, 'seg-0', 4.0, 11))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects splitting a zero-length or empty-text segment (degenerate)', function () {
    $zeroLength = [EditingFixtures::segment(0, 5.0, 5.0, 'text')];
    $emptyText = [EditingFixtures::segment(0, 0.0, 5.0, '')];

    expect(fn () => $this->composer->compose($zeroLength, 'seg-0', 5.0, 1))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => $this->composer->compose($emptyText, 'seg-0', 2.0, 0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an unknown segment, a non-integer offset, and sub-millisecond boundaries', function () {
    $base = [EditingFixtures::segment(0, 0.0, 10.0, 'hello world')];

    expect(fn () => $this->composer->compose($base, 'missing', 4.0, 5))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->composer->compose($base, 'seg-0', 4.0, 1.5))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->composer->compose($base, 'seg-0', 4.0005, 5))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->composer->compose($base, 'seg-0', 'not-a-number', 5))
        ->toThrow(InvalidArgumentException::class);
});

it('produces contiguous positions when splitting a middle segment', function () {
    $base = [
        EditingFixtures::segment(0, 0.0, 2.0, 'aa'),
        EditingFixtures::segment(1, 2.0, 8.0, 'bbbbbb'),
        EditingFixtures::segment(2, 8.0, 10.0, 'cc'),
    ];

    $result = $this->composer->compose($base, 'seg-1', 5.0, 3);

    expect(array_map(fn ($s) => $s->position, $result))->toBe([0, 1, 2, 3])
        ->and(array_map(fn ($s) => $s->text, $result))->toBe(['aa', 'bbb', 'bbb', 'cc']);
});
