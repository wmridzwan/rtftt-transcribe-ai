<?php

namespace App\Transcription;

/**
 * A single segment of a transcription result.
 *
 * Timestamp invariants: start_seconds >= 0, end_seconds >= start_seconds.
 * One best-supported language per segment. Mixed-language segments
 * are supported at the transcript level; each segment carries a single
 * language tag. If segment language cannot be defensibly determined,
 * language = Undetermined.
 */
final readonly class TranscriptSegmentData
{
    public function __construct(
        public int $segmentIndex,
        public float $startSeconds,
        public float $endSeconds,
        public string $text,
        public LanguageIdentifier $language,
    ) {
        if ($segmentIndex < 0) {
            throw new \InvalidArgumentException('Segment index must be non-negative.');
        }

        if ($startSeconds < 0) {
            throw new \InvalidArgumentException('Start seconds must be non-negative.');
        }

        if ($endSeconds < $startSeconds) {
            throw new \InvalidArgumentException('End seconds must be greater than or equal to start seconds.');
        }
    }
}
