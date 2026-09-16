<?php

namespace App\Transcription;

/**
 * Individual segment data from the worker response.
 */
final readonly class WorkerSegmentData
{
    public function __construct(
        public int $segmentIndex,
        public float $startSeconds,
        public float $endSeconds,
        public string $text,
        public LanguageIdentifier $language,
    ) {}
}
