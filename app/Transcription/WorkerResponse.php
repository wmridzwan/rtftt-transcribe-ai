<?php

namespace App\Transcription;

/**
 * Worker response DTO — returned from the Python worker.
 *
 * Provider-neutral: no faster-whisper or Python types leak.
 */
final readonly class WorkerResponse
{
    /**
     * @param  list<WorkerSegmentData>  $segments
     */
    public function __construct(
        public string $contractVersion,
        public string $text,
        public LanguageIdentifier $language,
        public float $durationSeconds,
        public bool $speechDetected,
        public array $segments,
    ) {}

    /**
     * Convert to NormalizedTranscript for application domain.
     */
    public function toNormalizedTranscript(): NormalizedTranscript
    {
        $domainSegments = array_map(
            fn (WorkerSegmentData $s) => new TranscriptSegmentData(
                segmentIndex: $s->segmentIndex,
                startSeconds: $s->startSeconds,
                endSeconds: $s->endSeconds,
                text: $s->text,
                language: $s->language,
            ),
            $this->segments,
        );

        return new NormalizedTranscript(
            text: $this->text,
            detectedLanguage: $this->language,
            durationSeconds: $this->durationSeconds,
            speechDetected: $this->speechDetected,
            segments: $domainSegments,
        );
    }
}
