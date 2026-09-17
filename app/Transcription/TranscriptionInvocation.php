<?php

namespace App\Transcription;

use Illuminate\Support\Str;

/**
 * Provider-neutral invocation context for transcription.
 *
 * Carries server-generated processing identity (transcription ID,
 * processing attempt ID, request ID) together with media and options.
 * This is the single entry point for TranscriptionProvider::transcribe().
 *
 * Future P3-004/P3-006 can supply canonical persisted identifiers
 * without redesigning the provider interface.
 */
final readonly class TranscriptionInvocation
{
    public function __construct(
        public int $transcriptionId,
        public int $processingAttemptId,
        public string $requestId,
        public TranscriptionMedia $media,
        public TranscriptionOptions $options,
    ) {
        if ($transcriptionId <= 0) {
            throw new \InvalidArgumentException('Transcription ID must be a positive integer.');
        }

        if ($processingAttemptId <= 0) {
            throw new \InvalidArgumentException('Processing attempt ID must be a positive integer.');
        }

        if ($requestId === '') {
            throw new \InvalidArgumentException('Request ID must not be empty.');
        }
    }

    /**
     * Create an invocation with a server-generated request ID.
     */
    public static function create(
        int $transcriptionId,
        int $processingAttemptId,
        TranscriptionMedia $media,
        ?LanguageIdentifier $requestedLanguage = null,
    ): self {
        return new self(
            transcriptionId: $transcriptionId,
            processingAttemptId: $processingAttemptId,
            requestId: (string) Str::uuid(),
            media: $media,
            options: new TranscriptionOptions(requestedLanguage: $requestedLanguage),
        );
    }
}
