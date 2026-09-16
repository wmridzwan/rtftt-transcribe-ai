<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionMedia;
use App\Transcription\WorkerContract;
use App\Transcription\WorkerRequest;
use App\Transcription\WorkerResponse;
use App\Transcription\WorkerResponseValidator;
use App\Transcription\WorkerSegmentData;

it('creates a worker request with generated ID', function () {
    $media = new TranscriptionMedia(
        storageKey: 'media/abc123.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
    );

    $request = WorkerRequest::create(
        transcriptionId: 1,
        attemptId: 2,
        mediaReference: $media,
        requestedLanguage: LanguageIdentifier::Malay,
    );

    expect($request->requestId)->not->toBeEmpty();
    expect($request->transcriptionId)->toBe(1);
    expect($request->attemptId)->toBe(2);
    expect($request->mediaReference)->toBe($media);
    expect($request->requestedLanguage)->toBe(LanguageIdentifier::Malay);
    expect($request->contractVersion)->toBe(WorkerContract::VERSION);
});

it('serializes request to array', function () {
    $media = new TranscriptionMedia(
        storageKey: 'media/abc123.mp3',
        mimeType: 'audio/mpeg',
        fileSizeBytes: 1024000,
        durationSeconds: 120.5,
    );

    $request = WorkerRequest::create(
        transcriptionId: 1,
        attemptId: 2,
        mediaReference: $media,
        requestedLanguage: null,
    );

    $array = $request->toArray();

    expect($array['request_id'])->not->toBeEmpty();
    expect($array['transcription_id'])->toBe(1);
    expect($array['attempt_id'])->toBe(2);
    expect($array['media_reference']['storage_key'])->toBe('media/abc123.mp3');
    expect($array['media_reference']['mime_type'])->toBe('audio/mpeg');
    expect($array['media_reference']['file_size_bytes'])->toBe(1024000);
    expect($array['media_reference']['duration_seconds'])->toBe(120.5);
    expect($array['requested_language'])->toBeNull();
    expect($array['contract_version'])->toBe(WorkerContract::VERSION);
});

it('converts worker response to normalized transcript', function () {
    $response = new WorkerResponse(
        contractVersion: WorkerContract::VERSION,
        text: 'Hello. Selamat pagi.',
        language: LanguageIdentifier::English,
        durationSeconds: 10.0,
        speechDetected: true,
        segments: [
            new WorkerSegmentData(0, 0.0, 5.0, 'Hello.', LanguageIdentifier::English),
            new WorkerSegmentData(1, 5.0, 10.0, 'Selamat pagi.', LanguageIdentifier::Malay),
        ],
    );

    $transcript = $response->toNormalizedTranscript();

    expect($transcript->text)->toBe('Hello. Selamat pagi.');
    expect($transcript->detectedLanguage)->toBe(LanguageIdentifier::English);
    expect($transcript->durationSeconds)->toBe(10.0);
    expect($transcript->speechDetected)->toBeTrue();
    expect($transcript->segments)->toHaveCount(2);
    expect($transcript->segments[0]->language)->toBe(LanguageIdentifier::English);
    expect($transcript->segments[1]->language)->toBe(LanguageIdentifier::Malay);
});

it('validates a valid success response', function () {
    $data = [
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
    ];

    $response = WorkerResponseValidator::fromArray($data);

    expect($response->contractVersion)->toBe(WorkerContract::VERSION);
    expect($response->text)->toBe('Hello world.');
    expect($response->language)->toBe(LanguageIdentifier::English);
    expect($response->speechDetected)->toBeTrue();
    expect($response->segments)->toHaveCount(1);
});

it('rejects response with missing segments', function () {
    WorkerResponseValidator::fromArray([
        'text' => 'Hello',
        'language' => 'en',
        'duration_seconds' => 5.0,
        'speech_detected' => true,
    ]);
})->throws(TranscriptionException::class, 'Worker response missing or invalid segments array.');

it('rejects response with invalid segment type', function () {
    WorkerResponseValidator::fromArray([
        'text' => 'Hello',
        'language' => 'en',
        'duration_seconds' => 5.0,
        'speech_detected' => true,
        'segments' => ['invalid'],
    ]);
})->throws(TranscriptionException::class, 'Worker response segment 0 is not an array.');

it('rejects no-speech response with non-empty text', function () {
    WorkerResponseValidator::fromArray([
        'text' => 'Hello',
        'language' => 'en',
        'duration_seconds' => 5.0,
        'speech_detected' => false,
        'segments' => [],
    ]);
})->throws(TranscriptionException::class, 'Worker response: speech not detected but text is non-empty.');

it('rejects no-speech response with non-empty segments', function () {
    WorkerResponseValidator::fromArray([
        'text' => '',
        'language' => 'und',
        'duration_seconds' => 5.0,
        'speech_detected' => false,
        'segments' => [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 5.0, 'text' => 'Hello', 'language' => 'en'],
        ],
    ]);
})->throws(TranscriptionException::class, 'Worker response: speech not detected but segments are non-empty.');
