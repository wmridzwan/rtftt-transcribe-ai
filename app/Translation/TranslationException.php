<?php

namespace App\Translation;

use RuntimeException;

/**
 * Provider-neutral translation exception (ADR-022).
 *
 * Wraps the authoritative TranslationFailure taxonomy and provides user-safe
 * messaging without leaking provider internals.
 */
class TranslationException extends RuntimeException
{
    public function __construct(
        public readonly TranslationFailure $failure,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: self::failureMessage($failure), 0, $previous);
    }

    private static function failureMessage(TranslationFailure $failure): string
    {
        return match ($failure) {
            TranslationFailure::ProviderUnavailable => 'Translation provider unavailable.',
            TranslationFailure::ProviderTimeout => 'Translation provider timeout.',
            TranslationFailure::ProviderFailed => 'Translation provider failed.',
            TranslationFailure::MalformedOutput => 'Malformed translation response.',
            TranslationFailure::MissingSegments => 'Translation response is missing segments.',
            TranslationFailure::UnsupportedSource => 'Unsupported source language.',
            TranslationFailure::InvalidRequest => 'Invalid translation request.',
            TranslationFailure::PersistenceFailed => 'Translation persistence failed.',
            TranslationFailure::ProcessingFailed => 'Translation processing failed.',
            TranslationFailure::ConfigurationError => 'Translation configuration error.',
        };
    }
}
