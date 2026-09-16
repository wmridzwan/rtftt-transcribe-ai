<?php

namespace App\Transcription;

use RuntimeException;

/**
 * Provider-neutral transcription exception.
 *
 * Wraps failure categories and provides user-safe messaging without
 * leaking provider internals.
 */
class TranscriptionException extends RuntimeException
{
    public function __construct(
        public readonly TranscriptionFailure $failure,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?: self::failureMessage($failure), 0, $previous);
    }

    private static function failureMessage(TranscriptionFailure $failure): string
    {
        return match ($failure) {
            TranscriptionFailure::MediaMissing => 'Media file not found.',
            TranscriptionFailure::MediaRejected => 'Media file rejected by worker.',
            TranscriptionFailure::WorkerUnavailable => 'Transcription worker unavailable.',
            TranscriptionFailure::WorkerAuthFailed => 'Worker authentication failed.',
            TranscriptionFailure::WorkerTimeout => 'Worker timeout.',
            TranscriptionFailure::WorkerSaturated => 'Worker saturated.',
            TranscriptionFailure::FfmpegFailed => 'FFmpeg processing failed.',
            TranscriptionFailure::ResourceExhausted => 'System resources exhausted.',
            TranscriptionFailure::InvalidWorkerResponse => 'Invalid worker response.',
            TranscriptionFailure::ProcessingFailed => 'Processing failed.',
            TranscriptionFailure::PersistenceFailed => 'Persistence failed.',
            TranscriptionFailure::ConfigurationError => 'Configuration error.',
        };
    }
}
