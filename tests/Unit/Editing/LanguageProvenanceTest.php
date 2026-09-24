<?php

use App\Editing\LanguageProvenance;
use App\Transcription\LanguageIdentifier;

it('round-trips ordered contributor markers', function () {
    $provenance = LanguageProvenance::fromIdentifiers([
        LanguageIdentifier::Malay,
        LanguageIdentifier::English,
        LanguageIdentifier::Tamil,
    ]);

    expect($provenance->toArray())->toBe(['ms', 'en', 'ta'])
        ->and($provenance->fromArray(['ms', 'en', 'ta'])->equals($provenance))->toBeTrue()
        ->and($provenance->contributorCount())->toBe(3);
});

it('detects mixed contributor markers', function () {
    expect(LanguageProvenance::fromArray(['en', 'en'])->isMixed())->toBeFalse()
        ->and(LanguageProvenance::fromArray(['en', 'ms'])->isMixed())->toBeTrue();
});

it('rejects an empty provenance and unknown markers', function () {
    expect(fn () => LanguageProvenance::fromArray([]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => LanguageProvenance::fromArray(['en', 'klingon']))
        ->toThrow(InvalidArgumentException::class);
});
