<?php

use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationResponseValidator;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;

function validatorInvocation(): TranslationInvocation
{
    return TranslationInvocation::create(
        transcriptionId: 42,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 5.0, 'Hello', LanguageIdentifier::English),
        ],
    );
}

function validResponse(): array
{
    return [
        'target_language' => 'ms',
        'text' => 'Hai',
        'segments' => [
            [
                'segment_index' => 0,
                'start_seconds' => 0.0,
                'end_seconds' => 5.0,
                'text' => 'Hai',
                'source_language' => 'en',
            ],
        ],
    ];
}

function validateResponse(array $body): mixed
{
    return TranslationResponseValidator::fromArray($body, validatorInvocation(), 'self-hosted', 'self-hosted-default');
}

it('parses a valid worker translation response', function () {
    $result = validateResponse(validResponse());

    expect($result->targetLanguage)->toBe(TranslationTarget::Malay)
        ->and($result->fullText)->toBe('Hai')
        ->and($result->segments)->toHaveCount(1)
        ->and($result->provider)->toBe('self-hosted')
        ->and($result->model)->toBe('self-hosted-default');
});

it('copies authoritative alignment from the invocation, not the provider', function () {
    $body = validResponse();
    $body['segments'][0]['start_seconds'] = 0.0004; // within tolerance
    $body['segments'][0]['source_language'] = 'en';

    $result = validateResponse($body);

    expect($result->segments[0]->startSeconds)->toBe(0.0)
        ->and($result->segments[0]->endSeconds)->toBe(5.0)
        ->and($result->segments[0]->sourceLanguage)->toBe(LanguageIdentifier::English);
});

it('rejects a missing or non-string target', function () {
    $body = validResponse();
    unset($body['target_language']);

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);

    $body = validResponse();
    $body['target_language'] = ['ms'];

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects a target mismatch', function () {
    $body = validResponse();
    $body['target_language'] = 'en';

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects a missing segments array', function () {
    $body = validResponse();
    unset($body['segments']);

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects an empty or partial segment set', function () {
    $body = validResponse();
    $body['segments'] = [];

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects a foreign segment index', function () {
    $body = validResponse();
    $body['segments'][0]['segment_index'] = 99;

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects duplicate segment indices', function () {
    $invocation = TranslationInvocation::create(
        transcriptionId: 42,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 5.0, 'A', LanguageIdentifier::English),
            new TranslationSegmentData(1, 5.0, 10.0, 'B', LanguageIdentifier::English),
        ],
    );

    $body = [
        'target_language' => 'ms',
        'text' => 'x',
        'segments' => [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 5.0, 'text' => 'x', 'source_language' => 'en'],
            ['segment_index' => 0, 'start_seconds' => 5.0, 'end_seconds' => 10.0, 'text' => 'y', 'source_language' => 'en'],
        ],
    ];

    expect(fn () => TranslationResponseValidator::fromArray($body, $invocation, 'p', 'm'))
        ->toThrow(TranslationException::class);
});

it('rejects non-numeric and non-integer fields without a raw warning', function () {
    $body = validResponse();
    $body['segments'][0]['segment_index'] = 'abc';

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);

    $body = validResponse();
    $body['segments'][0]['start_seconds'] = 'zzz';

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects a non-string text without a raw array-to-string warning', function () {
    $body = validResponse();
    $body['segments'][0]['text'] = ['a'];

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects a timestamp drift beyond tolerance', function () {
    $body = validResponse();
    $body['segments'][0]['end_seconds'] = 5.5;

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('rejects a source-language echo mismatch', function () {
    $body = validResponse();
    $body['segments'][0]['source_language'] = 'zh';

    expect(fn () => validateResponse($body))->toThrow(TranslationException::class);
});

it('maps worker error codes to the authoritative taxonomy', function () {
    expect(TranslationResponseValidator::failureFromCode('PROVIDER_UNAVAILABLE'))->toBe(TranslationFailure::ProviderUnavailable)
        ->and(TranslationResponseValidator::failureFromCode('PROVIDER_TIMEOUT'))->toBe(TranslationFailure::ProviderTimeout)
        ->and(TranslationResponseValidator::failureFromCode('UNSUPPORTED_SOURCE'))->toBe(TranslationFailure::UnsupportedSource)
        ->and(TranslationResponseValidator::failureFromCode('MISSING_SEGMENTS'))->toBe(TranslationFailure::MissingSegments)
        ->and(TranslationResponseValidator::failureFromCode('INVALID_REQUEST'))->toBe(TranslationFailure::InvalidRequest)
        ->and(TranslationResponseValidator::failureFromCode('CONFIGURATION_ERROR'))->toBe(TranslationFailure::ConfigurationError)
        ->and(TranslationResponseValidator::failureFromCode('MALFORMED_OUTPUT'))->toBe(TranslationFailure::MalformedOutput)
        ->and(TranslationResponseValidator::failureFromCode('SOMETHING_NEW'))->toBe(TranslationFailure::ProviderFailed);
});
