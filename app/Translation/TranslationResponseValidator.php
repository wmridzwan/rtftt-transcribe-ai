<?php

namespace App\Translation;

use App\Transcription\LanguageIdentifier;

/**
 * Validate and parse a translation worker response (ADR-022, D5-04).
 *
 * Rejects malformed or partial responses with a TranslationException so a
 * provider boundary can never hand a non-aligned result to the persistence
 * layer.
 */
final class TranslationResponseValidator
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws TranslationException
     */
    public static function fromArray(
        array $data,
        TranslationTarget $expectedTarget,
        string $provider,
        string $model,
    ): TranslationResult {
        $target = TranslationTarget::fromBcp47((string) ($data['target_language'] ?? ''));

        if ($target === null) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response missing or unsupported target language.',
            );
        }

        if ($target !== $expectedTarget) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response target language does not match the requested target.',
            );
        }

        if (! is_array($data['segments'] ?? null)) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response missing or invalid segments array.',
            );
        }

        $segments = [];

        foreach ($data['segments'] as $i => $segment) {
            if (! is_array($segment)) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} is not an array.",
                );
            }

            if (! isset($segment['segment_index'], $segment['start_seconds'], $segment['end_seconds'], $segment['text'])) {
                throw new TranslationException(
                    TranslationFailure::MissingSegments,
                    "Worker response segment {$i} is missing required fields.",
                );
            }

            try {
                $segments[] = new TranslationSegmentData(
                    segmentIndex: (int) $segment['segment_index'],
                    startSeconds: (float) $segment['start_seconds'],
                    endSeconds: (float) $segment['end_seconds'],
                    text: (string) $segment['text'],
                    sourceLanguage: LanguageIdentifier::fromBcp47((string) ($segment['source_language'] ?? 'und')),
                );
            } catch (\InvalidArgumentException $exception) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} has invalid alignment values.",
                    $exception,
                );
            }
        }

        try {
            return new TranslationResult(
                targetLanguage: $target,
                fullText: (string) ($data['text'] ?? ''),
                segments: $segments,
                provider: $provider,
                model: $model,
            );
        } catch (\InvalidArgumentException $exception) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response produced an invalid translation result.',
                $exception,
            );
        }
    }

    /**
     * Map a worker error envelope code to the authoritative Laravel taxonomy.
     * The worker's advisory retryable flag is never used to control domain
     * retry policy.
     */
    public static function failureFromCode(string $code): TranslationFailure
    {
        return match (strtoupper($code)) {
            'PROVIDER_UNAVAILABLE' => TranslationFailure::ProviderUnavailable,
            'PROVIDER_TIMEOUT' => TranslationFailure::ProviderTimeout,
            'UNSUPPORTED_SOURCE' => TranslationFailure::UnsupportedSource,
            'MISSING_SEGMENTS' => TranslationFailure::MissingSegments,
            'INVALID_REQUEST' => TranslationFailure::InvalidRequest,
            'CONFIGURATION_ERROR' => TranslationFailure::ConfigurationError,
            'MALFORMED_OUTPUT', 'INVALID_RESPONSE' => TranslationFailure::MalformedOutput,
            default => TranslationFailure::ProviderFailed,
        };
    }
}
