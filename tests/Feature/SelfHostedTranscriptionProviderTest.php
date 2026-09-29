<?php

use App\Transcription\HttpTranscriptionProvider;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\SelfHostedTranscriptionProvider;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProvider;
use App\Transcription\WorkerContract;
use Illuminate\Support\Facades\Http;

function selfHostedParityInvocation(): TranscriptionInvocation
{
    return TranscriptionInvocation::create(
        transcriptionId: 42,
        processingAttemptId: 7,
        media: new TranscriptionMedia(
            storageKey: 'media/test.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 1024000,
        ),
        requestedLanguage: LanguageIdentifier::Malay,
    );
}

function successfulTranscriptionEnvelope(): array
{
    return [
        'contract_version' => WorkerContract::VERSION,
        'text' => 'Hello world. Apa khabar?',
        'language' => 'en',
        'duration_seconds' => 5.0,
        'speech_detected' => true,
        'segments' => [
            [
                'segment_index' => 0,
                'start_seconds' => 0.0,
                'end_seconds' => 2.5,
                'text' => 'Hello world.',
                'language' => 'en',
            ],
            [
                'segment_index' => 1,
                'start_seconds' => 2.5,
                'end_seconds' => 5.0,
                'text' => 'Apa khabar?',
                'language' => 'ms',
            ],
        ],
    ];
}

it('binds the transcription provider contract to the self-hosted adapter', function () {
    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($resolved)->toBeInstanceOf(TranscriptionProvider::class)
        // PP-T2 §8: selection is re-evaluated on every interface resolution,
        // so the container no longer returns a cached singleton instance —
        // each resolution must still be the self-hosted adapter.
        ->and(app(TranscriptionProvider::class))->toBeInstanceOf(SelfHostedTranscriptionProvider::class);
});

it('declares no behavior of its own beyond the proven self-hosted path', function () {
    $ownMethods = array_filter(
        (new ReflectionClass(SelfHostedTranscriptionProvider::class))->getMethods(),
        fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === SelfHostedTranscriptionProvider::class,
    );

    expect($ownMethods)->toBeEmpty();
});

it('produces output exactly equal to the pre-T1 provider for the same response', function () {
    Http::fake([
        'localhost:8000/transcribe' => Http::response(successfulTranscriptionEnvelope(), 200),
    ]);

    $legacy = new HttpTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
    );
    $adapter = new SelfHostedTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
    );

    $legacyResult = $legacy->transcribe(selfHostedParityInvocation());
    $adapterResult = $adapter->transcribe(selfHostedParityInvocation());

    expect($adapterResult)->toBeInstanceOf(NormalizedTranscript::class)
        ->and($adapterResult)->toEqual($legacyResult)
        ->and($adapterResult->text)->toBe('Hello world. Apa khabar?');

    Http::assertSentCount(2);
});

it('preserves failure meaning identically to the pre-T1 provider', function () {
    Http::fake([
        'localhost:8000/transcribe' => Http::response([
            'error_code' => 'TIMEOUT',
            'retryable' => true,
            'safe_message' => 'Worker timeout.',
            'request_id' => 'parity-req',
        ], 500),
    ]);

    $legacy = new HttpTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
    );
    $adapter = new SelfHostedTranscriptionProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
    );

    foreach ([$legacy, $adapter] as $index => $provider) {
        try {
            $provider->transcribe(selfHostedParityInvocation());
            $this->fail('Expected TranscriptionException for provider '.$index.'.');
        } catch (TranscriptionException $exception) {
            expect($exception->getMessage())->toBe('Worker timeout.')
                ->and($exception->failure->isRetryable())->toBeTrue();
        }
    }
});

it('introduces no routing or external-provider selection', function () {
    $reflection = new ReflectionClass(SelfHostedTranscriptionProvider::class);

    $ownProperties = array_filter(
        $reflection->getProperties(),
        fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === SelfHostedTranscriptionProvider::class,
    );

    expect($reflection->getParentClass()->getName())->toBe(HttpTranscriptionProvider::class)
        ->and($ownProperties)->toBeEmpty();
});
