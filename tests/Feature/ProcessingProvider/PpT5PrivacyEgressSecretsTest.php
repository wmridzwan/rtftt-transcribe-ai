<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\ReferenceExternalChunkResponse;
use App\Transcription\ReferenceExternalTranscriptionProvider;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProviderResolver;
use App\Translation\ReferenceExternalTranslationProvider;
use App\Translation\ReferenceExternalTranslationResponse;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProviderResolver;
use App\Translation\TranslationResult;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeReferenceExternalTranslationTransport;
use Tests\Support\FakeReferenceExternalTransport;

const PP5_PE_TOKEN = 'fixture-token-pp5-pe-not-a-secret';
const PP5_PE_BASE_URL = 'http://localhost:9/reference';

function pp5peTranscriptionAdapter(FakeReferenceExternalTransport $transport): ReferenceExternalTranscriptionProvider
{
    return new ReferenceExternalTranscriptionProvider(
        baseUrl: PP5_PE_BASE_URL,
        token: PP5_PE_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    );
}

function pp5peTranslationAdapter(FakeReferenceExternalTranslationTransport $transport): ReferenceExternalTranslationProvider
{
    return new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference-translate',
        token: PP5_PE_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    );
}

function pp5peTranscriptionInvocation(int $fileSizeBytes = 4096): TranscriptionInvocation
{
    return TranscriptionInvocation::create(
        transcriptionId: 511,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/pp5-pe.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: $fileSizeBytes,
            durationSeconds: 4.0,
        ),
        requestedLanguage: LanguageIdentifier::Malay,
    );
}

function pp5peTranslationInvocation(): TranslationInvocation
{
    return TranslationInvocation::create(
        transcriptionId: 512,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::fromBcp47('en')),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::fromBcp47('en')),
        ],
        translationId: 513,
    );
}

/**
 * @return array{records: list<MessageLogged>}
 */
function pp5peCaptureLogs(callable $run): array
{
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records): void {
        $records[] = $event;
    });

    $run();

    return ['records' => $records];
}

function pp5pePayloads(array $records): string
{
    return (string) json_encode(array_map(
        static fn (MessageLogged $record): array => [
            'message' => $record->message,
            'context' => $record->context,
        ],
        $records,
    ));
}

beforeEach(function () {
    Storage::fake('local');
    config(['processing.external_kill_switch' => false]);
    config(['transcription.provider_selection' => 'self_hosted']);
    config(['translation.provider_selection' => 'self_hosted']);
    config(['transcription.timeout_seconds' => 300]);
    config(['translation.timeout_seconds' => 300]);
    config(['transcription.worker_token' => '']);
    config(['translation.worker_token' => '']);
});

it('zero-egress runtime: both reference paths run with stray HTTP prevention armed', function () {
    Http::preventStrayRequests();

    $transcriptionTransport = new FakeReferenceExternalTransport;
    $transcriptionTransport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
        ],
    );

    $translationTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
        ])
    );

    $transcriptionResult = pp5peTranscriptionAdapter($transcriptionTransport)->transcribe(pp5peTranscriptionInvocation());
    $translationResult = pp5peTranslationAdapter($translationTransport)->translate(pp5peTranslationInvocation());

    expect($transcriptionResult)->toBeInstanceOf(NormalizedTranscript::class)
        ->and($translationResult)->toBeInstanceOf(TranslationResult::class)
        ->and($transcriptionTransport->dispatchCount())->toBe(1)
        ->and($translationTransport->dispatchCount())->toBe(1);
});

it('zero-egress source audit: reference seam files use no network or secret-loading primitives', function () {
    $files = array_merge(
        glob(app_path('Transcription/ReferenceExternal*.php')) ?: [],
        glob(app_path('Translation/ReferenceExternal*.php')) ?: [],
        [app_path('Transcription/TranscriptionProviderResolver.php'), app_path('Translation/TranslationProviderResolver.php')],
    );

    expect($files)->not->toBe([]);

    $needles = ['Http::', 'curl_', 'fsockopen', 'pfsockopen', 'socket_', 'Guzzle', 'Aws\\', 'SDK', 'env(', '$_ENV', 'getenv('];
    $hits = [];

    foreach ($files as $file) {
        $code = (string) file_get_contents($file);

        foreach ($needles as $needle) {
            if (str_contains($code, $needle)) {
                $hits[] = basename($file).':'.$needle;
            }
        }
    }

    expect($hits)->toBe([]);
});

it('fixture endpoints and credentials are clearly non-sensitive', function () {
    expect(parse_url(PP5_PE_BASE_URL, PHP_URL_HOST))->toBe('localhost')
        ->and(PP5_PE_TOKEN)->toContain('fixture')
        ->and(config('transcription.worker_token'))->toBe('')
        ->and(config('translation.worker_token'))->toBe('');
});

it('AC2 transcription: resolution and adapter records join on requestId and provider_key', function () {
    $transport = new FakeReferenceExternalTransport;
    app()->bind('transcription.providers.external_reference', fn () => pp5peTranscriptionAdapter($transport));

    config(['transcription.provider_selection' => 'external_reference']);

    $invocation = pp5peTranscriptionInvocation();

    $captured = pp5peCaptureLogs(function () use ($invocation, $transport) {
        (new TranscriptionProviderResolver)->resolve($invocation->requestId);

        $transport->script[0] = ReferenceExternalChunkResponse::success(
            segments: [
                ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
            ],
        );

        pp5peTranscriptionAdapter($transport)->transcribe($invocation);
    });

    $selection = null;
    $adapterRecords = [];

    foreach ($captured['records'] as $record) {
        if ($record->message === 'Transcription provider selected.') {
            $selection = $record;
        }

        if (str_starts_with((string) $record->message, 'Reference external')) {
            $adapterRecords[] = $record;
        }
    }

    expect($selection)->not->toBeNull()
        ->and($selection->context['config_source'])->toBe('transcription.provider_selection')
        ->and($selection->context['provider_key'])->toBe('external_reference')
        ->and($selection->context['request_id'])->toBe($invocation->requestId)
        ->and($adapterRecords)->not->toBe([]);

    foreach ($adapterRecords as $record) {
        expect($record->context['request_id'])->toBe($invocation->requestId)
            ->and($record->context['domain'])->toBe('transcription')
            ->and($record->context['provider_key'])->toBe('external_reference');
    }
});

it('AC2 translation: resolution and adapter records join on requestId and provider_key', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
        ])
    );
    app()->bind('translation.providers.external_reference', fn () => pp5peTranslationAdapter($transport));

    config(['translation.provider_selection' => 'external_reference']);

    $invocation = pp5peTranslationInvocation();

    $captured = pp5peCaptureLogs(function () use ($invocation) {
        (new TranslationProviderResolver)->resolve($invocation->requestId);
    });

    // Resolve path carries the invocation requestId for correlation.
    $selection = null;

    foreach ($captured['records'] as $record) {
        if ($record->message === 'Translation provider selected.') {
            $selection = $record;
        }
    }

    expect($selection)->not->toBeNull()
        ->and($selection->context['config_source'])->toBe('translation.provider_selection')
        ->and($selection->context['provider_key'])->toBe('external_reference')
        ->and($selection->context['request_id'])->toBe($invocation->requestId);

    $runCaptured = pp5peCaptureLogs(function () use ($invocation, $transport) {
        pp5peTranslationAdapter($transport)->translate($invocation);
    });

    expect($runCaptured['records'])->not->toBe([]);

    foreach ($runCaptured['records'] as $record) {
        expect($record->context['request_id'])->toBe($invocation->requestId)
            ->and($record->context['domain'])->toBe('translation')
            ->and($record->context['provider_key'])->toBe('external_reference');
    }
});

it('AC5 missing credentials fail closed with ConfigurationError and zero dispatch in both domains', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    $translationTransport = new FakeReferenceExternalTranslationTransport;

    try {
        new ReferenceExternalTranscriptionProvider(
            baseUrl: PP5_PE_BASE_URL,
            token: '',
            providerKey: 'external_reference',
            modelPinned: 'reference-1.0',
            transport: $transcriptionTransport,
        );

        $this->fail('Expected TranscriptionException for empty transcription credentials.');
    } catch (TranscriptionException $e) {
        expect($e->failure)->toBe(TranscriptionFailure::ConfigurationError)
            ->and($e->getMessage())->not->toContain('Bearer');
    }

    try {
        new ReferenceExternalTranslationProvider(
            baseUrl: 'http://localhost:9/reference-translate',
            token: '',
            providerKey: 'external_reference',
            modelPinned: 'reference-1.0',
            transport: $translationTransport,
        );

        $this->fail('Expected TranslationException for empty translation credentials.');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ConfigurationError)
            ->and($e->getMessage())->not->toContain('Bearer');
    }

    expect($transcriptionTransport->dispatchCount())->toBe(0)
        ->and($translationTransport->dispatchCount())->toBe(0);
});

it('secrets never appear in captured logs or persisted results for either domain', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    $transcriptionTransport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
        ],
    );

    $translationTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
        ])
    );

    $captured = pp5peCaptureLogs(function () use ($transcriptionTransport, $translationTransport, &$transcriptionResult, &$translationResult) {
        $transcriptionResult = pp5peTranscriptionAdapter($transcriptionTransport)->transcribe(pp5peTranscriptionInvocation());
        $translationResult = pp5peTranslationAdapter($translationTransport)->translate(pp5peTranslationInvocation());
    });

    $payloads = pp5pePayloads($captured['records']);

    expect($captured['records'])->not->toBe([])
        ->and($payloads)->not->toContain(PP5_PE_TOKEN)
        ->and($payloads)->not->toContain('Bearer ')
        ->and((string) json_encode($transcriptionResult))->not->toContain(PP5_PE_TOKEN)
        ->and((string) json_encode($translationResult))->not->toContain(PP5_PE_TOKEN);
});
