<?php

namespace App\Translation;

/**
 * Text-only translation worker request payload (ADR-022, D5-04).
 *
 * Only segment-aligned source text and small identifiers are carried; no media
 * path or binary crosses the provider boundary.
 */
final readonly class TranslationRequest
{
    /**
     * @param  list<array<string, mixed>>  $segments
     */
    public function __construct(
        public string $requestId,
        public int $transcriptionId,
        public ?int $translationId,
        public TranslationTarget $targetLanguage,
        public array $segments,
        public string $contractVersion,
    ) {}

    public static function create(
        TranslationInvocation $invocation,
        string $contractVersion,
    ): self {
        return new self(
            requestId: $invocation->requestId,
            transcriptionId: $invocation->transcriptionId,
            translationId: $invocation->translationId,
            targetLanguage: $invocation->targetLanguage,
            segments: array_map(
                static fn (TranslationSegmentData $segment): array => [
                    'segment_index' => $segment->segmentIndex,
                    'start_seconds' => $segment->startSeconds,
                    'end_seconds' => $segment->endSeconds,
                    'text' => $segment->text,
                    'source_language' => $segment->sourceLanguage->value,
                ],
                $invocation->segments,
            ),
            contractVersion: $contractVersion,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'transcription_id' => $this->transcriptionId,
            'translation_id' => $this->translationId,
            'target_language' => $this->targetLanguage->value,
            'contract_version' => $this->contractVersion,
            'segments' => $this->segments,
        ];
    }
}
