<?php

namespace App\Translation;

use App\Transcription\LanguageIdentifier;

/**
 * A single segment-aligned translated unit (ADR-022, D5-01).
 *
 * Alignment is a hard invariant: the translated segment retains the source
 * segment index, timestamps, and source-language marker. Timestamps are copied
 * from the source and never recomputed.
 */
final readonly class TranslationSegmentData
{
    public function __construct(
        public int $segmentIndex,
        public float $startSeconds,
        public float $endSeconds,
        public string $text,
        public LanguageIdentifier $sourceLanguage,
    ) {
        if ($segmentIndex < 0) {
            throw new \InvalidArgumentException('Segment index must be non-negative.');
        }

        if (! is_finite($startSeconds)) {
            throw new \InvalidArgumentException('Start seconds must be a finite number.');
        }

        if (! is_finite($endSeconds)) {
            throw new \InvalidArgumentException('End seconds must be a finite number.');
        }

        if ($startSeconds < 0) {
            throw new \InvalidArgumentException('Start seconds must be non-negative.');
        }

        if ($endSeconds < $startSeconds) {
            throw new \InvalidArgumentException('End seconds must be greater than or equal to start seconds.');
        }
    }
}
