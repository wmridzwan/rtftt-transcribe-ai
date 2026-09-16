<?php

namespace App\Transcription;

/**
 * Normalized transcription result from a provider.
 *
 * Provider-neutral: no faster-whisper, FFmpeg, or Python types leak.
 * Transcript-level language is dominant/summary metadata.
 * Segment-level language is the canonical multilingual unit.
 */
final readonly class NormalizedTranscript
{
    /**
     * @param  list<TranscriptSegmentData>  $segments
     */
    public function __construct(
        public string $text,
        public LanguageIdentifier $detectedLanguage,
        public float $durationSeconds,
        public bool $speechDetected,
        public array $segments,
    ) {
        if ($durationSeconds < 0) {
            throw new \InvalidArgumentException('Duration seconds must be non-negative.');
        }

        if (! $speechDetected && $text !== '') {
            throw new \InvalidArgumentException('Speech not detected but text is non-empty.');
        }

        if (! $speechDetected && $segments !== []) {
            throw new \InvalidArgumentException('Speech not detected but segments are non-empty.');
        }
    }

    /**
     * Create a no-speech result.
     */
    public static function noSpeech(float $durationSeconds): self
    {
        return new self(
            text: '',
            detectedLanguage: LanguageIdentifier::Undetermined,
            durationSeconds: $durationSeconds,
            speechDetected: false,
            segments: [],
        );
    }
}
