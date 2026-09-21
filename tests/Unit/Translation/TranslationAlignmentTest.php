<?php

use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationAlignment;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;

it('treats a known source equal to the target as passthrough', function () {
    expect(TranslationAlignment::isPassthrough(LanguageIdentifier::Malay, TranslationTarget::Malay))->toBeTrue();
    expect(TranslationAlignment::isPassthrough(LanguageIdentifier::English, TranslationTarget::English))->toBeTrue();
});

it('never treats und or a differing source as passthrough', function () {
    expect(TranslationAlignment::isPassthrough(LanguageIdentifier::Undetermined, TranslationTarget::English))->toBeFalse();
    expect(TranslationAlignment::isPassthrough(LanguageIdentifier::Chinese, TranslationTarget::English))->toBeFalse();
});

it('builds a passthrough segment preserving alignment verbatim', function () {
    $source = new TranslationSegmentData(
        segmentIndex: 4,
        startSeconds: 10.0,
        endSeconds: 13.25,
        text: 'Selamat datang',
        sourceLanguage: LanguageIdentifier::Malay,
    );

    $result = TranslationAlignment::passthrough($source, TranslationTarget::Malay);

    expect($result->segmentIndex)->toBe(4);
    expect($result->startSeconds)->toBe(10.0);
    expect($result->endSeconds)->toBe(13.25);
    expect($result->text)->toBe('Selamat datang');
    expect($result->sourceLanguage)->toBe(LanguageIdentifier::Malay);
});

it('rejects a passthrough that is not actually the target language', function () {
    $source = new TranslationSegmentData(0, 0.0, 1.0, 'hello', LanguageIdentifier::English);

    expect(fn () => TranslationAlignment::passthrough($source, TranslationTarget::Malay))
        ->toThrow(InvalidArgumentException::class);
});
