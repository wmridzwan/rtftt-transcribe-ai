<?php

use App\Jobs\ProcessTranslation;
use App\Models\Translation;
use App\Transcription\LanguageIdentifier;
use App\Translation\ProviderResolutionException;
use App\Translation\SelfHostedTranslationProvider;
use App\Translation\TranslationException;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationProviderResolver;
use App\Translation\TranslationResult;
use App\Translation\TranslationResultWriter;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Support\RecordingTranslationProvider;

beforeEach(function () {
    // .env points workers at 127.0.0.1; pin loopback localhost so the
    // Http::fake() patterns below intercept deterministically.
    config(['translation.worker_url' => 'http://localhost:8000']);
});

function pp2TranslationInvocation(): TranslationInvocation
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

function pp2TranslationSuccessEnvelope(): array
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

it('AC1: selects self-hosted under default configuration', function () {
    $resolved = app(TranslationProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($resolved)->toBeInstanceOf(TranslationProvider::class);
});

it('evaluates selection on every resolution without caching', function () {
    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);

    config(['processing.external_kill_switch' => true]);
    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);

    config(['processing.external_kill_switch' => false]);
    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);
});

it('AC5: rejects any non-self_hosted translation value', function () {
    config(['translation.provider_selection' => 'external_x']);

    expect(fn () => app(TranslationProvider::class))
        ->toThrow(ProviderResolutionException::class, 'Wave 1 supports self_hosted only');
});

it('AC5: rejects non-self_hosted even when a binding exists', function () {
    app()->bind('translation.providers.external_x', fn () => new stdClass);
    config(['translation.provider_selection' => 'external_x']);

    expect(fn () => app(TranslationProvider::class))
        ->toThrow(ProviderResolutionException::class, 'Wave 1 supports self_hosted only');
});

it('AC3: boot validation rejects a non-self_hosted value', function () {
    config(['translation.provider_selection' => 'external_x']);

    expect(fn () => TranslationProviderResolver::validateSelection())
        ->toThrow(ProviderResolutionException::class, 'Wave 1 supports self_hosted only');
});

it('AC6: enabled kill switch keeps self-hosted after refresh', function () {
    config(['processing.external_kill_switch' => true]);
    $this->artisan('config:clear');

    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);
});

it('AC6: invalid kill-switch value fails closed with a warning', function () {
    config(['processing.external_kill_switch' => 'maybe']);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);

    $found = false;
    foreach ($records as $record) {
        if ($record->level === 'warning'
            && str_contains($record->message, 'kill-switch')
            && ($record->context['configured_value'] ?? null) === 'maybe'
        ) {
            $found = true;
        }
    }
    expect($found)->toBeTrue();
});

it('AC7: logs provider identity without secrets', function () {
    config(['translation.worker_token' => 'pp2-secret-token-probe']);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    expect(app(TranslationProvider::class))->toBeInstanceOf(SelfHostedTranslationProvider::class);

    $found = false;
    foreach ($records as $record) {
        $context = $record->context;
        if (($context['provider_key'] ?? null) === 'self_hosted'
            && ($context['model_pinned'] ?? null) === config('translation.model')
            && ($context['config_source'] ?? null) === 'translation.provider_selection'
        ) {
            $found = true;
        }

        $haystack = json_encode([$record->message, $context]);
        expect($haystack)->not->toContain('pp2-secret-token-probe');
    }

    expect($found)->toBeTrue();
});

it('AC8: self-hosted failure never triggers an external call', function () {
    Http::fake([
        'localhost:8000/translate' => Http::response([
            'error_code' => 'PROVIDER_UNAVAILABLE',
            'retryable' => false,
            'safe_message' => 'Model unavailable.',
            'request_id' => 'pp2-ac8',
        ], 503),
    ]);

    $provider = app(TranslationProvider::class);

    expect(fn () => $provider->translate(pp2TranslationInvocation()))
        ->toThrow(TranslationException::class, 'Model unavailable.');
});

it('preserves the existing invocation requestId end to end', function () {
    Http::fake([
        'localhost:8000/translate' => Http::response(pp2TranslationSuccessEnvelope(), 200),
    ]);

    $invocation = pp2TranslationInvocation();
    $provider = app(TranslationProvider::class);
    $result = $provider->translate($invocation);

    expect($result)->toBeInstanceOf(TranslationResult::class);

    Http::assertSent(function ($request) use ($invocation) {
        return $request->url() === 'http://localhost:8000/translate'
            && $request->data()['request_id'] === $invocation->requestId
            && ! array_key_exists('media_reference', $request->data());
    });
});

it('keeps selection independent of the identity label', function () {
    config(['translation.provider' => 'custom-label']);

    $resolved = app(TranslationProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranslationProvider::class);
});

it('carries a caller-provided requestId into resolution records without affecting selection', function () {
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    $resolver = new TranslationProviderResolver;
    $withId = $resolver->resolve('req-456');
    $withoutId = $resolver->resolve();

    expect($withId)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($withoutId)->toBeInstanceOf(SelfHostedTranslationProvider::class);

    $withFound = $withoutFound = false;
    foreach ($records as $record) {
        if (($record->context['provider_key'] ?? null) !== 'self_hosted') {
            continue;
        }
        if (($record->context['request_id'] ?? null) === 'req-456') {
            $withFound = true;
        }
        if (! array_key_exists('request_id', $record->context) || $record->context['request_id'] === null) {
            $withoutFound = true;
        }
    }

    expect($withFound)->toBeTrue()
        ->and($withoutFound)->toBeTrue();
});

it('logs selection rejections without secrets', function () {
    config(['translation.worker_token' => 'pp2-secret-token-probe']);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    foreach (['external_x', 'bogus'] as $value) {
        config(['translation.provider_selection' => $value]);

        try {
            app(TranslationProvider::class);
            $this->fail("Expected ProviderResolutionException for [{$value}].");
        } catch (ProviderResolutionException) {
        }
    }

    try {
        TranslationProviderResolver::validateSelection();
        $this->fail('Expected ProviderResolutionException from boot validation.');
    } catch (ProviderResolutionException) {
    }

    $warnings = array_filter(
        $records,
        fn (MessageLogged $record): bool => $record->level === 'warning',
    );

    expect($warnings)->toHaveCount(3);

    foreach ($records as $record) {
        $haystack = json_encode([$record->message, $record->context]);
        expect($haystack)->not->toContain('pp2-secret-token-probe');
    }
});

it('joins request identity with the selected provider in job logs', function () {
    $transcription = translationSource();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
    ]);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    (new ProcessTranslation($translation->getKey(), $translation->transcription_id, $translation->attempt_token))
        ->handle(new RecordingTranslationProvider(alignedTranslationResult()), app(TranslationResultWriter::class));

    $found = false;
    foreach ($records as $record) {
        if ($record->message === 'Translation provider invocation started.'
            && is_string($record->context['request_id'] ?? null)
            && ($record->context['provider_class'] ?? null) === RecordingTranslationProvider::class
        ) {
            $found = true;
        }
    }

    expect($found)->toBeTrue();
});
