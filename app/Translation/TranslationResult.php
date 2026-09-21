<?php

namespace App\Translation;

/**
 * Provider-neutral, segment-aligned translation result (ADR-022, D5-01/D5-04).
 *
 * Produced by a TranslationProvider and persisted verbatim by the writer.
 * Result segments must be aligned to the requested source segments: indices
 * are unique and non-negative, and timestamps are inherited from the source.
 */
final readonly class TranslationResult
{
    /**
     * @param  list<TranslationSegmentData>  $segments
     */
    public function __construct(
        public TranslationTarget $targetLanguage,
        public string $fullText,
        public array $segments,
        public string $provider,
        public string $model,
    ) {
        if ($provider === '') {
            throw new \InvalidArgumentException('Provider identity must not be empty.');
        }

        if ($model === '') {
            throw new \InvalidArgumentException('Model identity must not be empty.');
        }

        $seen = [];

        foreach ($segments as $segment) {
            if (isset($seen[$segment->segmentIndex])) {
                throw new \InvalidArgumentException('Translation result segment indices must be unique.');
            }

            $seen[$segment->segmentIndex] = true;
        }
    }
}
