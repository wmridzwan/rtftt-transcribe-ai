<?php

namespace App\Translation;

use Illuminate\Support\Str;

/**
 * Provider-neutral invocation context for translation (ADR-022).
 *
 * Carries server-generated identity plus segment-aligned source text. It is
 * the single entry point for TranslationProvider::translate(). No media path
 * or binary is carried: translation is text-only.
 */
final readonly class TranslationInvocation
{
    /**
     * @param  list<TranslationSegmentData>  $segments
     */
    public function __construct(
        public int $transcriptionId,
        public string $requestId,
        public TranslationTarget $targetLanguage,
        public array $segments,
        public ?int $translationId = null,
        public string $contractVersion = '1.0',
    ) {
        if ($transcriptionId <= 0) {
            throw new \InvalidArgumentException('Transcription ID must be a positive integer.');
        }

        if ($requestId === '') {
            throw new \InvalidArgumentException('Request ID must not be empty.');
        }

        if ($translationId !== null && $translationId <= 0) {
            throw new \InvalidArgumentException('Translation ID must be a positive integer when present.');
        }

        $seen = [];

        foreach ($segments as $segment) {
            if (isset($seen[$segment->segmentIndex])) {
                throw new \InvalidArgumentException('Invocation source segment indices must be unique.');
            }

            $seen[$segment->segmentIndex] = true;
        }
    }

    /**
     * Create an invocation with a server-generated request ID.
     *
     * @param  list<TranslationSegmentData>  $segments
     */
    public static function create(
        int $transcriptionId,
        TranslationTarget $targetLanguage,
        array $segments,
        ?int $translationId = null,
    ): self {
        return new self(
            transcriptionId: $transcriptionId,
            requestId: (string) Str::uuid(),
            targetLanguage: $targetLanguage,
            segments: $segments,
            translationId: $translationId,
        );
    }
}
