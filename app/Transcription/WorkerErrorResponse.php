<?php

namespace App\Transcription;

/**
 * Worker error response envelope.
 *
 * Returned by the worker on failure. Retryable indicates whether
 * the caller should attempt retry.
 */
final readonly class WorkerErrorResponse
{
    public function __construct(
        public string $errorCode,
        public bool $retryable,
        public string $safeMessage,
        public string $requestId,
    ) {}

    /**
     * Parse a worker error response from decoded JSON.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            errorCode: (string) ($data['error_code'] ?? 'UNKNOWN'),
            retryable: (bool) ($data['retryable'] ?? false),
            safeMessage: (string) ($data['safe_message'] ?? 'An error occurred.'),
            requestId: (string) ($data['request_id'] ?? ''),
        );
    }

    /**
     * Map worker error code to a TranscriptionFailure category.
     */
    public function toFailure(): TranscriptionFailure
    {
        return match ($this->errorCode) {
            'MEDIA_MISSING' => TranscriptionFailure::MediaMissing,
            'MEDIA_REJECTED' => TranscriptionFailure::MediaRejected,
            'AUTH_FAILED' => TranscriptionFailure::WorkerAuthFailed,
            'TIMEOUT' => TranscriptionFailure::WorkerTimeout,
            'SATURATED' => TranscriptionFailure::WorkerSaturated,
            'FFMPEG_FAILED' => TranscriptionFailure::FfmpegFailed,
            'RESOURCE_EXHAUSTED' => TranscriptionFailure::ResourceExhausted,
            'INVALID_RESPONSE' => TranscriptionFailure::InvalidWorkerResponse,
            'PROCESSING_FAILED' => TranscriptionFailure::ProcessingFailed,
            default => TranscriptionFailure::ProcessingFailed,
        };
    }
}
