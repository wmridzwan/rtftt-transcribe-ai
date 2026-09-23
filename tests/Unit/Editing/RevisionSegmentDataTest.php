<?php

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Transcription\LanguageIdentifier;
use Tests\Support\EditingFixtures;

it('constructs a valid revision segment', function () {
    $segment = EditingFixtures::segment(0, 0.0, 5.0, 'hello', LanguageIdentifier::Chinese);

    expect($segment->position)->toBe(0)
        ->and($segment->startSeconds)->toBe(0.0)
        ->and($segment->endSeconds)->toBe(5.0)
        ->and($segment->text)->toBe('hello')
        ->and($segment->language)->toBe(LanguageIdentifier::Chinese)
        ->and($segment->identity->key())->toBe('seg-0');
});

it('rejects a negative position', function () {
    expect(fn () => new RevisionSegmentData(
        identity: RevisionSegmentIdentity::fromString('x'),
        position: -1,
        startSeconds: 0.0,
        endSeconds: 1.0,
        text: 'x',
        language: LanguageIdentifier::English,
    ))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid timing', function () {
    expect(fn () => EditingFixtures::segment(0, 5.0, 4.0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => EditingFixtures::segment(0, -1.0, 1.0))->toThrow(InvalidArgumentException::class);
});

it('reports zero-length and equality helpers', function () {
    $zero = EditingFixtures::segment(0, 2.0, 2.0, 'same', identity: 'z');
    $same = EditingFixtures::segment(1, 2.0, 2.0, 'same', identity: 's');
    $different = EditingFixtures::segment(2, 2.0, 3.0, 'other', identity: 'd');

    expect($zero->isZeroLength())->toBeTrue()
        ->and($different->isZeroLength())->toBeFalse()
        ->and($zero->hasSameTiming($same))->toBeTrue()
        ->and($zero->hasSameTiming($different))->toBeFalse()
        ->and($zero->hasSameText($same))->toBeTrue()
        ->and($zero->hasSameText($different))->toBeFalse();
});
