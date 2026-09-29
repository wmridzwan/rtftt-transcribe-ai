<?php

namespace App\Transcription;

/**
 * Outbound chunk request for the reference external transcription boundary.
 *
 * Carries small identifiers only: never media bytes, never paths beyond the
 * existing opaque storage key. The authorization value is fixture-injected
 * transport metadata; it must never be written to logs.
 */
final readonly class ReferenceExternalChunkRequest
{
    public function __construct(
        public string $providerKey,
        public string $modelPinned,
        public string $baseUrl,
        public string $authorization,
        public string $requestId,
        public int $transcriptionId,
        public int $processingAttemptId,
        public int $attemptSeq,
        public int $chunkIndex,
        public string $chunkId,
        public int $offsetMs,
        public ?int $windowMs,
        public string $mediaStorageKey,
        public string $mimeType,
        public ?string $requestedLanguage,
        public int $timeoutSeconds,
    ) {}
}
