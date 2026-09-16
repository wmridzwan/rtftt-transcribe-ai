<?php

use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;

it('creates exception with failure category', function () {
    $exception = new TranscriptionException(
        failure: TranscriptionFailure::WorkerTimeout,
    );

    expect($exception->failure)->toBe(TranscriptionFailure::WorkerTimeout);
    expect($exception->getMessage())->toBe('Worker timeout.');
});

it('creates exception with custom message', function () {
    $exception = new TranscriptionException(
        failure: TranscriptionFailure::FfmpegFailed,
        message: 'FFmpeg process exited with code 1.',
    );

    expect($exception->getMessage())->toBe('FFmpeg process exited with code 1.');
});

it('preserves previous exception', function () {
    $previous = new RuntimeException('original');
    $exception = new TranscriptionException(
        failure: TranscriptionFailure::ProcessingFailed,
        previous: $previous,
    );

    expect($exception->getPrevious())->toBe($previous);
});

it('identifies retryable failures', function () {
    expect(TranscriptionFailure::WorkerUnavailable->isRetryable())->toBeTrue();
    expect(TranscriptionFailure::WorkerTimeout->isRetryable())->toBeTrue();
    expect(TranscriptionFailure::WorkerSaturated->isRetryable())->toBeTrue();
    expect(TranscriptionFailure::ResourceExhausted->isRetryable())->toBeTrue();
});

it('identifies non-retryable failures', function () {
    expect(TranscriptionFailure::MediaMissing->isRetryable())->toBeFalse();
    expect(TranscriptionFailure::MediaRejected->isRetryable())->toBeFalse();
    expect(TranscriptionFailure::WorkerAuthFailed->isRetryable())->toBeFalse();
    expect(TranscriptionFailure::FfmpegFailed->isRetryable())->toBeFalse();
    expect(TranscriptionFailure::InvalidWorkerResponse->isRetryable())->toBeFalse();
    expect(TranscriptionFailure::ProcessingFailed->isRetryable())->toBeFalse();
    expect(TranscriptionFailure::PersistenceFailed->isRetryable())->toBeFalse();
    expect(TranscriptionFailure::ConfigurationError->isRetryable())->toBeFalse();
});
