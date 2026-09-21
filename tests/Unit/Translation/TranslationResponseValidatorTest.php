<?php

use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationResponseValidator;
use App\Translation\TranslationTarget;

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

it('parses a valid worker translation response', function () {
    $result = TranslationResponseValidator::fromArray(
        validResponse(),
        TranslationTarget::Malay,
        'self-hosted',
        'self-hosted-default',
    );

    expect($result->targetLanguage)->toBe(TranslationTarget::Malay)
        ->and($result->fullText)->toBe('Hai')
        ->and($result->segments)->toHaveCount(1)
        ->and($result->provider)->toBe('self-hosted')
        ->and($result->model)->toBe('self-hosted-default');
});

it('rejects a missing or unsupported target', function () {
    $body = validResponse();
    unset($body['target_language']);

    expect(fn () => TranslationResponseValidator::fromArray($body, TranslationTarget::Malay, 'p', 'm'))
        ->toThrow(TranslationException::class);
});

it('rejects a target mismatch', function () {
    expect(fn () => TranslationResponseValidator::fromArray(validResponse(), TranslationTarget::English, 'p', 'm'))
        ->toThrow(TranslationException::class);
});

it('rejects a missing segments array', function () {
    $body = validResponse();
    unset($body['segments']);

    expect(fn () => TranslationResponseValidator::fromArray($body, TranslationTarget::Malay, 'p', 'm'))
        ->toThrow(TranslationException::class);
});

it('rejects a segment missing required fields', function () {
    $body = validResponse();
    unset($body['segments'][0]['text']);

    expect(fn () => TranslationResponseValidator::fromArray($body, TranslationTarget::Malay, 'p', 'm'))
        ->toThrow(TranslationException::class);
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
