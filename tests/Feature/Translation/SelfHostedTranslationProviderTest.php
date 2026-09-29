<?php

use App\Transcription\LanguageIdentifier;
use App\Translation\HttpTranslationProvider;
use App\Translation\SelfHostedTranslationProvider;
use App\Translation\TranslationException;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationResult;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Illuminate\Support\Facades\Http;

it('binds the translation provider contract to the self-hosted adapter', function () {
    $resolved = app(TranslationProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($resolved)->toBeInstanceOf(TranslationProvider::class)
        // PP-T2 §8: selection is re-evaluated on every interface resolution,
        // so the container no longer returns a cached singleton instance —
        // each resolution must still be the self-hosted adapter.
        ->and(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);
});

it('declares no behavior of its own beyond the proven self-hosted path', function () {
    $ownMethods = array_filter(
        (new ReflectionClass(SelfHostedTranslationProvider::class))->getMethods(),
        fn (ReflectionMethod $method): bool => $method->getDeclaringClass()->getName() === SelfHostedTranslationProvider::class,
    );

    expect($ownMethods)->toBeEmpty();
});

it('produces output exactly equal to the pre-T1 provider for the same response', function () {
    $envelope = [
        'contract_version' => '1.0',
        'target_language' => 'ms',
        'provider' => 'self-hosted',
        'model' => 'self-hosted-default',
        'text' => 'Hai semua Selamat datang',
        'segments' => [
            [
                'segment_index' => 0,
                'start_seconds' => 0.0,
                'end_seconds' => 4.999,
                'text' => 'Hai semua',
                'source_language' => 'en',
            ],
            [
                'segment_index' => 1,
                'start_seconds' => 4.999,
                'end_seconds' => 9.5,
                'text' => 'Selamat datang',
                'source_language' => 'en',
            ],
        ],
    ];

    Http::fake([
        'localhost:8000/translate' => Http::response($envelope, 200),
    ]);

    $factory = fn (): TranslationInvocation => TranslationInvocation::create(
        transcriptionId: 42,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::English),
        ],
    );

    $legacy = new HttpTranslationProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
        providerName: 'self-hosted',
        model: 'self-hosted-default',
        contractVersion: '1.0',
    );
    $adapter = new SelfHostedTranslationProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
        providerName: 'self-hosted',
        model: 'self-hosted-default',
        contractVersion: '1.0',
    );

    $legacyResult = $legacy->translate($factory());
    $adapterResult = $adapter->translate($factory());

    expect($adapterResult)->toBeInstanceOf(TranslationResult::class)
        ->and($adapterResult)->toEqual($legacyResult)
        ->and($adapterResult->segments)->toHaveCount(2)
        ->and($adapterResult->segments[0]->text)->toBe('Hai semua');

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8000/translate'
            && ! array_key_exists('media_reference', $request->data());
    });

    Http::assertSentCount(2);
});

it('preserves failure meaning identically to the pre-T1 provider', function () {
    Http::fake([
        'localhost:8000/translate' => Http::response([
            'error_code' => 'PROVIDER_UNAVAILABLE',
            'retryable' => false,
            'safe_message' => 'Model unavailable.',
            'request_id' => 'parity-req',
        ], 503),
    ]);

    $factory = fn (): TranslationInvocation => TranslationInvocation::create(
        transcriptionId: 42,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::English),
        ],
    );

    $legacy = new HttpTranslationProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
        providerName: 'self-hosted',
        model: 'self-hosted-default',
        contractVersion: '1.0',
    );
    $adapter = new SelfHostedTranslationProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'parity-token',
        providerName: 'self-hosted',
        model: 'self-hosted-default',
        contractVersion: '1.0',
    );

    foreach ([$legacy, $adapter] as $index => $provider) {
        try {
            $provider->translate($factory());
            $this->fail('Expected TranslationException for provider '.$index.'.');
        } catch (TranslationException $exception) {
            expect($exception->getMessage())->toBe('Model unavailable.');
        }
    }
});

it('introduces no routing or external-provider selection', function () {
    $reflection = new ReflectionClass(SelfHostedTranslationProvider::class);

    $ownProperties = array_filter(
        $reflection->getProperties(),
        fn (ReflectionProperty $property): bool => $property->getDeclaringClass()->getName() === SelfHostedTranslationProvider::class,
    );

    expect($reflection->getParentClass()->getName())->toBe(HttpTranslationProvider::class)
        ->and($ownProperties)->toBeEmpty();
});
