<?php

use App\Models\TranslationSegment;
use App\Transcription\LanguageIdentifier;
use App\Translation\ReferenceExternalTranslationFailureKind;
use App\Translation\ReferenceExternalTranslationProvider;
use App\Translation\ReferenceExternalTranslationResponse;
use App\Translation\ReferenceExternalTranslationTransport;
use App\Translation\TranslationException;
use App\Translation\TranslationFailure;
use App\Translation\TranslationInvocation;
use App\Translation\TranslationProvider;
use App\Translation\TranslationResponseValidator;
use App\Translation\TranslationResult;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeReferenceExternalTranslationTransport;

const PP4_FIXTURE_TOKEN = 'fixture-token-pp4-not-a-secret';
const PP4_PROVIDER_KEY = 'external_reference';
const PP4_MODEL_PINNED = 'reference-1.0';

function pp4Segments(
    string $sourceA = 'en',
    string $sourceB = 'en',
): array {
    return [
        new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::fromBcp47($sourceA)),
        new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::fromBcp47($sourceB)),
    ];
}

function pp4Invocation(
    TranslationTarget $target = TranslationTarget::Malay,
    ?array $segments = null,
    int $transcriptionId = 42,
    int $translationId = 7,
): TranslationInvocation {
    return TranslationInvocation::create(
        transcriptionId: $transcriptionId,
        targetLanguage: $target,
        segments: $segments ?? pp4Segments(),
        translationId: $translationId,
    );
}

function pp4Adapter(
    FakeReferenceExternalTranslationTransport $transport,
    ?int $timeoutSeconds = null,
    int $maxSegments = ReferenceExternalTranslationProvider::DEFAULT_MAX_SEGMENTS,
    int $maxPayloadChars = ReferenceExternalTranslationProvider::DEFAULT_MAX_PAYLOAD_CHARS,
): ReferenceExternalTranslationProvider {
    return new ReferenceExternalTranslationProvider(
        baseUrl: 'http://localhost:9/reference-translate',
        token: PP4_FIXTURE_TOKEN,
        providerKey: PP4_PROVIDER_KEY,
        modelPinned: PP4_MODEL_PINNED,
        transport: $transport,
        timeoutSeconds: $timeoutSeconds,
        maxSegments: $maxSegments,
        maxPayloadChars: $maxPayloadChars,
    );
}

function pp4AlignedShapes(string $t0 = 'Hai semua', string $t1 = 'Selamat datang'): array
{
    return [
        ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => $t0, 'source_language' => 'en'],
        ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => $t1, 'source_language' => 'en'],
    ];
}

/**
 * @return array{records: list<MessageLogged>}
 */
function pp4CaptureLogs(callable $run): array
{
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records): void {
        $records[] = $event;
    });

    $run();

    return ['records' => $records];
}

function pp4LogPayloads(array $records): string
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
    config(['translation.worker_token' => '']);
    config(['translation.timeout_seconds' => 300]);
});

it('AC2: maps fixture requests to exact outbound fields with zero network calls', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4AlignedShapes())
    );

    $invocation = pp4Invocation();
    $result = pp4Adapter($transport)->translate($invocation);

    expect($transport->dispatchCount())->toBe(1);

    $outbound = $transport->requests[0];
    expect($outbound->authorization)->toBe('Bearer '.PP4_FIXTURE_TOKEN)
        ->and($outbound->baseUrl)->toBe('http://localhost:9/reference-translate')
        ->and($outbound->providerKey)->toBe(PP4_PROVIDER_KEY)
        ->and($outbound->modelPinned)->toBe(PP4_MODEL_PINNED)
        ->and($outbound->requestId)->toBe($invocation->requestId)
        ->and($outbound->transcriptionId)->toBe(42)
        ->and($outbound->translationId)->toBe(7)
        ->and($outbound->targetLanguage)->toBe('ms')
        ->and($outbound->timeoutSeconds)->toBe(300)
        ->and($outbound->segments)->toHaveCount(2)
        ->and($outbound->segments[0]['text'])->toBe('Hello')
        ->and($result)->toBeInstanceOf(TranslationResult::class);
});

it('AC1: reference adapter implements the frozen provider contract', function () {
    $adapter = pp4Adapter(new FakeReferenceExternalTranslationTransport);

    expect($adapter)->toBeInstanceOf(TranslationProvider::class)
        ->and($adapter)->toBeInstanceOf(ReferenceExternalTranslationProvider::class);
});

it('AC3: aligns per-target fixtures through the frozen validator', function () {
    foreach ([TranslationTarget::Malay, TranslationTarget::Tamil] as $target) {
        $invocation = pp4Invocation(target: $target);
        $shapes = array_map(
            static fn (TranslationSegmentData $segment): array => [
                'segment_index' => $segment->segmentIndex,
                'start_seconds' => $segment->startSeconds,
                'end_seconds' => $segment->endSeconds,
                'text' => 'T['.$target->value.':'.$segment->segmentIndex.']',
                'source_language' => $segment->sourceLanguage->value,
            ],
            $invocation->segments,
        );
        $transport = new FakeReferenceExternalTranslationTransport(
            ReferenceExternalTranslationResponse::success($target->value, 'T0 T1', $shapes)
        );

        $result = pp4Adapter($transport)->translate($invocation);

        expect($result->targetLanguage)->toBe($target)
            ->and($result->segments)->toHaveCount(2)
            ->and($result->segments[0]->segmentIndex)->toBe(0)
            ->and($result->segments[1]->segmentIndex)->toBe(1)
            ->and($result->provider)->toBe(PP4_PROVIDER_KEY)
            ->and($result->model)->toBe(PP4_MODEL_PINNED);
    }
});

it('AC3: preserves und-source echo and mixed source segments without resegmentation', function () {
    $invocation = pp4Invocation(segments: pp4Segments('und', 'ms'));
    $shapes = array_map(
        static fn (TranslationSegmentData $segment, string $text): array => [
            'segment_index' => $segment->segmentIndex,
            'start_seconds' => $segment->startSeconds,
            'end_seconds' => $segment->endSeconds,
            'text' => $text,
            'source_language' => $segment->sourceLanguage->value,
        ],
        $invocation->segments,
        ['T0', 'T1'],
    );
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'T0 T1', $shapes)
    );

    $result = pp4Adapter($transport)->translate($invocation);

    expect($result->segments[0]->sourceLanguage)->toBe(LanguageIdentifier::Undetermined)
        ->and($result->segments[1]->sourceLanguage)->toBe(LanguageIdentifier::Malay)
        ->and($result->segments)->toHaveCount(2);
});

it('AC4: count mismatch fails closed with MissingSegments and no partial persist', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Only one', [pp4AlignedShapes()[0]])
    );

    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected MissingSegments');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::MissingSegments)
            ->and($e->failure->isRetryable())->toBeFalse();
    }

    expect($translation->fresh()->status)->toBe(TranslationStatus::Translating)
        ->and(TranslationSegment::query()->where('translation_id', $translation->getKey())->count())->toBe(0);
});

it('AC4: unknown index fails closed with MissingSegments', function () {
    $shapes = pp4AlignedShapes();
    $shapes[1]['segment_index'] = 9;
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', $shapes)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected MissingSegments');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::MissingSegments);
    }
});

it('AC4: timestamp drift beyond tolerance fails closed with MalformedOutput', function () {
    $shapes = pp4AlignedShapes();
    $shapes[0]['start_seconds'] = 0.5;
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', $shapes)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected MalformedOutput');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::MalformedOutput);
    }
});

it('AC4: source-echo mismatch fails closed with MalformedOutput', function () {
    $shapes = pp4AlignedShapes();
    $shapes[0]['source_language'] = 'ms';
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', $shapes)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected MalformedOutput');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::MalformedOutput);
    }
});

it('AC4: target mismatch fails closed with MalformedOutput', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ta', 'T0 T1', pp4AlignedShapes())
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected MalformedOutput');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::MalformedOutput);
    }
});

it('AC5: timeout maps to ProviderTimeout and is retryable', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(ReferenceExternalTranslationFailureKind::Timeout)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected ProviderTimeout');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ProviderTimeout)
            ->and($e->failure->isRetryable())->toBeTrue()
            ->and($transport->dispatchCount())->toBe(1);
    }
});

it('AC5: rate-limited maps to ProviderUnavailable and is retryable', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(ReferenceExternalTranslationFailureKind::RateLimited)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected ProviderUnavailable');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ProviderUnavailable)
            ->and($e->failure->isRetryable())->toBeTrue();
    }
});

it('AC5: server-error and unreachable map to ProviderUnavailable', function () {
    foreach ([
        ReferenceExternalTranslationFailureKind::ServerError,
        ReferenceExternalTranslationFailureKind::Unreachable,
    ] as $kind) {
        $transport = new FakeReferenceExternalTranslationTransport(
            ReferenceExternalTranslationResponse::failure($kind)
        );

        try {
            pp4Adapter($transport)->translate(pp4Invocation());
            expect(false)->toBeTrue('expected ProviderUnavailable');
        } catch (TranslationException $e) {
            expect($e->failure)->toBe(TranslationFailure::ProviderUnavailable)
                ->and($e->failure->isRetryable())->toBeTrue();
        }
    }
});

it('AC5: partial maps to MissingSegments and is non-retryable', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(ReferenceExternalTranslationFailureKind::Partial)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected MissingSegments');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::MissingSegments)
            ->and($e->failure->isRetryable())->toBeFalse();
    }
});

it('AC5: invalid maps to MalformedOutput even with a contradictory retryable hint', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(
            ReferenceExternalTranslationFailureKind::Invalid,
            true,
            'vendor claims retryable',
        )
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected MalformedOutput');
    } catch (TranslationException $e) {
        // The transport advisory hint must never override canonical mapping.
        expect($e->failure)->toBe(TranslationFailure::MalformedOutput)
            ->and($e->failure->isRetryable())->toBeFalse();
    }
});

it('AC5: unknown maps to the frozen ProviderFailed default', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(ReferenceExternalTranslationFailureKind::Unknown)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected ProviderFailed');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ProviderFailed)
            ->and($e->failure->isRetryable())->toBe(
                TranslationResponseValidator::failureFromCode('SOMETHING_NEW')->isRetryable()
            );
    }
});

it('AC5: dropped dispatch fails closed with ProviderFailed after exactly one dispatch', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4AlignedShapes())
    );
    $transport->dropNext = true;

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected ProviderFailed');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ProviderFailed)
            ->and($transport->dispatchCount())->toBe(1);
    }
});

it('AC5: unsupported source tag maps to UnsupportedSource', function () {
    $shapes = pp4AlignedShapes();
    $shapes[0]['source_language'] = 'xx';
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', $shapes)
    );

    try {
        pp4Adapter($transport)->translate(pp4Invocation());
        expect(false)->toBeTrue('expected UnsupportedSource');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::UnsupportedSource)
            ->and($e->failure->isRetryable())->toBeFalse();
    }
});

it('AC5: empty-secret production construction fails closed before dispatch', function () {
    $transport = new FakeReferenceExternalTranslationTransport;

    try {
        new ReferenceExternalTranslationProvider(
            baseUrl: 'http://localhost:9/reference-translate',
            token: '',
            providerKey: PP4_PROVIDER_KEY,
            modelPinned: PP4_MODEL_PINNED,
            transport: $transport,
        );
        expect(false)->toBeTrue('expected ConfigurationError');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ConfigurationError)
            ->and($e->failure->isRetryable())->toBeFalse()
            ->and($transport->dispatchCount())->toBe(0);
    }
});

it('AC5: contracted HTTP rows match the frozen failureForStatus behavior', function () {
    // The §9 table's HTTP rows are seam-inapplicable to the fake transport;
    // they pin the canonical mapping the frozen self-hosted path honors, so a
    // future vendor addendum cannot reinterpret them.
    expect(TranslationResponseValidator::failureFromCode('PROVIDER_TIMEOUT'))->toBe(TranslationFailure::ProviderTimeout)
        ->and(TranslationResponseValidator::failureFromCode('PROVIDER_UNAVAILABLE'))->toBe(TranslationFailure::ProviderUnavailable)
        ->and(TranslationResponseValidator::failureFromCode('CONFIGURATION_ERROR'))->toBe(TranslationFailure::ConfigurationError)
        ->and(TranslationResponseValidator::failureFromCode('MALFORMED_OUTPUT'))->toBe(TranslationFailure::MalformedOutput)
        ->and(TranslationResponseValidator::failureFromCode('MISSING_SEGMENTS'))->toBe(TranslationFailure::MissingSegments)
        ->and(TranslationResponseValidator::failureFromCode('TOTALLY_UNKNOWN_CODE'))->toBe(TranslationFailure::ProviderFailed);
});

it('AC6: logs carry identity and outcome while secrets and full text stay absent', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4AlignedShapes())
    );
    $invocation = pp4Invocation();

    $captured = pp4CaptureLogs(fn () => pp4Adapter($transport)->translate($invocation));

    $completion = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external translation completed.'
    ));

    expect($completion)->toHaveCount(1);

    $context = $completion[0]->context;
    expect($context['request_id'])->toBe($invocation->requestId)
        ->and($context['domain'])->toBe('translation')
        ->and($context['provider_key'])->toBe(PP4_PROVIDER_KEY)
        ->and($context['model_pinned'])->toBe(PP4_MODEL_PINNED)
        ->and($context['transcription_id'])->toBe(42)
        ->and($context['translation_id'])->toBe(7)
        ->and($context['target_language'])->toBe('ms')
        ->and($context['outcome'])->toBe('success')
        ->and($context)->toHaveKey('duration_ms')
        ->and($context['segment_count'])->toBe(2);

    $payloads = pp4LogPayloads($captured['records']);
    expect($payloads)->not->toContain(PP4_FIXTURE_TOKEN)
        ->and($payloads)->not->toContain('Hai semua Selamat datang');
});

it('AC6: validator rejection leaves a failed audit record with the mapped category', function () {
    // PP-T4-REV-01 corrective: every terminal outcome, including frozen
    // validator rejections, must be reconstructible from adapter logs.
    $shapes = pp4AlignedShapes();
    $shapes[1]['segment_index'] = 9;
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', $shapes)
    );

    $captured = pp4CaptureLogs(function () use ($transport) {
        try {
            pp4Adapter($transport)->translate(pp4Invocation());
        } catch (TranslationException) {
        }
    });

    $failures = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external translation failed.'
    ));

    expect($failures)->toHaveCount(1)
        ->and($failures[0]->context['failure_category'])->toBe(TranslationFailure::MissingSegments->value)
        ->and($failures[0]->context['request_id'])->not->toBe('')
        ->and($transport->dispatchCount())->toBe(1);
});

it('AC6: failure logs carry the mapped failure category', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::failure(ReferenceExternalTranslationFailureKind::Timeout)
    );

    $captured = pp4CaptureLogs(function () use ($transport) {
        try {
            pp4Adapter($transport)->translate(pp4Invocation());
        } catch (TranslationException) {
        }
    });

    $failures = array_values(array_filter(
        $captured['records'],
        static fn (MessageLogged $record): bool => $record->message === 'Reference external translation failed.'
    ));

    expect($failures)->toHaveCount(1)
        ->and($failures[0]->context['failure_category'])->toBe(TranslationFailure::ProviderTimeout->value)
        ->and($failures[0]->context['outcome'])->toBe('failed');
});

it('AC7: segment ceiling rejects before dispatch with InvalidRequest', function () {
    $segments = [
        new TranslationSegmentData(0, 0.0, 1.0, 'A', LanguageIdentifier::English),
        new TranslationSegmentData(1, 1.0, 2.0, 'B', LanguageIdentifier::English),
        new TranslationSegmentData(2, 2.0, 3.0, 'C', LanguageIdentifier::English),
    ];

    // Boundary-equal passes on the segment count (chars way below ceiling).
    $okTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'T', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 1.0, 'text' => 'A', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 1.0, 'end_seconds' => 2.0, 'text' => 'B', 'source_language' => 'en'],
            ['segment_index' => 2, 'start_seconds' => 2.0, 'end_seconds' => 3.0, 'text' => 'C', 'source_language' => 'en'],
        ])
    );
    $boundary = pp4Adapter($okTransport, maxSegments: 3)->translate(pp4Invocation(segments: $segments));
    expect($boundary->segments)->toHaveCount(3)
        ->and($okTransport->dispatchCount())->toBe(1);

    // Boundary-exceed rejects before dispatch.
    $overTransport = new FakeReferenceExternalTranslationTransport;

    try {
        pp4Adapter($overTransport, maxSegments: 2)->translate(pp4Invocation(segments: $segments));
        expect(false)->toBeTrue('expected InvalidRequest');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::InvalidRequest)
            ->and($e->failure->isRetryable())->toBeFalse()
            ->and($overTransport->dispatchCount())->toBe(0);
    }
});

it('AC7: character ceiling rejects before dispatch with InvalidRequest', function () {
    $long = str_repeat('x', 128);
    $segments = [
        new TranslationSegmentData(0, 0.0, 1.0, $long, LanguageIdentifier::English),
        new TranslationSegmentData(1, 1.0, 2.0, 'B', LanguageIdentifier::English),
    ];

    // 129 payload chars: boundary-equal passes.
    $okTransport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'T', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 1.0, 'text' => 'A', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 1.0, 'end_seconds' => 2.0, 'text' => 'B', 'source_language' => 'en'],
        ])
    );
    pp4Adapter($okTransport, maxPayloadChars: 129)->translate(pp4Invocation(segments: $segments));
    expect($okTransport->dispatchCount())->toBe(1);

    // One char over rejects with zero dispatch.
    $overTransport = new FakeReferenceExternalTranslationTransport;

    try {
        pp4Adapter($overTransport, maxPayloadChars: 128)->translate(pp4Invocation(segments: $segments));
        expect(false)->toBeTrue('expected InvalidRequest');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::InvalidRequest)
            ->and($overTransport->dispatchCount())->toBe(0);
    }
});

it('AC8: suite passes with empty provider env secrets and no env reads in boundary', function () {
    config(['translation.worker_token' => '']);
    config(['translation.worker_url' => 'http://localhost:8000']);

    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4AlignedShapes())
    );

    $result = pp4Adapter($transport)->translate(pp4Invocation());

    expect($result->segments)->toHaveCount(2);

    foreach (['ReferenceExternalTranslationProvider.php', 'ReferenceExternalTranslationRequest.php', 'ReferenceExternalTranslationResponse.php'] as $file) {
        $code = (string) file_get_contents(app_path('Translation/'.$file));

        expect($code)->not->toContain('env(');
    }
});

it('timeout honors the 300s provider ceiling and carries explicit values', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4AlignedShapes())
    );

    pp4Adapter($transport, timeoutSeconds: 60)->translate(pp4Invocation());

    expect($transport->requests[0]->timeoutSeconds)->toBe(60);

    try {
        pp4Adapter(new FakeReferenceExternalTranslationTransport, timeoutSeconds: 600);
        expect(false)->toBeTrue('expected ConfigurationError');
    } catch (TranslationException $e) {
        expect($e->failure)->toBe(TranslationFailure::ConfigurationError);
    }
});

it('AC10: reuses the invocation requestId and performs no hidden retry', function () {
    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', pp4AlignedShapes())
    );

    $first = pp4Invocation();
    $second = pp4Invocation();

    pp4Adapter($transport)->translate($first);
    pp4Adapter($transport)->translate($second);

    // Two explicit attempts → exactly two dispatches, each carrying its own
    // invocation identity; the adapter mints nothing.
    expect($transport->dispatchCount())->toBe(2)
        ->and($transport->requests[0]->requestId)->toBe($first->requestId)
        ->and($transport->requests[1]->requestId)->toBe($second->requestId)
        ->and($first->requestId)->not->toBe($second->requestId);
});

it('AC11: same input with a pinned version reruns byte-identical', function () {
    $shapes = pp4AlignedShapes();

    $run = static fn (): TranslationResult => pp4Adapter(new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', $shapes)
    ))->translate(pp4Invocation());

    expect(serialize($run()))->toBe(serialize($run()));
});

it('writer compatibility: adapter output persists atomically with machine-source alignment', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
        ])
    );

    $invocation = TranslationInvocation::create(
        transcriptionId: (int) $transcription->getKey(),
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::English),
        ],
        translationId: (int) $translation->getKey(),
    );

    $before = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();

    $written = writerPersist($transcription, pp4Adapter($transport)->translate($invocation), $translation);

    expect($written->fresh()->status)->toBe(TranslationStatus::Completed)
        ->and($written->provider)->toBe(PP4_PROVIDER_KEY)
        ->and($written->model)->toBe(PP4_MODEL_PINNED)
        ->and($written->segments()->pluck('text')->all())->toBe(['Hai semua', 'Selamat datang'])
        ->and($written->segments()->pluck('source_language')->map->value->all())->toBe(['en', 'en']);

    $after = $transcription->segments()->get()->map->only(['segment_index', 'start_seconds', 'end_seconds', 'text', 'language'])->all();
    expect($after)->toBe($before);
});

it('AC12: persistence never restores currency on a stale translation', function () {
    $transcription = writerSource();
    $translation = writerTranslation($transcription);

    $transport = new FakeReferenceExternalTranslationTransport(
        ReferenceExternalTranslationResponse::success('ms', 'Hai semua Selamat datang', [
            ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 4.999, 'text' => 'Hai semua', 'source_language' => 'en'],
            ['segment_index' => 1, 'start_seconds' => 4.999, 'end_seconds' => 9.5, 'text' => 'Selamat datang', 'source_language' => 'en'],
        ])
    );

    $invocation = TranslationInvocation::create(
        transcriptionId: (int) $transcription->getKey(),
        targetLanguage: TranslationTarget::Malay,
        segments: [
            new TranslationSegmentData(0, 0.0, 4.999, 'Hello', LanguageIdentifier::English),
            new TranslationSegmentData(1, 4.999, 9.5, 'Welcome', LanguageIdentifier::English),
        ],
        translationId: (int) $translation->getKey(),
    );

    $result = pp4Adapter($transport)->translate($invocation);
    writerPersist($transcription, $result, $translation);

    // Edit-after-translate marks the row stale (P6-005 additive schema).
    $translation->forceFill([
        'stale_at' => now(),
        'staleness_reason' => 'SOURCE_TEXT_CHANGED',
        'stale_caused_by_revision_id' => 'rev-fixture-1',
    ])->save();

    // A same-token persist (idempotent completion path) must not clear it.
    $same = writerPersist($transcription, $result, $translation);

    expect($same->isStale())->toBeTrue()
        ->and($same->fresh()->stale_caused_by_revision_id)->toBe('rev-fixture-1');
});

it('zero-network audit: reference boundary performs no external traffic', function () {
    $needles = ['Http::', 'curl_', 'curl_init', 'fsockopen', 'file_get_contents', 'socket_'];

    foreach (['ReferenceExternalTranslationProvider.php', 'ReferenceExternalTranslationRequest.php', 'ReferenceExternalTranslationResponse.php', 'ReferenceExternalTranslationTransport.php'] as $file) {
        $code = (string) file_get_contents(app_path('Translation/'.$file));

        foreach ($needles as $needle) {
            expect($code)->not->toContain($needle, "network needle [{$needle}] in {$file}");
        }
    }

    expect((new ReflectionClass(ReferenceExternalTranslationTransport::class))->getMethods())->toHaveCount(1);
});
