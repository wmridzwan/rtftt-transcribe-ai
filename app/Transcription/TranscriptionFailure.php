<?php

namespace App\Transcription;

/**
 * Provider-neutral exception taxonomy for transcription failures.
 *
 * Each category maps to retryable/recoverable semantics and a
 * user-safe message class.
 */
enum TranscriptionFailure: string
{
    case MediaMissing = 'MEDIA_MISSING';
    case MediaRejected = 'MEDIA_REJECTED';
    case WorkerUnavailable = 'WORKER_UNAVAILABLE';
    case WorkerAuthFailed = 'WORKER_AUTH_FAILED';
    case WorkerTimeout = 'WORKER_TIMEOUT';
    case WorkerSaturated = 'WORKER_SATURATED';
    case FfmpegFailed = 'FFMPEG_FAILED';
    case ResourceExhausted = 'RESOURCE_EXHAUSTED';
    case InvalidWorkerResponse = 'INVALID_WORKER_RESPONSE';
    case ProcessingFailed = 'PROCESSING_FAILED';
    case PersistenceFailed = 'PERSISTENCE_FAILED';
    case ConfigurationError = 'CONFIGURATION_ERROR';

    /**
     * Whether this failure category is eligible for automatic retry.
     */
    public function isRetryable(): bool
    {
        return match ($this) {
            self::WorkerUnavailable,
            self::WorkerTimeout,
            self::WorkerSaturated,
            self::ResourceExhausted => true,
            default => false,
        };
    }
}
