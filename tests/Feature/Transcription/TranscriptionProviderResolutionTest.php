<?php

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\ProviderResolutionException;
use App\Transcription\SelfHostedTranscriptionProvider;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProvider;
use App\Transcription\TranscriptionProviderResolver;
use App\Transcription\WorkerContract;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RecordingTranscriptionProvider;
use Tests\Support\TranscriptionFixtures;

final class Pp2FakeExternalTranscriptionProvider implements TranscriptionProvider
{
    public static int $resolutions = 0;

    public function __construct()
    {
        self::$resolutions++;
    }

    public function transcribe(TranscriptionInvocation $invocation): NormalizedTranscript
    {
        return NormalizedTranscript::noSpeech(5.0);
    }
}

function pp2ResolutionInvocation(): TranscriptionInvocation
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

function pp2TranscriptionSuccessEnvelope(): array
{
    return [
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
}

function pp2LoggedContextsContainNoSecret(array $records, string $secret): bool
{
    foreach ($records as $record) {
        $haystack = $record instanceof MessageLogged
            ? json_encode([$record->message, $record->context])
            : json_encode($record);
        if (is_string($haystack) && str_contains($haystack, $secret)) {
            return false;
        }
    }

    return true;
}

beforeEach(function () {
    Pp2FakeExternalTranscriptionProvider::$resolutions = 0;
    Storage::fake('local');
    // .env points workers at 127.0.0.1; pin loopback localhost so the
    // Http::fake() patterns below intercept deterministically.
    config(['transcription.worker_url' => 'http://localhost:8000']);
});

it('AC1: selects self-hosted under default configuration', function () {
    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($resolved)->toBeInstanceOf(TranscriptionProvider::class);
});

it('evaluates selection on every resolution without caching', function () {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);

    config(['transcription.provider_selection' => 'external_acme']);
    expect(app(TranscriptionProvider::class))->toBeInstanceOf(Pp2FakeExternalTranscriptionProvider::class);

    config(['transcription.provider_selection' => 'self_hosted']);
    expect(app(TranscriptionProvider::class))->toBeInstanceOf(SelfHostedTranscriptionProvider::class);
});

it('AC2: selects a named external binding when configured', function () {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);
    config(['transcription.provider_selection' => 'external_acme']);

    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(Pp2FakeExternalTranscriptionProvider::class);

    Http::fake();
    $result = $resolved->transcribe(pp2ResolutionInvocation());

    expect($result)->toBeInstanceOf(NormalizedTranscript::class);
    Http::assertNothingSent();
});

it('AC3: rejects an invalid selection value with a named error', function () {
    config(['transcription.provider_selection' => 'bogus']);

    expect(fn () => app(TranscriptionProvider::class))
        ->toThrow(ProviderResolutionException::class, 'Unknown transcription provider selection [bogus]');
});

it('AC3: boot validation rejects an invalid selection value', function () {
    config(['transcription.provider_selection' => 'bogus']);

    expect(fn () => TranscriptionProviderResolver::validateSelection())
        ->toThrow(ProviderResolutionException::class, 'Invalid transcription provider selection [bogus]');
});

it('rejects a well-formed external name with no container binding', function () {
    config(['transcription.provider_selection' => 'external_ghost']);

    expect(fn () => app(TranscriptionProvider::class))
        ->toThrow(ProviderResolutionException::class, 'has no container binding');
});

it('rejects an external binding that does not implement the contract', function () {
    app()->bind('transcription.providers.external_wrong', fn () => new stdClass);
    config(['transcription.provider_selection' => 'external_wrong']);

    expect(fn () => app(TranscriptionProvider::class))
        ->toThrow(ProviderResolutionException::class, 'does not implement TranscriptionProvider');
});

it('AC4: missing external credentials fail closed without fallback', function () {
    app()->bind('transcription.providers.external_acme', function () {
        if (config('test.pp2_external_acme_token') === null) {
            throw new RuntimeException('Missing external credentials for [external_acme].');
        }

        return new Pp2FakeExternalTranscriptionProvider;
    });
    config(['transcription.provider_selection' => 'external_acme']);

    expect(fn () => app(TranscriptionProvider::class))
        ->toThrow(RuntimeException::class, 'Missing external credentials');
});

it('AC6: disabled kill switch honors selection', function () {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);
    config([
        'transcription.provider_selection' => 'external_acme',
        'processing.external_kill_switch' => false,
    ]);

    expect(app(TranscriptionProvider::class))->toBeInstanceOf(Pp2FakeExternalTranscriptionProvider::class);
});

it('AC6: enabled kill switch forces self-hosted after refresh', function () {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);
    config([
        'transcription.provider_selection' => 'external_acme',
        'processing.external_kill_switch' => true,
    ]);
    $this->artisan('config:clear');

    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class);
});

it('AC6: engaged kill-switch values force self-hosted', function (mixed $value) {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);
    config([
        'transcription.provider_selection' => 'external_acme',
        'processing.external_kill_switch' => $value,
    ]);

    expect(app(TranscriptionProvider::class))->toBeInstanceOf(SelfHostedTranscriptionProvider::class);
})->with([true, 1, '1', 'true']);

it('AC6: disengaged kill-switch values honor selection', function (mixed $value) {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);
    config([
        'transcription.provider_selection' => 'external_acme',
        'processing.external_kill_switch' => $value,
    ]);

    expect(app(TranscriptionProvider::class))->toBeInstanceOf(Pp2FakeExternalTranscriptionProvider::class);
})->with([false, 0, '0', 'false', null]);

it('AC6: invalid kill-switch value fails closed with a warning and no boot crash', function () {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);
    config([
        'transcription.provider_selection' => 'external_acme',
        'processing.external_kill_switch' => 'maybe',
    ]);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class);

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
    config(['transcription.worker_token' => 'pp2-secret-token-probe']);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    $resolved = app(TranscriptionProvider::class);

    expect($resolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class);

    $found = false;
    foreach ($records as $record) {
        $context = $record->context;
        if (($context['provider_key'] ?? null) === 'self_hosted'
            && ($context['model_pinned'] ?? null) === config('transcription.model')
            && ($context['config_source'] ?? null) === 'transcription.provider_selection'
        ) {
            $found = true;
        }
    }

    expect($found)->toBeTrue()
        ->and(pp2LoggedContextsContainNoSecret($records, 'pp2-secret-token-probe'))->toBeTrue();
});

it('AC8: self-hosted failure never triggers an external call', function () {
    app()->bind('transcription.providers.external_acme', fn () => new Pp2FakeExternalTranscriptionProvider);

    Http::fake([
        'localhost:8000/transcribe' => Http::response([
            'error_code' => 'TIMEOUT',
            'retryable' => true,
            'safe_message' => 'Worker timeout.',
            'request_id' => 'pp2-ac8',
        ], 500),
    ]);

    $provider = app(TranscriptionProvider::class);

    expect(fn () => $provider->transcribe(pp2ResolutionInvocation()))
        ->toThrow(TranscriptionException::class, 'Worker timeout.')
        ->and(Pp2FakeExternalTranscriptionProvider::$resolutions)->toBe(0);
});

it('preserves the existing invocation requestId end to end', function () {
    Http::fake([
        'localhost:8000/transcribe' => Http::response(pp2TranscriptionSuccessEnvelope(), 200),
    ]);

    $invocation = pp2ResolutionInvocation();
    $original = $invocation->requestId;

    expect($original)->toMatch('/^[0-9a-f-]{36}$/');

    app(TranscriptionProvider::class)->transcribe($invocation);

    // The resolver/provider path must not mint, replace, or unify the
    // factory-minted identity: still present and unchanged afterwards,
    // and distinct across invocations.
    expect($invocation->requestId)->toBe($original);

    $other = pp2ResolutionInvocation();

    expect($other->requestId)->not->toBe($original);
});

it('does not conflate selection with the identity label namespace', function () {
    config(['translation.provider' => 'custom-label']);

    expect(app(TranscriptionProvider::class))->toBeInstanceOf(SelfHostedTranscriptionProvider::class);
});

it('carries a caller-provided requestId into resolution records without affecting selection', function () {
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    $resolver = new TranscriptionProviderResolver;
    $withId = $resolver->resolve('req-123');
    $withoutId = $resolver->resolve();

    expect($withId)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($withoutId)->toBeInstanceOf(SelfHostedTranscriptionProvider::class);

    $withFound = $withoutFound = false;
    foreach ($records as $record) {
        if (($record->context['provider_key'] ?? null) !== 'self_hosted') {
            continue;
        }
        if (($record->context['request_id'] ?? null) === 'req-123') {
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
    config(['transcription.worker_token' => 'pp2-secret-token-probe']);
    app()->bind('transcription.providers.external_wrong', fn () => new stdClass);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    foreach (['bogus', 'external_ghost', 'external_wrong'] as $value) {
        config(['transcription.provider_selection' => $value]);

        try {
            app(TranscriptionProvider::class);
            $this->fail("Expected ProviderResolutionException for [{$value}].");
        } catch (ProviderResolutionException) {
        }
    }

    $warnings = array_filter(
        $records,
        fn (MessageLogged $record): bool => $record->level === 'warning',
    );

    expect($warnings)->toHaveCount(3);

    foreach ($warnings as $warning) {
        expect($warning->context['configured_value'] ?? null)->toBeString();
    }

    expect(pp2LoggedContextsContainNoSecret($records, 'pp2-secret-token-probe'))->toBeTrue();
});

it('logs boot-validation rejections without secrets', function () {
    config([
        'transcription.provider_selection' => 'bogus',
        'transcription.worker_token' => 'pp2-secret-token-probe',
    ]);

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    expect(fn () => TranscriptionProviderResolver::validateSelection())
        ->toThrow(ProviderResolutionException::class, 'Invalid transcription provider selection [bogus]');

    $found = false;
    foreach ($records as $record) {
        if ($record->level === 'warning' && ($record->context['configured_value'] ?? null) === 'bogus') {
            $found = true;
        }
    }

    expect($found)->toBeTrue()
        ->and(pp2LoggedContextsContainNoSecret($records, 'pp2-secret-token-probe'))->toBeTrue();
});

it('joins request identity with the selected provider in job logs', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records) {
        $records[] = $event;
    });

    (new ProcessTranscription($transcription->getKey(), $attempt->getKey()))
        ->handle(new RecordingTranscriptionProvider(NormalizedTranscript::noSpeech(5.0)), app(TranscriptionResultWriter::class));

    $found = false;
    foreach ($records as $record) {
        if ($record->message === 'Transcription provider invocation started.'
            && is_string($record->context['request_id'] ?? null)
            && ($record->context['provider_class'] ?? null) === RecordingTranscriptionProvider::class
        ) {
            $found = true;
        }
    }

    expect($found)->toBeTrue();
});
