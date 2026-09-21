<?php

use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationRequest;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;

it('builds a text-only segment-aligned request payload', function () {
    $invocation = TranslationInvocation::create(
        transcriptionId: 7,
        targetLanguage: TranslationTarget::Chinese,
        segments: [
            new TranslationSegmentData(3, 12.5, 15.999, 'Hello', LanguageIdentifier::English),
        ],
        translationId: 11,
    );

    $payload = TranslationRequest::create($invocation, '1.0')->toArray();

    expect($payload['transcription_id'])->toBe(7)
        ->and($payload['translation_id'])->toBe(11)
        ->and($payload['target_language'])->toBe('zh')
        ->and($payload['contract_version'])->toBe('1.0')
        ->and($payload['segments'][0])->toBe([
            'segment_index' => 3,
            'start_seconds' => 12.5,
            'end_seconds' => 15.999,
            'text' => 'Hello',
            'source_language' => 'en',
        ])
        ->and($payload)->not->toHaveKey('media_reference')
        ->and($payload)->not->toHaveKey('storage_key');
});
