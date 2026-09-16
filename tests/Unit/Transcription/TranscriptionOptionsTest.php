<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\TranscriptionOptions;

it('defaults to null requested language (auto-detect)', function () {
    $options = new TranscriptionOptions;

    expect($options->requestedLanguage)->toBeNull();
});

it('accepts explicit language hint', function () {
    $options = new TranscriptionOptions(
        requestedLanguage: LanguageIdentifier::Malay,
    );

    expect($options->requestedLanguage)->toBe(LanguageIdentifier::Malay);
});

it('accepts all known languages', function () {
    foreach (LanguageIdentifier::cases() as $lang) {
        $options = new TranscriptionOptions(requestedLanguage: $lang);
        expect($options->requestedLanguage)->toBe($lang);
    }
});
