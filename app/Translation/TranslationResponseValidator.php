<?php

namespace App\Translation;

/**
 * Validate and parse a translation worker response against the invocation
 * (ADR-022, D5-01/D5-04).
 *
 * The provider boundary is strict: the response must be segment-aligned to the
 * invocation (count, index set, timestamps, and source-language echo). Malformed
 * or partial responses raise a TranslationException and never reach persistence.
 */
final class TranslationResponseValidator
{
    private const TIMESTAMP_TOLERANCE_SECONDS = 0.0005;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws TranslationException
     */
    public static function fromArray(
        array $data,
        TranslationInvocation $invocation,
        string $provider,
        string $model,
    ): TranslationResult {
        $rawTarget = $data['target_language'] ?? null;

        if (! is_string($rawTarget)) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response has a missing or non-string target language.',
            );
        }

        $target = TranslationTarget::fromBcp47($rawTarget);

        if ($target === null) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response has an unsupported target language.',
            );
        }

        if ($target !== $invocation->targetLanguage) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response target language does not match the requested target.',
            );
        }

        if (isset($data['text']) && ! is_string($data['text'])) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response text must be a string.',
            );
        }

        if (! is_array($data['segments'] ?? null)) {
            throw new TranslationException(
                TranslationFailure::MalformedOutput,
                'Worker response missing or invalid segments array.',
            );
        }

        /** @var array<int, TranslationSegmentData> $expected */
        $expected = [];
        foreach ($invocation->segments as $source) {
            $expected[$source->segmentIndex] = $source;
        }

        if (count($data['segments']) !== count($expected)) {
            throw new TranslationException(
                TranslationFailure::MissingSegments,
                'Worker response segment count does not match the invocation.',
            );
        }

        $seen = [];
        $segments = [];

        foreach ($data['segments'] as $i => $segment) {
            if (! is_array($segment)) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} is not an object.",
                );
            }

            $index = self::strictInt($segment['segment_index'] ?? null);

            if ($index === null) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} has a missing or non-integer segment index.",
                );
            }

            if (isset($seen[$index])) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    'Worker response segment indices must be unique.',
                );
            }

            $seen[$index] = true;

            if (! isset($expected[$index])) {
                throw new TranslationException(
                    TranslationFailure::MissingSegments,
                    'Worker response contains a segment index that is not in the invocation.',
                );
            }

            $start = self::strictNumber($segment['start_seconds'] ?? null);
            $end = self::strictNumber($segment['end_seconds'] ?? null);

            if ($start === null || $end === null) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} has missing or non-numeric timestamps.",
                );
            }

            $source = $expected[$index];

            if (abs($start - $source->startSeconds) > self::TIMESTAMP_TOLERANCE_SECONDS
                || abs($end - $source->endSeconds) > self::TIMESTAMP_TOLERANCE_SECONDS) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    'Worker response segment timestamps do not match the invocation.',
                );
            }

            if (! is_string($segment['source_language'] ?? null)) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} is missing the source-language echo.",
                );
            }

            if ($segment['source_language'] !== $source->sourceLanguage->value) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    'Worker response source-language echo does not match the invocation.',
                );
            }

            if (! is_string($segment['text'] ?? null)) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} text must be a string.",
                );
            }

            // Persist authoritative alignment: index, timestamps, and source
            // language are copied from the invocation, never from the provider.
            try {
                $segments[] = new TranslationSegmentData(
                    segmentIndex: $source->segmentIndex,
                    startSeconds: $source->startSeconds,
                    endSeconds: $source->endSeconds,
                    text: $segment['text'],
                    sourceLanguage: $source->sourceLanguage,
                );
            } catch (\InvalidArgumentException $exception) {
                throw new TranslationException(
                    TranslationFailure::MalformedOutput,
                    "Worker response segment {$i} is invalid.",
                    $exception,
                );
            }
        }

        try {
            return new TranslationResult(
                targetLanguage: $target,
                fullText: is_string($data['text'] ?? null) ? $data['text'] : '',
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

    private static function strictInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/\A-?\d+\z/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }

    private static function strictNumber(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $float = (float) $value;

            return is_finite($float) ? $float : null;
        }

        if (is_string($value) && is_numeric($value)) {
            $float = (float) $value;

            return is_finite($float) ? $float : null;
        }

        return null;
    }
}
