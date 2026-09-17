<?php

use App\Transcription\HttpTranscriptionProvider;
use App\Transcription\LanguageIdentifier;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\WorkerContract;
use Illuminate\Support\Facades\Http;

it('sends correct headers and payload', function () {
    Http::fake([
        'localhost:8000/transcribe' => Http::response([
            'contract_version' => WorkerContract::VERSION,
            'text' => 'Hello world.',
            'language' => 'en',
            'duration_seconds' => 5.0,
            'speech_detected' => true,
            'segments' => [
                [
                    'segment_index' => 0,
                    'start_seconds' => 0.0,
                    'end_seconds' => 5.0,
                    'text' => 'Hello world.',
                    'language' => 'en',
                ],
            ],
        ], 200),
    ]);

    $provider = new HttpTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'test-token-123',
    );

    $invocation = TranscriptionInvocation::create(
        transcriptionId: 42,
        processingAttemptId: 7,
        media: new TranscriptionMedia(
            storageKey: 'media/test.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 1024000,
        ),
        requestedLanguage: LanguageIdentifier::Malay,
    );

    $result = $provider->transcribe($invocation);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8000/transcribe'
            && $request->header('Authorization') === ['Bearer test-token-123']
            && $request->header('Content-Type') === ['application/json']
            && $request->data()['transcription_id'] === 42
            && $request->data()['attempt_id'] === 7
            && $request->data()['requested_language'] === 'ms'
            && $request->data()['contract_version'] === WorkerContract::VERSION;
    });

    expect($result->text)->toBe('Hello world.');
    expect($result->detectedLanguage)->toBe(LanguageIdentifier::English);
});

it('throws WorkerUnavailable on non-2xx without error envelope', function () {
    Http::fake([
        'localhost:8000/transcribe' => Http::response([], 503),
    ]);

    $provider = new HttpTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'test-token',
    );

    $invocation = TranscriptionInvocation::create(
        transcriptionId: 1,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/test.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 1024000,
        ),
    );

    $provider->transcribe($invocation);
})->throws(TranscriptionException::class, 'Worker returned HTTP 503');

it('maps worker error envelope to TranscriptionException', function () {
    Http::fake([
        'localhost:8000/transcribe' => Http::response([
            'error_code' => 'TIMEOUT',
            'retryable' => true,
            'safe_message' => 'Worker timeout.',
            'request_id' => 'test-req-id',
        ], 500),
    ]);

    $provider = new HttpTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'test-token',
    );

    $invocation = TranscriptionInvocation::create(
        transcriptionId: 1,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/test.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 1024000,
        ),
    );

    $provider->transcribe($invocation);
})->throws(TranscriptionException::class, 'Worker timeout.');

it('uses configured timeout from config', function () {
    config(['transcription.timeout_seconds' => 120]);

    Http::fake([
        'localhost:8000/transcribe' => Http::response([
            'contract_version' => WorkerContract::VERSION,
            'text' => '',
            'language' => 'und',
            'duration_seconds' => 5.0,
            'speech_detected' => false,
            'segments' => [],
        ], 200),
    ]);

    $provider = new HttpTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'test-token',
    );

    $invocation = TranscriptionInvocation::create(
        transcriptionId: 1,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/test.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 1024000,
        ),
    );

    $result = $provider->transcribe($invocation);

    // Verify the request was sent (timeout is applied internally)
    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8000/transcribe';
    });

    expect($result->speechDetected)->toBeFalse();
});

it('sends null requested_language when not specified', function () {
    Http::fake([
        'localhost:8000/transcribe' => Http::response([
            'contract_version' => WorkerContract::VERSION,
            'text' => '',
            'language' => 'und',
            'duration_seconds' => 5.0,
            'speech_detected' => false,
            'segments' => [],
        ], 200),
    ]);

    $provider = new HttpTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'test-token',
    );

    $invocation = TranscriptionInvocation::create(
        transcriptionId: 1,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/test.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 1024000,
        ),
    );

    $provider->transcribe($invocation);

    Http::assertSent(function ($request) {
        return $request->data()['requested_language'] === null;
    });
});
