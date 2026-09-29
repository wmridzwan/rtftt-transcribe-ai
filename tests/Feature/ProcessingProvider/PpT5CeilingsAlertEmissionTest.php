<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\ProviderResolutionException;
use App\Transcription\ReferenceExternalChunkFailureKind;
use App\Transcription\ReferenceExternalChunkResponse;
use App\Transcription\ReferenceExternalTranscriptionProvider;
use App\Transcription\SelfHostedTranscriptionProvider;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use App\Transcription\TranscriptionProviderResolver;
use App\Translation\ReferenceExternalTranslationFailureKind;
use App\Translation\ReferenceExternalTranslationProvider;
use App\Translation\ReferenceExternalTranslationResponse;
use App\Translation\SelfHostedTranslationProvider;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProviderResolver;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationTarget;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeReferenceExternalTranslationTransport;
use Tests\Support\FakeReferenceExternalTransport;

const PP5_CA_TOKEN = 'fixture-token-pp5-ca-not-a-secret';

/**
 * Test-side alert-condition keys (PP-T5 reconciled §11/H-2): each key names
 * an observable fail-closed record emitted by existing PP-T2/PP-T3/PP-T4
 * behavior. No production alert field is introduced.
 */
const PP5_CA_CONDITIONS = [
    'invalid_selection',
    'missing_credentials',
    'forbidden_external',
    'ceiling_breach',
    'saturation_timeout',
];

function pp5caTranscriptionAdapter(
    FakeReferenceExternalTransport $transport,
    ?int $timeoutSeconds = null,
    int $maxRequestBytes = ReferenceExternalTranscriptionProvider::PRODUCT_LIMIT_BYTES,
): ReferenceExternalTranscriptionProvider {
    return new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP5_CA_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
        timeoutSeconds: $timeoutSeconds,
        maxRequestBytes: $maxRequestBytes,
    );
}

function pp5caTranslationAdapter(
    FakeReferenceExternalTranslationTransport $transport,
    ?int $timeoutSeconds = null,
    int $maxSegments = ReferenceExternalTranslationProvider::DEFAULT_MAX_SEGMENTS,
    int $maxPayloadChars = ReferenceExternalTranslationProvider::DEFAULT_MAX_PAYLOAD_CHARS,
): ReferenceExternalTranslationProvider {
    return new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference-translate',
        token: PP5_CA_TOKEN,
        providerKey: 'external_reference',
        modelPinned: 'reference-1.0',
        transport: $transport,
        timeoutSeconds: $timeoutSeconds,
        maxSegments: $maxSegments,
        maxPayloadChars: $maxPayloadChars,
    );
}

function pp5caTranscriptionInvocation(int $fileSizeBytes = 4096): TranscriptionInvocation
{
    return TranscriptionInvocation::create(
        transcriptionId: 521,
        processingAttemptId: 1,
        media: new TranscriptionMedia(
            storageKey: 'media/pp5-ca.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: $fileSizeBytes,
            durationSeconds: 4.0,
        ),
        requestedLanguage: LanguageIdentifier::Malay,
    );
}

function pp5caTranslationInvocation(?array $segments = null): TranslationInvocation
{
    return TranslationInvocation::create(
        transcriptionId: 522,
        targetLanguage: TranslationTarget::Malay,
        segments: $segments ?? [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::fromBcp47('en')),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::fromBcp47('en')),
        ],
        translationId: 523,
    );
}

function pp5caAlignedShapes(): array
{
    return [
        ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
        ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
    ];
}

/**
 * @return array{records: list<MessageLogged>}
 */
function pp5caCaptureLogs(callable $run): array
{
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records): void {
        $records[] = $event;
    });

    $run();

    return ['records' => $records];
}

function pp5caPayloads(array $records): string
{
    return (string) json_encode(array_map(
        static fn (MessageLogged $record): array => [
            'message' => $record->message,
            'level' => $record->level,
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

it('AC7 contract traceability: the five alert conditions match the reconciled set exactly', function () {
    expect(PP5_CA_CONDITIONS)->toBe([
        'invalid_selection',
        'missing_credentials',
        'forbidden_external',
        'ceiling_breach',
        'saturation_timeout',
    ]);
});

it('AC6 transcription size ceiling: boundary accepted, boundary+1 rejected with MediaRejected', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
        ],
    );

    $adapter = pp5caTranscriptionAdapter($transport, maxRequestBytes: 1024);

    // Boundary accepted.
    $adapter->transcribe(pp5caTranscriptionInvocation(fileSizeBytes: 1024));

    expect($transport->dispatchCount())->toBe(1);

    // Boundary+1 rejected.
    $captured = pp5caCaptureLogs(function () use ($adapter) {
        try {
            $adapter->transcribe(pp5caTranscriptionInvocation(fileSizeBytes: 1025));

            $this->fail('Expected MediaRejected for oversized transcription request.');
        } catch (TranscriptionException $e) {
            expect($e->failure)->toBe(TranscriptionFailure::MediaRejected)
                ->and($e->failure->isRetryable())->toBeFalse();
        }
    });

    expect($transport->dispatchCount())->toBe(1);

    $ceiling = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external transcription request exceeds provider ceiling.',
    ));

    expect($ceiling)->toHaveCount(1)
        ->and($ceiling[0]->level)->toBe('warning')
        ->and($ceiling[0]->context['failure_category'])->toBe(TranscriptionFailure::MediaRejected->value)
        ->and($ceiling[0]->context['file_size_bytes'])->toBe(1025)
        ->and($ceiling[0]->context['max_request_bytes'])->toBe(1024)
        ->and($ceiling[0]->context['request_id'])->not->toBeEmpty();
});

it('AC6 transcription timeout ceiling: 300s honored, extension refused at construction', function () {
    $transport = new FakeReferenceExternalTransport;

    $adapter = pp5caTranscriptionAdapter($transport, timeoutSeconds: 300);

    expect($adapter)->toBeInstanceOf(ReferenceExternalTranscriptionProvider::class);

    try {
        pp5caTranscriptionAdapter($transport, timeoutSeconds: 301);

        $this->fail('Expected ConfigurationError for timeout above the provider ceiling.');
    } catch (TranscriptionException $e) {
        expect($e->failure)->toBe(TranscriptionFailure::ConfigurationError);
    }

    expect($transport->dispatchCount())->toBe(0);
});

it('AC6 translation ceilings: exact boundaries accepted, exceed rejected with InvalidRequest', function () {
    // Segment-count boundary: exactly 2 of max 2 accepted.
    $okTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp5caAlignedShapes())
    );

    pp5caTranslationAdapter($okTransport, maxSegments: 2)->translate(pp5caTranslationInvocation());

    expect($okTransport->dispatchCount())->toBe(1);

    // Segment-count exceed: 3 segments of max 2 rejected.
    $rejectTransport = new FakeReferenceExternalTranslationTransport;
    $three = [
        new TranslationSegmentData(0, 0.0, 2.0, 'One', LanguageIdentifier::fromBcp47('en')),
        new TranslationSegmentData(1, 2.0, 4.0, 'Two', LanguageIdentifier::fromBcp47('en')),
        new TranslationSegmentData(2, 4.0, 6.0, 'Three', LanguageIdentifier::fromBcp47('en')),
    ];

    $captured = pp5caCaptureLogs(function () use ($rejectTransport, $three) {
        try {
            pp5caTranslationAdapter($rejectTransport, maxSegments: 2)
                ->translate(pp5caTranslationInvocation($three));

            $this->fail('Expected InvalidRequest for oversized translation request.');
        } catch (TranslationException $e) {
            expect($e->failure)->toBe(TranslationFailure::InvalidRequest)
                ->and($e->failure->isRetryable())->toBeFalse();
        }
    });

    expect($rejectTransport->dispatchCount())->toBe(0);

    $ceiling = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external translation request exceeds provider ceiling.',
    ));

    expect($ceiling)->toHaveCount(1)
        ->and($ceiling[0]->level)->toBe('warning')
        ->and($ceiling[0]->context['failure_category'])->toBe(TranslationFailure::InvalidRequest->value)
        ->and($ceiling[0]->context['segment_count'])->toBe(3)
        ->and($ceiling[0]->context['max_segments'])->toBe(2);

    // Payload-char boundary: 'Hello'(5) + 'Welcome'(7) = 12 chars accepted at 12, rejected at 11.
    $charsTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp5caAlignedShapes())
    );

    pp5caTranslationAdapter($charsTransport, maxPayloadChars: 12)->translate(pp5caTranslationInvocation());

    expect($charsTransport->dispatchCount())->toBe(1);

    $charsReject = new FakeReferenceExternalTranslationTransport;

    try {
        pp5caTranslationAdapter($charsReject, maxPayloadChars: 11)->translate(pp5caTranslationInvocation());

        $this->fail('Expected InvalidRequest for over-char translation request.');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::InvalidRequest);
    }

    expect($charsReject->dispatchCount())->toBe(0);
});

it('AC6 translation timeout ceiling: 300s honored, extension refused at construction', function () {
    $transport = new FakeReferenceExternalTranslationTransport;

    $adapter = pp5caTranslationAdapter($transport, timeoutSeconds: 300);

    expect($adapter)->toBeInstanceOf(ReferenceExternalTranslationProvider::class);

    try {
        pp5caTranslationAdapter($transport, timeoutSeconds: 301);

        $this->fail('Expected ConfigurationError for timeout above the provider ceiling.');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ConfigurationError);
    }

    expect($transport->dispatchCount())->toBe(0);
});

it('AC6 ceiling rejections perform no retry and no fallback', function () {
    $transport = new FakeReferenceExternalTransport;
    $adapter = pp5caTranscriptionAdapter($transport, maxRequestBytes: 1024);

    foreach ([1025, 2048] as $size) {
        try {
            $adapter->transcribe(pp5caTranscriptionInvocation(fileSizeBytes: $size));

            $this->fail('Expected MediaRejected.');
        } catch (TranscriptionException $e) {
            expect($e->failure)->toBe(TranscriptionFailure::MediaRejected);
        }
    }

    expect($transport->dispatchCount())->toBe(0)
        ->and(config('transcription.provider_selection'))->toBe('self_hosted');
});

it('AC7 invalid_selection: unknown values warn with domain context and fail closed', function () {
    $captured = pp5caCaptureLogs(function () {
        config(['transcription.provider_selection' => 'bogus_vendor']);

        try {
            (new TranscriptionProviderResolver)->resolve('pp5-invalid-1');

            $this->fail('Expected ProviderResolutionException.');
        } catch (ProviderResolutionException $e) {
            expect($e->getMessage())->toContain('bogus_vendor');
        }

        config(['translation.provider_selection' => 'external_other']);

        try {
            (new TranslationProviderResolver)->resolve('pp5-invalid-2');

            $this->fail('Expected ProviderResolutionException.');
        } catch (App\Translation\ProviderResolutionException $e) {
            expect($e->getMessage())->toContain('external_other');
        }
    });

    $transcriptionWarning = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Unknown transcription provider selection; failing closed.',
    ));
    $translationWarning = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Unsupported translation provider selection; failing closed.',
    ));

    expect($transcriptionWarning)->toHaveCount(1)
        ->and($transcriptionWarning[0]->level)->toBe('warning')
        ->and($transcriptionWarning[0]->context['domain'])->toBe('transcription')
        ->and($transcriptionWarning[0]->context['request_id'])->toBe('pp5-invalid-1')
        ->and($translationWarning)->toHaveCount(1)
        ->and($translationWarning[0]->level)->toBe('warning')
        ->and($translationWarning[0]->context['domain'])->toBe('translation')
        ->and($translationWarning[0]->context['request_id'])->toBe('pp5-invalid-2')
        ->and(pp5caPayloads($captured['records']))->not->toContain(PP5_CA_TOKEN);
});

it('AC7 missing_credentials: empty-secret construction fails closed before dispatch', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    $translationTransport = new FakeReferenceExternalTranslationTransport;

    // The operator-visible alert signal for this condition is the synchronous
    // fail-closed ConfigurationError (surfaced to callers and the job error
    // layer); no dispatch occurs and no secret exists to leak.
    foreach (['transcription', 'translation'] as $domain) {
        try {
            if ($domain === 'transcription') {
                new ReferenceExternalTranscriptionProvider(
                    baseUrl: 'http://localhost:9/reference',
                    token: '',
                    providerKey: 'external_reference',
                    modelPinned: 'reference-1.0',
                    transport: $transcriptionTransport,
                );
            } else {
                new ReferenceExternalTranslationProvider(
                    baseUrl: 'http://localhost:9/reference-translate',
                    token: '',
                    providerKey: 'external_reference',
                    modelPinned: 'reference-1.0',
                    transport: $translationTransport,
                );
            }

            $this->fail("Expected ConfigurationError for empty {$domain} credentials.");
        } catch (TranscriptionException|TranslationException $e) {
            expect($e->failure)->toBe($domain === 'transcription' ? TranscriptionFailure::ConfigurationError : TranslationFailure::ConfigurationError);
        }
    }

    expect($transcriptionTransport->dispatchCount())->toBe(0)
        ->and($translationTransport->dispatchCount())->toBe(0);
});

it('AC7 forbidden_external: engaged kill-switch forces self-hosted with zero dispatches', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    $translationTransport = new FakeReferenceExternalTranslationTransport;
    app()->bind('transcription.providers.external_reference', fn () => pp5caTranscriptionAdapter($transcriptionTransport));
    app()->bind('translation.providers.external_reference', fn () => pp5caTranslationAdapter($translationTransport));

    config(['transcription.provider_selection' => 'external_reference']);
    config(['translation.provider_selection' => 'external_reference']);
    config(['processing.external_kill_switch' => true]);

    $captured = pp5caCaptureLogs(function () use (&$transcriptionResolved, &$translationResolved) {
        $transcriptionResolved = (new TranscriptionProviderResolver)->resolve('pp5-forbidden-1');
        $translationResolved = (new TranslationProviderResolver)->resolve('pp5-forbidden-2');
    });

    expect($transcriptionResolved)->toBeInstanceOf(SelfHostedTranscriptionProvider::class)
        ->and($translationResolved)->toBeInstanceOf(SelfHostedTranslationProvider::class)
        ->and($transcriptionTransport->dispatchCount())->toBe(0)
        ->and($translationTransport->dispatchCount())->toBe(0);

    $engaged = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => str_contains($record->message, 'kill switch engaged'),
    ));

    expect($engaged)->toHaveCount(2);

    foreach ($engaged as $record) {
        expect($record->context['kill_switch_engaged'])->toBeTrue()
            ->and($record->context['provider_key'])->toBe('self_hosted');
    }

    expect($engaged[0]->context['request_id'])->toBe('pp5-forbidden-1')
        ->and($engaged[1]->context['request_id'])->toBe('pp5-forbidden-2');
});

it('AC7 ceiling_breach: oversized requests warn with ceiling context and mapped codes', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    $translationTransport = new FakeReferenceExternalTranslationTransport;

    $captured = pp5caCaptureLogs(function () use ($transcriptionTransport, $translationTransport) {
        try {
            pp5caTranscriptionAdapter($transcriptionTransport, maxRequestBytes: 512)
                ->transcribe(pp5caTranscriptionInvocation(fileSizeBytes: 513));

            $this->fail('Expected MediaRejected.');
        } catch (TranscriptionException $e) {
            expect($e->failure)->toBe(TranscriptionFailure::MediaRejected);
        }

        try {
            pp5caTranslationAdapter($translationTransport, maxSegments: 1)
                ->translate(pp5caTranslationInvocation());

            $this->fail('Expected InvalidRequest.');
        } catch (TranslationException $e) {
            expect($e->failure)->toBe(TranslationFailure::InvalidRequest);
        }
    });

    $transcriptionCeiling = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external transcription request exceeds provider ceiling.',
    ));
    $translationCeiling = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external translation request exceeds provider ceiling.',
    ));

    expect($transcriptionCeiling)->toHaveCount(1)
        ->and($transcriptionCeiling[0]->level)->toBe('warning')
        ->and($transcriptionCeiling[0]->context['failure_category'])->toBe(TranscriptionFailure::MediaRejected->value)
        ->and($translationCeiling)->toHaveCount(1)
        ->and($translationCeiling[0]->level)->toBe('warning')
        ->and($translationCeiling[0]->context['failure_category'])->toBe(TranslationFailure::InvalidRequest->value)
        ->and($transcriptionTransport->dispatchCount())->toBe(0)
        ->and($translationTransport->dispatchCount())->toBe(0)
        ->and(pp5caPayloads($captured['records']))->not->toContain(PP5_CA_TOKEN);
});

it('AC7 saturation_timeout: timeout faults map retryable with anomaly records and no fallback', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    $transcriptionTransport->script[0] = ReferenceExternalChunkResponse::failure(
        ReferenceExternalChunkFailureKind::Timeout,
        failureMessage: 'synthetic timeout',
    );

    $translationTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(
            ReferenceExternalTranslationFailureKind::Timeout,
            failureMessage: 'synthetic timeout',
        )
    );

    $captured = pp5caCaptureLogs(function () use ($transcriptionTransport, $translationTransport) {
        try {
            pp5caTranscriptionAdapter($transcriptionTransport)->transcribe(pp5caTranscriptionInvocation());

            $this->fail('Expected WorkerTimeout.');
        } catch (TranscriptionException $e) {
            expect($e->failure)->toBe(TranscriptionFailure::WorkerTimeout)
                ->and($e->failure->isRetryable())->toBeTrue();
        }

        try {
            pp5caTranslationAdapter($translationTransport)->translate(pp5caTranslationInvocation());

            $this->fail('Expected ProviderTimeout.');
        } catch (TranslationException $e) {
            expect($e->failure)->toBe(TranslationFailure::ProviderTimeout)
                ->and($e->failure->isRetryable())->toBeTrue();
        }
    });

    $transcriptionAnomaly = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external chunk failed.',
    ));
    $translationAnomaly = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external translation failed.',
    ));

    expect($transcriptionAnomaly)->toHaveCount(1)
        ->and($transcriptionAnomaly[0]->level)->toBe('warning')
        ->and($transcriptionAnomaly[0]->context['failure_category'])->toBe(TranscriptionFailure::WorkerTimeout->value)
        ->and($transcriptionAnomaly[0]->context['outcome'])->toBe('failed')
        ->and($translationAnomaly)->toHaveCount(1)
        ->and($translationAnomaly[0]->level)->toBe('warning')
        ->and($translationAnomaly[0]->context['failure_category'])->toBe(TranslationFailure::ProviderTimeout->value)
        ->and($translationAnomaly[0]->context['outcome'])->toBe('failed')
        ->and($transcriptionTransport->dispatchCount())->toBe(1)
        ->and($translationTransport->dispatchCount())->toBe(1)
        ->and(config('transcription.provider_selection'))->toBe('self_hosted')
        ->and(config('translation.provider_selection'))->toBe('self_hosted')
        ->and(pp5caPayloads($captured['records']))->not->toContain(PP5_CA_TOKEN);
});

it('AC7 saturation_timeout: rate-limit faults map saturated/unavailable with anomaly records', function () {
    $transcriptionTransport = new FakeReferenceExternalTransport;
    $transcriptionTransport->script[0] = ReferenceExternalChunkResponse::failure(
        ReferenceExternalChunkFailureKind::RateLimited,
        failureMessage: 'synthetic 429',
    );

    $translationTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(
            ReferenceExternalTranslationFailureKind::RateLimited,
            failureMessage: 'synthetic 429',
        )
    );

    $captured = pp5caCaptureLogs(function () use ($transcriptionTransport, $translationTransport) {
        try {
            pp5caTranscriptionAdapter($transcriptionTransport)->transcribe(pp5caTranscriptionInvocation());

            $this->fail('Expected WorkerSaturated.');
        } catch (TranscriptionException $e) {
            expect($e->failure)->toBe(TranscriptionFailure::WorkerSaturated)
                ->and($e->failure->isRetryable())->toBeTrue();
        }

        try {
            pp5caTranslationAdapter($translationTransport)->translate(pp5caTranslationInvocation());

            $this->fail('Expected ProviderUnavailable.');
        } catch (TranslationException $e) {
            expect($e->failure)->toBe(TranslationFailure::ProviderUnavailable)
                ->and($e->failure->isRetryable())->toBeTrue();
        }
    });

    $categories = array_map(
        static fn (MessageLogged $record): ?string => $record->context['failure_category'] ?? null,
        $captured['records'],
    );

    expect($categories)->toContain(TranscriptionFailure::WorkerSaturated->value)
        ->and($categories)->toContain(TranslationFailure::ProviderUnavailable->value)
        ->and($transcriptionTransport->dispatchCount())->toBe(1)
        ->and($translationTransport->dispatchCount())->toBe(1);
});
