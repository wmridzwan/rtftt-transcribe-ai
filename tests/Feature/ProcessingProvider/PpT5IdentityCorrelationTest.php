<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\ReferenceExternalChunkResponse;
use App\Transcription\ReferenceExternalTranscriptionProvider;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProviderResolver;
use App\Translation\ReferenceExternalTranslationProvider;
use App\Translation\ReferenceExternalTranslationResponse;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProviderResolver;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeReferenceExternalTranslationTransport;
use Tests\Support\FakeReferenceExternalTransport;

const PP5_IC_TOKEN = 'fixture-token-pp5-ic-not-a-secret';

function pp5icTranscriptionInvocation(): TranscriptionInvocation
{
    return TranscriptionInvocation::create(
        transcriptionId: 531,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/pp5-ic.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 4096,
            durationSeconds: 4.0,
        ),
        requestedLanguage: LanguageIdentifier::Malay,
    );
}

function pp5icTranslationInvocation(): TranslationInvocation
{
    return TranslationInvocation::create(
        transcriptionId: 532,
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::fromBcp47('en')),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::fromBcp47('en')),
        ],
        translationId: 533,
    );
}

/**
 * @return array{records: list<MessageLogged>}
 */
function pp5icCaptureLogs(callable $run): array
{
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records): void {
        $records[] = $event;
    });

    $run();

    return ['records' => $records];
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

it('AC1 transcription: one invocation requestId flows through outbound request and every log record', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
        ],
    );

    $adapter = new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP5_IC_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    );

    $invocation = pp5icTranscriptionInvocation();

    $captured = pp5icCaptureLogs(function () use ($adapter, $invocation) {
        $adapter->transcribe($invocation);
    });

    expect($transport->requests[0]->requestId)->toBe($invocation->requestId)
        ->and($captured['records'])->not->toBe([]);

    $requestIds = [];

    foreach ($captured['records'] as $record) {
        expect($record->context)->toHaveKey('request_id');

        $requestIds[] = $record->context['request_id'];
    }

    // No second logical identity is minted anywhere in the run.
    expect(array_values(array_unique($requestIds)))->toBe([$invocation->requestId]);
});

it('AC1 translation: one invocation requestId flows through outbound request and every log record', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
        ])
    );

    $adapter = new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference-translate',
        token: PP5_IC_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    );

    $invocation = pp5icTranslationInvocation();

    $captured = pp5icCaptureLogs(function () use ($adapter, $invocation) {
        $adapter->translate($invocation);
    });

    expect($transport->requests[0]->requestId)->toBe($invocation->requestId)
        ->and($captured['records'])->not->toBe([]);

    $requestIds = [];

    foreach ($captured['records'] as $record) {
        expect($record->context)->toHaveKey('request_id');

        $requestIds[] = $record->context['request_id'];
    }

    expect(array_values(array_unique($requestIds)))->toBe([$invocation->requestId]);
});

it('resolver requestId is correlation-only: distinct ids never change selection', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    app()->bind('transcription.providers.external_reference', fn () => new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP5_IC_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transcriptionTransport,
    ));

    config(['transcription.provider_selection' => 'external_reference']);

    $captured = pp5icCaptureLogs(function () use (&$first, &$second) {
        $first = (new TranscriptionProviderResolver)->resolve('pp5-correlation-a');
        $second = (new TranscriptionProviderResolver)->resolve('pp5-correlation-b');
    });

    expect($first)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class)
        ->and($second)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class);

    $carried = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Transcription provider selected.',
    ));

    expect($carried)->toHaveCount(2)
        ->and($carried[0]->context['request_id'])->toBe('pp5-correlation-a')
        ->and($carried[1]->context['request_id'])->toBe('pp5-correlation-b');
});

it('no http_request_id is minted anywhere in PP-T5 reference paths', function () {
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
    app()->bind('translation.providers.external_reference', fn () => new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference-translate',
        token: PP5_IC_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $translationTransport,
    ));

    config(['translation.provider_selection' => 'external_reference']);

    $captured = pp5icCaptureLogs(function () use ($transcriptionTransport, $translationTransport) {
        (new TranscriptionProviderResolver)->resolve('pp5-no-http-1');
        (new TranslationProviderResolver)->resolve('pp5-no-http-2');

        (new ReferenceExternalTranscriptionProvider(
            baseUrl: 'http://localhost:9/reference',
            token: PP5_IC_TOKEN,
            providerKey: 'external_reference',
            modelPinned: 'reference-1.0',
            transport: $transcriptionTransport,
        ))->transcribe(pp5icTranscriptionInvocation());

        (new ReferenceExternalTranslationProvider(
            baseUrl: 'http://localhost:9/reference-translate',
            token: PP5_IC_TOKEN,
            providerKey: 'external_reference',
            modelPinned: 'reference-1.0',
            transport: $translationTransport,
        ))->translate(pp5icTranslationInvocation());
    });

    expect($captured['records'])->not->toBe([]);

    foreach ($captured['records'] as $record) {
        expect($record->context)->not->toHaveKey('http_request_id');
    }
});

it('chunk identity stays execution-only: no chunk ids leak into normalized results', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
        ],
    );

    $adapter = new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP5_IC_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
    );

    $captured = pp5icCaptureLogs(function () use ($adapter, &$result) {
        $result = $adapter->transcribe(pp5icTranscriptionInvocation());
    });

    $chunkRecords = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => array_key_exists('chunk_id', $record->context),
    ));

    expect($chunkRecords)->not->toBe([])
        ->and((string) json_encode($result))->not->toContain('chk_')
        ->and(array_map(static fn ($segment): int => $segment->segmentIndex, $result->segments))->toBe([0]);
});
