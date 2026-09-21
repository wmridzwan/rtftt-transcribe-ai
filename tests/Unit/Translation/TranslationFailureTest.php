<?php

use App\Translation\TranslationException;
use App\Translation\TranslationFailure;

it('marks transient provider failures as retryable', function () {
    expect(TranslationFailure::ProviderUnavailable->isRetryable())->toBeTrue();
    expect(TranslationFailure::ProviderTimeout->isRetryable())->toBeTrue();
    expect(TranslationFailure::ProviderFailed->isRetryable())->toBeTrue();
    expect(TranslationFailure::ProcessingFailed->isRetryable())->toBeTrue();
});

it('marks deterministic failures as non-retryable', function () {
    expect(TranslationFailure::MalformedOutput->isRetryable())->toBeFalse();
    expect(TranslationFailure::MissingSegments->isRetryable())->toBeFalse();
    expect(TranslationFailure::UnsupportedSource->isRetryable())->toBeFalse();
    expect(TranslationFailure::InvalidRequest->isRetryable())->toBeFalse();
    expect(TranslationFailure::PersistenceFailed->isRetryable())->toBeFalse();
    expect(TranslationFailure::ConfigurationError->isRetryable())->toBeFalse();
});

it('wraps a failure in a safe exception', function () {
    $exception = new TranslationException(TranslationFailure::ProviderTimeout);

    expect($exception->failure)->toBe(TranslationFailure::ProviderTimeout);
    expect($exception->getMessage())->toBe('Translation provider timeout.');
});
