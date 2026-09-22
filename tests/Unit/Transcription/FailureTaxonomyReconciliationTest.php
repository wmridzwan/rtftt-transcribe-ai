<?php

use App\Transcription\TranscriptionFailure;
use App\Transcription\WorkerErrorResponse;

/*
 * P3-007 / ADR-018 failure-taxonomy reconciliation.
 *
 * Laravel's provider-neutral TranscriptionFailure taxonomy is the authoritative
 * domain retryability source. The worker envelope's `retryable` flag is advisory
 * transport metadata and must never override it.
 */

test('the laravel taxonomy defines the authoritative retryable set', function () {
    $retryable = array_map(
        static fn (TranscriptionFailure $failure): string => $failure->value,
        array_values(array_filter(
            TranscriptionFailure::cases(),
            static fn (TranscriptionFailure $failure): bool => $failure->isRetryable(),
        )),
    );

    expect($retryable)->toBe([
        'WORKER_UNAVAILABLE',
        'WORKER_TIMEOUT',
        'WORKER_SATURATED',
        'RESOURCE_EXHAUSTED',
    ]);
});

test('the worker advisory retryable flag cannot override the laravel taxonomy', function () {
    $response = WorkerErrorResponse::fromArray([
        'error_code' => 'FFMPEG_FAILED',
        'retryable' => true,
        'safe_message' => 'Audio processing failed.',
        'request_id' => 'req-1',
    ]);

    expect($response->toFailure())->toBe(TranscriptionFailure::FfmpegFailed)
        ->and($response->toFailure()->isRetryable())->toBeFalse();
});

test('media, processing, persistence and configuration failures are not retryable', function () {
    expect(TranscriptionFailure::MediaMissing->isRetryable())->toBeFalse()
        ->and(TranscriptionFailure::MediaRejected->isRetryable())->toBeFalse()
        ->and(TranscriptionFailure::FfmpegFailed->isRetryable())->toBeFalse()
        ->and(TranscriptionFailure::ProcessingFailed->isRetryable())->toBeFalse()
        ->and(TranscriptionFailure::InvalidWorkerResponse->isRetryable())->toBeFalse()
        ->and(TranscriptionFailure::PersistenceFailed->isRetryable())->toBeFalse()
        ->and(TranscriptionFailure::ConfigurationError->isRetryable())->toBeFalse();
});
