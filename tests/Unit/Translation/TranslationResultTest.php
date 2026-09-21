<?php

use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationResult;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;

function translationSegment(int $index = 0, float $start = 0.0, float $end = 5.0, string $text = 'Hai', LanguageIdentifier $source = LanguageIdentifier::English): TranslationSegmentData
{
    return new TranslationSegmentData(
        segmentIndex: $index,
        startSeconds: $start,
        endSeconds: $end,
        text: $text,
        sourceLanguage: $source,
    );
}

it('creates a valid translation segment preserving alignment', function () {
    $segment = translationSegment(3, 12.5, 15.999, '你好世界', LanguageIdentifier::Chinese);

    expect($segment->segmentIndex)->toBe(3);
    expect($segment->startSeconds)->toBe(12.5);
    expect($segment->endSeconds)->toBe(15.999);
    expect($segment->text)->toBe('你好世界');
    expect($segment->sourceLanguage)->toBe(LanguageIdentifier::Chinese);
});

it('rejects invalid translation segment alignment', function () {
    expect(fn () => translationSegment(-1))->toThrow(InvalidArgumentException::class, 'Segment index must be non-negative.');
    expect(fn () => translationSegment(0, -1.0))->toThrow(InvalidArgumentException::class, 'Start seconds must be non-negative.');
    expect(fn () => translationSegment(0, 5.0, 3.0))->toThrow(InvalidArgumentException::class, 'End seconds must be greater than or equal to start seconds.');
});

it('creates a valid segment-aligned translation result', function () {
    $result = new TranslationResult(
        targetLanguage: TranslationTarget::Malay,
        fullText: 'Hai dunia',
        segments: [translationSegment(0), translationSegment(1, 5.0, 9.0)],
        provider: 'self-hosted',
        model: 'translation-test',
    );

    expect($result->targetLanguage)->toBe(TranslationTarget::Malay);
    expect($result->segments)->toHaveCount(2);
});

it('rejects duplicate segment indices in a result', function () {
    new TranslationResult(
        targetLanguage: TranslationTarget::English,
        fullText: 'x',
        segments: [translationSegment(0), translationSegment(0)],
        provider: 'self-hosted',
        model: 'm',
    );
})->throws(InvalidArgumentException::class, 'Translation result segment indices must be unique.');

it('rejects empty provider or model identity', function () {
    expect(fn () => new TranslationResult(TranslationTarget::English, 'x', [], '', 'm'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => new TranslationResult(TranslationTarget::English, 'x', [], 'p', ''))
        ->toThrow(InvalidArgumentException::class);
});
