<?php

use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;

function invocationSegment(int $index): TranslationSegmentData
{
    return new TranslationSegmentData(
        segmentIndex: $index,
        startSeconds: $index * 5.0,
        endSeconds: ($index + 1) * 5.0,
        text: "segment {$index}",
        sourceLanguage: LanguageIdentifier::English,
    );
}

it('creates an invocation with a server-generated request id', function () {
    $invocation = TranslationInvocation::create(
        transcriptionId: 42,
        targetLanguage: TranslationTarget::Malay,
        segments: [invocationSegment(0), invocationSegment(1)],
    );

    expect($invocation->transcriptionId)->toBe(42);
    expect($invocation->targetLanguage)->toBe(TranslationTarget::Malay);
    expect($invocation->segments)->toHaveCount(2);
    expect($invocation->requestId)->not->toBe('');
    expect($invocation->translationId)->toBeNull();
    expect($invocation->contractVersion)->toBe('1.0');
});

it('rejects a non-positive transcription id', function () {
    new TranslationInvocation(
        transcriptionId: 0,
        requestId: 'req',
        targetLanguage: TranslationTarget::English,
        segments: [],
    );
})->throws(InvalidArgumentException::class);

it('rejects an empty request id', function () {
    new TranslationInvocation(
        transcriptionId: 1,
        requestId: '',
        targetLanguage: TranslationTarget::English,
        segments: [],
    );
})->throws(InvalidArgumentException::class);
