<?php

namespace App\Translation;

/**
 * Provider-neutral failure taxonomy for translation (ADR-022).
 *
 * Laravel-side taxonomy is authoritative for domain retryability; any
 * worker-provided flag is transport-advisory only.
 */
enum TranslationFailure: string
{
    case ProviderUnavailable = 'PROVIDER_UNAVAILABLE';
    case ProviderTimeout = 'PROVIDER_TIMEOUT';
    case ProviderFailed = 'PROVIDER_FAILED';
    case MalformedOutput = 'MALFORMED_OUTPUT';
    case MissingSegments = 'MISSING_SEGMENTS';
    case UnsupportedSource = 'UNSUPPORTED_SOURCE';
    case InvalidRequest = 'INVALID_REQUEST';
    case PersistenceFailed = 'PERSISTENCE_FAILED';
    case ProcessingFailed = 'PROCESSING_FAILED';
    case ConfigurationError = 'CONFIGURATION_ERROR';

    /**
     * Whether this failure class is eligible for domain retry.
     *
     * Phase 5 is manual-retry only; this flag informs the retry surface, it
     * does not schedule retries.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::ProviderUnavailable,
            self::ProviderTimeout,
            self::ProviderFailed,
            self::ProcessingFailed => true,
            default => false,
        };
    }

    /**
     * User-facing explanation. Deliberately generic: it never names credentials,
     * tokens, endpoints, provider internals, or configuration, and the persisted
     * failure code stays available for diagnostics.
     */
    public function userMessage(): string
    {
        return match ($this) {
            self::ProviderUnavailable => 'The translation service is temporarily unavailable.',
            self::ProviderTimeout => 'The translation took too long and was stopped.',
            self::ProviderFailed, self::ProcessingFailed => 'The translation could not be completed.',
            self::MalformedOutput, self::MissingSegments => 'The translation service returned a result that could not be used.',
            self::UnsupportedSource => 'The language of this transcript is not supported for translation.',
            self::InvalidRequest => 'The translation request was not valid.',
            self::PersistenceFailed => 'The translation could not be saved.',
            self::ConfigurationError => 'Translation is not available right now because of a service configuration problem.',
        };
    }
}
