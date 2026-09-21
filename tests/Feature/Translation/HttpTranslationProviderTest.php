<?php

use App\Transcription\LanguageIdentifier;
use App\Translation\HttpTranslationProvider;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function translationInvocationFixture(): TranslationInvocation
{
    return TranslationInvocation::create(
        transcriptionId: 42,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::English),
        ],
    );
}

function successfulTranslationResponse(): array
{
    return [
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
}

function provider(): HttpTranslationProvider
{
    return new HttpTranslationProvider(
        workerBaseUrl: 'http://localhost:8000',
        bearerToken: 'translation-token',
        providerName: 'self-hosted',
        model: 'self-hosted-default',
        contractVersion: '1.0',
    );
}

it('sends a text-only segment-aligned payload', function () {
    Http::fake([
        'localhost:8000/translate' => Http::response(successfulTranslationResponse(), 200),
    ]);

    $result = provider()->translate(translationInvocationFixture());

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'http://localhost:8000/translate'
            && $request->header('Authorization') === ['Bearer translation-token']
            && $data['transcription_id'] === 42
            && $data['target_language'] === 'ms'
            && $data['segments'][0]['text'] === 'Hello'
            && $data['segments'][0]['source_language'] === 'en'
            && ! array_key_exists('media_reference', $data);
    });

    expect($result->targetLanguage)->toBe(TranslationTarget::Malay)
        ->and($result->segments)->toHaveCount(2)
        ->and($result->segments[0]->text)->toBe('Hai semua')
        ->and($result->segments[0]->segmentIndex)->toBe(0)
        ->and($result->segments[0]->endSeconds)->toBe(4.999);
});

it('maps a worker error envelope to the authoritative failure taxonomy', function () {
    Http::fake([
        'localhost:8000/translate' => Http::response([
            'error_code' => 'PROVIDER_UNAVAILABLE',
            'retryable' => false,
            'safe_message' => 'Model unavailable.',
            'request_id' => 'req',
        ], 503),
    ]);

    try {
        provider()->translate(translationInvocationFixture());
        $this->fail('Expected TranslationException.');
    } catch (TranslationException $exception) {
        expect($exception->failure)->toBe(TranslationFailure::ProviderUnavailable)
            ->and($exception->getMessage())->toBe('Model unavailable.');
    }
});

it('throws ProviderUnavailable on a non-2xx without an error envelope', function () {
    Http::fake([
        'localhost:8000/translate' => Http::response([], 502),
    ]);

    expect(fn () => provider()->translate(translationInvocationFixture()))
        ->toThrow(TranslationException::class);
});

it('rejects a response whose target does not match the request', function () {
    $body = successfulTranslationResponse();
    $body['target_language'] = 'en';

    Http::fake([
        'localhost:8000/translate' => Http::response($body, 200),
    ]);

    expect(fn () => provider()->translate(translationInvocationFixture()))
        ->toThrow(TranslationException::class);
});

it('rejects a non-object response', function () {
    Http::fake([
        'localhost:8000/translate' => Http::response('not-json', 200, ['Content-Type' => 'text/plain']),
    ]);

    expect(fn () => provider()->translate(translationInvocationFixture()))
        ->toThrow(TranslationException::class);
});

it('resolves the translation provider bound in the container', function () {
    expect(app(TranslationProvider::class))->toBeInstanceOf(HttpTranslationProvider::class);
});

it('rejects an empty or partial provider response', function () {
    $body = successfulTranslationResponse();
    $body['segments'] = [];

    Http::fake(['localhost:8000/translate' => Http::response($body, 200)]);

    expect(fn () => provider()->translate(translationInvocationFixture()))->toThrow(TranslationException::class);

    $body = successfulTranslationResponse();
    $body['segments'] = [$body['segments'][0]];

    Http::fake(['localhost:8000/translate' => Http::response($body, 200)]);

    expect(fn () => provider()->translate(translationInvocationFixture()))->toThrow(TranslationException::class);
});

it('rejects a foreign segment index', function () {
    $body = successfulTranslationResponse();
    $body['segments'][0]['segment_index'] = 99;

    Http::fake(['localhost:8000/translate' => Http::response($body, 200)]);

    expect(fn () => provider()->translate(translationInvocationFixture()))->toThrow(TranslationException::class);
});

it('rejects an array text without a raw warning', function () {
    $body = successfulTranslationResponse();
    $body['segments'][0]['text'] = ['a'];

    Http::fake(['localhost:8000/translate' => Http::response($body, 200)]);

    expect(fn () => provider()->translate(translationInvocationFixture()))->toThrow(TranslationException::class);
});

it('maps a 401 without an envelope to a non-retryable configuration failure', function () {
    Http::fake(['localhost:8000/translate' => Http::response(['detail' => 'Unauthorized'], 401)]);

    try {
        provider()->translate(translationInvocationFixture());
        $this->fail('Expected TranslationException.');
    } catch (TranslationException $exception) {
        expect($exception->failure)->toBe(TranslationFailure::ConfigurationError);
    }
});

it('maps a transport timeout to ProviderTimeout', function () {
    Http::fake(function () {
        throw new ConnectionException('cURL error 28: Operation timed out');
    });

    try {
        provider()->translate(translationInvocationFixture());
        $this->fail('Expected TranslationException.');
    } catch (TranslationException $exception) {
        expect($exception->failure)->toBe(TranslationFailure::ProviderTimeout);
    }
});
