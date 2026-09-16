<?php

namespace App\Transcription;

use Illuminate\Support\Str;

/**
 * Worker request DTO — sent from Laravel to the Python worker.
 *
 * Contains only small identifiers. Must not serialize media binary
 * content or provider instances.
 */
final readonly class WorkerRequest
{
    public function __construct(
        public string $requestId,
        public int $transcriptionId,
        public int $attemptId,
        public TranscriptionMedia $mediaReference,
        public ?LanguageIdentifier $requestedLanguage,
        public string $contractVersion,
    ) {}

    /**
     * Create a new request with a server-generated request ID.
     */
    public static function create(
        int $transcriptionId,
        int $attemptId,
        TranscriptionMedia $mediaReference,
        ?LanguageIdentifier $requestedLanguage,
    ): self {
        return new self(
            requestId: (string) Str::uuid(),
            transcriptionId: $transcriptionId,
            attemptId: $attemptId,
            mediaReference: $mediaReference,
            requestedLanguage: $requestedLanguage,
            contractVersion: WorkerContract::VERSION,
        );
    }

    /**
     * Serialize to array for JSON transport.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'transcription_id' => $this->transcriptionId,
            'attempt_id' => $this->attemptId,
            'media_reference' => [
                'storage_key' => $this->mediaReference->storageKey,
                'mime_type' => $this->mediaReference->mimeType,
                'file_size_bytes' => $this->mediaReference->fileSizeBytes,
                'duration_seconds' => $this->mediaReference->durationSeconds,
            ],
            'requested_language' => $this->requestedLanguage?->value,
            'contract_version' => $this->contractVersion,
        ];
    }
}
