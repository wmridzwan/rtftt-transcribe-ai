<?php

use App\Transcription\LanguageIdentifier;

it('resolves known BCP 47 tags', function () {
    expect(LanguageIdentifier::fromBcp47('ms'))->toBe(LanguageIdentifier::Malay);
    expect(LanguageIdentifier::fromBcp47('en'))->toBe(LanguageIdentifier::English);
    expect(LanguageIdentifier::fromBcp47('zh'))->toBe(LanguageIdentifier::Chinese);
    expect(LanguageIdentifier::fromBcp47('ta'))->toBe(LanguageIdentifier::Tamil);
    expect(LanguageIdentifier::fromBcp47('und'))->toBe(LanguageIdentifier::Undetermined);
});

it('resolves prefixed BCP 47 tags', function () {
    expect(LanguageIdentifier::fromBcp47('en-US'))->toBe(LanguageIdentifier::English);
    expect(LanguageIdentifier::fromBcp47('ms-MY'))->toBe(LanguageIdentifier::Malay);
    expect(LanguageIdentifier::fromBcp47('zh-CN'))->toBe(LanguageIdentifier::Chinese);
    expect(LanguageIdentifier::fromBcp47('ta-LK'))->toBe(LanguageIdentifier::Tamil);
});

it('returns Undetermined for unknown tags', function () {
    expect(LanguageIdentifier::fromBcp47('fr'))->toBe(LanguageIdentifier::Undetermined);
    expect(LanguageIdentifier::fromBcp47('ja'))->toBe(LanguageIdentifier::Undetermined);
    expect(LanguageIdentifier::fromBcp47('de-DE'))->toBe(LanguageIdentifier::Undetermined);
});

it('is case insensitive', function () {
    expect(LanguageIdentifier::fromBcp47('EN'))->toBe(LanguageIdentifier::English);
    expect(LanguageIdentifier::fromBcp47('Ms'))->toBe(LanguageIdentifier::Malay);
    expect(LanguageIdentifier::fromBcp47('ZH-CN'))->toBe(LanguageIdentifier::Chinese);
});
