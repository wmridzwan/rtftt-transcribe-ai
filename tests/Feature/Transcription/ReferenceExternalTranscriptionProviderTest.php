<?php

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\TranscriptionSegment;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\ReferenceExternalChunkFailureKind;
use App\Transcription\ReferenceExternalChunkResponse;
use App\Transcription\ReferenceExternalTranscriptionProvider;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionMedia;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeReferenceExternalTransport;
use Tests\Support\TranscriptionFixtures;

const PP3_FIXTURE_TOKEN = 'fixture-token-pp3-not-a-secret';
const PP3_PROVIDER_KEY = 'external_reference';
const PP3_MODEL_PINNED = 'reference-1.0';

function pp3Invocation(
    int $transcriptionId = 11,
    int $attemptId = 3,
    ?float $durationSeconds = 12.0,
    int $fileSizeBytes = 1024000,
): TranscriptionInvocation {
    return TranscriptionInvocation::create(
        transcriptionId: $transcriptionId,
        processingAttemptId: $attemptId,
        media: new TranscriptionMedia(
            storageKey: 'media/pp3-fixture.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: $fileSizeBytes,
            durationSeconds: $durationSeconds,
        ),
        requestedLanguage: LanguageIdentifier::Malay,
    );
}

function pp3Adapter(
    FakeReferenceExternalTransport $transport,
    ?int $timeoutSeconds = null,
    int $maxRequestBytes = ReferenceExternalTranscriptionProvider::PRODUCT_LIMIT_BYTES,
): ReferenceExternalTranscriptionProvider {
    return new ReferenceExternalTranscriptionProvider(
        baseUrl: 'http://localhost:9/reference',
        token: PP3_FIXTURE_TOKEN,
        providerKey: PP3_PROVIDER_KEY,
        modelPinned: PP3_MODEL_PINNED,
        transport: $transport,
        timeoutSeconds: $timeoutSeconds,
        maxRequestBytes: $maxRequestBytes,
    );
}

/**
 * @return array{records: list<MessageLogged>}
 */
function pp3CaptureLogs(callable $run): array
{
    $records = [];
    Log::listen(function (MessageLogged $event) use (&$records): void {
        $records[] = $event;
    });

    $run();

    return ['records' => $records];
}

function pp3LogPayloads(array $records): string
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
    config(['transcription.worker_token' => '']);
    config(['transcription.timeout_seconds' => 300]);
});

it('AC2: maps fixture requests to exact outbound fields with zero network calls', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
        ],
    );

    $invocation = pp3Invocation();
    $result = pp3Adapter($transport)->transcribe($invocation);

    expect($transport->dispatchCount())->toBe(1);

    $outbound = $transport->requests[0];
    expect($outbound->authorization)->toBe('Bearer '.PP3_FIXTURE_TOKEN)
        ->and($outbound->baseUrl)->toBe('http://localhost:9/reference')
        ->and($outbound->providerKey)->toBe(PP3_PROVIDER_KEY)
        ->and($outbound->modelPinned)->toBe(PP3_MODEL_PINNED)
        ->and($outbound->offsetMs)->toBe(0)
        ->and($outbound->requestId)->toBe($invocation->requestId)
        ->and($outbound->transcriptionId)->toBe(11)
        ->and($outbound->processingAttemptId)->toBe(3)
        ->and($outbound->attemptSeq)->toBe(0)
        ->and($outbound->timeoutSeconds)->toBe(300)
        ->and($outbound->requestedLanguage)->toBe('ms')
        ->and($result)->toBeInstanceOf(NormalizedTranscript::class);
});

it('AC3: recomposes offsets, overlaps, duplicates, and language transitions', function () {
    $transport = new FakeReferenceExternalTransport;
    // Chunk 0 (offset 0) and chunk 1 (offset 30000): same text at distinct
    // absolute windows is a distinct occurrence (kept); exact duplicates are
    // proven in the dedup test. Chunk 1 also carries Tamil + allowlist-miss.
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'hello world', 'language' => 'en'],
            ['relative_start_ms' => 2000, 'relative_end_ms' => 4000, 'text' => 'selamat pagi', 'language' => 'ms'],
        ],
    );
    $transport->script[1] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'selamat pagi', 'language' => 'ms'],
            ['relative_start_ms' => 2000, 'relative_end_ms' => 4000, 'text' => 'vanakkam', 'language' => 'ta'],
            ['relative_start_ms' => 4000, 'relative_end_ms' => 5000, 'text' => 'mystery tongue', 'language' => 'xx'],
        ],
    );

    $invocation = pp3Invocation(durationSeconds: 65.0);
    $result = pp3Adapter($transport)->transcribe($invocation);

    // 65 s plans 3 chunks; the third defaults to no-speech fixture silence.
    expect($transport->dispatchCount())->toBe(3);

    $segments = $result->segments;
    expect(count($segments))->toBe(5)
        ->and($segments[0]->text)->toBe('hello world')
        ->and($segments[0]->startSeconds)->toBe(0.0)
        ->and($segments[0]->endSeconds)->toBe(2.0)
        ->and($segments[0]->language)->toBe(LanguageIdentifier::English)
        ->and($segments[1]->text)->toBe('selamat pagi')
        ->and($segments[1]->startSeconds)->toBe(2.0)
        ->and($segments[2]->text)->toBe('selamat pagi')
        ->and($segments[2]->startSeconds)->toBe(30.0)
        ->and($segments[2]->endSeconds)->toBe(32.0)
        ->and($segments[3]->text)->toBe('vanakkam')
        ->and($segments[3]->language)->toBe(LanguageIdentifier::Tamil)
        ->and($segments[4]->text)->toBe('mystery tongue')
        ->and($segments[4]->language)->toBe(LanguageIdentifier::Undetermined)
        ->and($result->text)->toBe('hello world selamat pagi selamat pagi vanakkam mystery tongue')
        ->and($result->detectedLanguage)->toBe(LanguageIdentifier::Malay)
        ->and($result->speechDetected)->toBeTrue();

    // Deterministic ordering: starts non-decreasing.
    $starts = array_map(static fn ($segment): float => $segment->startSeconds, $segments);
    $ordered = $starts;
    sort($ordered);
    expect($starts)->toBe($ordered);
});

it('dedupes exact and 1 ms near-duplicate segments', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 'repeat after me', 'language' => 'en'],
            ['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 'repeat after me', 'language' => 'en'],
            ['relative_start_ms' => 1, 'relative_end_ms' => 1001, 'text' => 'repeat after me', 'language' => 'en'],
            ['relative_start_ms' => 2000, 'relative_end_ms' => 2500, 'text' => 'yeah', 'language' => 'en'],
            ['relative_start_ms' => 2500, 'relative_end_ms' => 2900, 'text' => 'yeah', 'language' => 'en'],
        ],
    );

    $result = pp3Adapter($transport)->transcribe(pp3Invocation());

    // Exact + near duplicates collapse to one; the two adjacent (non
    // overlapping, far-apart windows) "yeah" repetitions are preserved.
    expect(count($result->segments))->toBe(3)
        ->and($result->text)->toBe('repeat after me yeah yeah');
});

it('trims overlapping later segments per keep-earlier/drop-later', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 2000, 'text' => 'first part', 'language' => 'en'],
            ['relative_start_ms' => 1000, 'relative_end_ms' => 3000, 'text' => 'second part', 'language' => 'en'],
        ],
    );

    $result = pp3Adapter($transport)->transcribe(pp3Invocation());

    expect(count($result->segments))->toBe(2)
        ->and($result->segments[1]->startSeconds)->toBe(2.0)
        ->and($result->segments[1]->endSeconds)->toBe(3.0);
});

it('AC4: drop-injected fixture fails closed with no partial persist', function () {
    $scenario = TranscriptionFixtures::scenario();
    $segmentsBefore = TranscriptionSegment::count();

    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 'lost words', 'language' => 'en'],
        ],
    );
    $transport->dropped[0] = true;

    $invocation = TranscriptionInvocation::create(
        transcriptionId: $scenario['transcription']->id,
        processingAttemptId: $scenario['attempt']->id,
        media: new TranscriptionMedia(
            storageKey: 'media/pp3-drop.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 512000,
            durationSeconds: 12.0,
        ),
    );

    try {
        pp3Adapter($transport)->transcribe($invocation);
        expect(false)->toBeTrue('expected TranscriptionException was not thrown');
    } catch (TranscriptionException $e) {
        expect($e->failure)->toBe(TranscriptionFailure::InvalidWorkerResponse)
            ->and($e->failure->isRetryable())->toBeFalse();
    }

    expect(TranscriptionSegment::count())->toBe($segmentsBefore)
        ->and($scenario['transcription']->fresh()->full_text)->toBeNull();
});

it('AC5: maps every contracted error condition to the frozen taxonomy', function () {
    $cases = [
        [ReferenceExternalChunkFailureKind::Timeout, TranscriptionFailure::WorkerTimeout, true],
        [ReferenceExternalChunkFailureKind::RateLimited, TranscriptionFailure::WorkerSaturated, true],
        [ReferenceExternalChunkFailureKind::ServerError, TranscriptionFailure::WorkerUnavailable, true],
        [ReferenceExternalChunkFailureKind::Unreachable, TranscriptionFailure::WorkerUnavailable, true],
        [ReferenceExternalChunkFailureKind::Partial, TranscriptionFailure::InvalidWorkerResponse, false],
        [ReferenceExternalChunkFailureKind::Invalid, TranscriptionFailure::InvalidWorkerResponse, false],
        [ReferenceExternalChunkFailureKind::Unknown, TranscriptionFailure::InvalidWorkerResponse, false],
    ];

    foreach ($cases as [$kind, $expected, $retryable]) {
        $transport = new FakeReferenceExternalTransport;
        // Advisory hint deliberately contradicts the mapping on one case to
        // prove the vendor flag never controls domain retryability.
        $transport->script[0] = ReferenceExternalChunkResponse::failure($kind, $kind === ReferenceExternalChunkFailureKind::Partial);

        try {
            pp3Adapter($transport)->transcribe(pp3Invocation());
            expect(false)->toBeTrue("expected failure for [{$kind->value}]");
        } catch (TranscriptionException $e) {
            expect($e->failure)->toBe($expected)
                ->and($e->failure->isRetryable())->toBe($retryable);
        }
    }
});

it('rejects malformed responses fail-closed without fallback', function () {
    $malformed = [
        [['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'language' => 'en']],
        [['relative_start_ms' => 2000, 'relative_end_ms' => 1000, 'text' => 'backwards', 'language' => 'en']],
        [['relative_start_ms' => INF, 'relative_end_ms' => 1000, 'text' => 'infinite', 'language' => 'en']],
        [['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 42, 'language' => 'en']],
        ['not-an-array'],
        [['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => '6543', 'language' => ['en']]],
    ];

    foreach ($malformed as $index => $segments) {
        $transport = new FakeReferenceExternalTransport;
        $transport->script[0] = ReferenceExternalChunkResponse::success(segments: $segments);

        try {
            pp3Adapter($transport)->transcribe(pp3Invocation());
            expect(false)->toBeTrue("expected malformed rejection for case [{$index}]");
        } catch (TranscriptionException $e) {
            expect($e->failure)->toBe(TranscriptionFailure::InvalidWorkerResponse);
        }

        // No retry, no second-chunk continuation: exactly one dispatch.
        expect($transport->dispatchCount())->toBe(1);
    }
});

it('fails closed when speech is reported without segments', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(speechDetected: true, segments: []);

    try {
        pp3Adapter($transport)->transcribe(pp3Invocation());
        expect(false)->toBeTrue('expected TranscriptionException was not thrown');
    } catch (TranscriptionException $e) {
        expect($e->failure)->toBe(TranscriptionFailure::InvalidWorkerResponse);
    }
});

it('preserves the no-speech form end to end', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(speechDetected: false, segments: []);

    $result = pp3Adapter($transport)->transcribe(pp3Invocation(durationSeconds: 8.0));

    expect($result->speechDetected)->toBeFalse()
        ->and($result->text)->toBe('')
        ->and($result->segments)->toBe([])
        ->and($result->detectedLanguage)->toBe(LanguageIdentifier::Undetermined)
        ->and($result->durationSeconds)->toBe(8.0);
});

it('AC6: logs the checklist fields and never secrets, media, or text', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 'classified words here', 'language' => 'en'],
        ],
    );

    $invocation = pp3Invocation();
    $captured = pp3CaptureLogs(static function () use ($transport, $invocation): void {
        pp3Adapter($transport)->transcribe($invocation);
    });
    $payload = pp3LogPayloads($captured['records']);

    foreach (['request_id', 'chunk_id', 'provider_key', 'model_pinned', 'processing_attempt_id', 'attempt_seq', 'outcome', 'duration_ms'] as $field) {
        expect($payload)->toContain($field);
    }

    expect($payload)->toContain($invocation->requestId)
        ->and($payload)->toContain(PP3_PROVIDER_KEY)
        ->and($payload)->toContain(PP3_MODEL_PINNED)
        ->and($payload)->not->toContain(PP3_FIXTURE_TOKEN)
        ->and($payload)->not->toContain('Bearer')
        ->and($payload)->not->toContain('classified words here')
        ->and($payload)->not->toContain('media/pp3-fixture.mp3');
});

it('AC7: rejects oversized input before dispatch with MediaRejected', function () {
    $transport = new FakeReferenceExternalTransport;

    try {
        pp3Adapter($transport)->transcribe(pp3Invocation(
            fileSizeBytes: ReferenceExternalTranscriptionProvider::PRODUCT_LIMIT_BYTES + 1,
        ));
        expect(false)->toBeTrue('expected TranscriptionException was not thrown');
    } catch (TranscriptionException $e) {
        expect($e->failure)->toBe(TranscriptionFailure::MediaRejected)
            ->and($e->failure->isRetryable())->toBeFalse();
    }

    expect($transport->dispatchCount())->toBe(0);

    // Boundary: exactly the ceiling proceeds.
    $transport->script[0] = ReferenceExternalChunkResponse::success(speechDetected: false, segments: []);
    $result = pp3Adapter($transport)->transcribe(pp3Invocation(
        fileSizeBytes: ReferenceExternalTranscriptionProvider::PRODUCT_LIMIT_BYTES,
    ));

    expect($result->speechDetected)->toBeFalse()
        ->and($transport->dispatchCount())->toBe(1);
});

it('produces output compatible with the atomic persist path', function () {
    $scenario = TranscriptionFixtures::scenario();

    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(
        segments: [
            ['relative_start_ms' => 0, 'relative_end_ms' => 1500, 'text' => 'persist me', 'language' => 'en'],
            ['relative_start_ms' => 1500, 'relative_end_ms' => 3000, 'text' => 'simpan saya', 'language' => 'ms'],
        ],
    );

    $invocation = TranscriptionInvocation::create(
        transcriptionId: $scenario['transcription']->id,
        processingAttemptId: $scenario['attempt']->id,
        media: new TranscriptionMedia(
            storageKey: 'media/pp3-persist.mp3',
            mimeType: 'audio/mpeg',
            fileSizeBytes: 256000,
            durationSeconds: 12.0,
        ),
    );

    $result = pp3Adapter($transport)->transcribe($invocation);

    app(TranscriptionResultWriter::class)->persist(
        $scenario['transcription'],
        $scenario['attempt'],
        $result,
        PP3_MODEL_PINNED,
    );

    expect($scenario['transcription']->fresh()->status)->toBe(TranscriptionStatus::Completed)
        ->and($scenario['attempt']->fresh()->status)->toBe(ProcessingStatus::Completed)
        ->and(TranscriptionSegment::query()->where('transcription_id', $scenario['transcription']->id)->count())->toBe(2)
        ->and($scenario['transcription']->fresh()->full_text)->toBe('persist me simpan saya');
});

it('AC8: builds and tests with empty provider env secrets', function () {
    expect((string) config('transcription.worker_token'))->toBe('');

    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(speechDetected: false, segments: []);

    $result = pp3Adapter($transport)->transcribe(pp3Invocation());

    expect($result->speechDetected)->toBeFalse();
});

it('honors the 300-second provider ceiling and never silently extends it', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::success(speechDetected: false, segments: []);

    pp3Adapter($transport)->transcribe(pp3Invocation());
    expect($transport->requests[0]->timeoutSeconds)->toBe(300);

    $custom = new FakeReferenceExternalTransport;
    $custom->script[0] = ReferenceExternalChunkResponse::success(speechDetected: false, segments: []);
    pp3Adapter($custom, timeoutSeconds: 60)->transcribe(pp3Invocation());
    expect($custom->requests[0]->timeoutSeconds)->toBe(60);

    try {
        pp3Adapter(new FakeReferenceExternalTransport, timeoutSeconds: 600);
        expect(false)->toBeTrue('expected TranscriptionException was not thrown');
    } catch (TranscriptionException $e) {
        expect($e->failure)->toBe(TranscriptionFailure::ConfigurationError);
    }
});

it('fails closed on empty-token construction before any dispatch', function () {
    $transport = new FakeReferenceExternalTransport;

    try {
        new ReferenceExternalTranscriptionProvider(
            baseUrl: 'http://localhost:9/reference',
            token: '',
            providerKey: PP3_PROVIDER_KEY,
            modelPinned: PP3_MODEL_PINNED,
            transport: $transport,
        );
        expect(false)->toBeTrue('expected TranscriptionException was not thrown');
    } catch (TranscriptionException $e) {
        expect($e->failure)->toBe(TranscriptionFailure::ConfigurationError)
            ->and($e->failure->isRetryable())->toBeFalse();
    }

    expect($transport->dispatchCount())->toBe(0);
});

it('AC10: keeps chunk identity scoped to the parent attempt', function () {
    $first = new FakeReferenceExternalTransport;
    $first->script[0] = ReferenceExternalChunkResponse::success(
        segments: [['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 'one', 'language' => 'en']],
    );
    $second = new FakeReferenceExternalTransport;
    $second->script[0] = ReferenceExternalChunkResponse::success(
        segments: [['relative_start_ms' => 0, 'relative_end_ms' => 1000, 'text' => 'two', 'language' => 'en']],
    );

    // Same logical transcription, two distinct parent attempts (operator
    // retry creates a new attempt; the adapter never bumps attemptSeq itself).
    $firstInvocation = pp3Invocation(transcriptionId: 77, attemptId: 7);
    $secondInvocation = pp3Invocation(transcriptionId: 77, attemptId: 8);

    $captured = pp3CaptureLogs(static function () use ($first, $second, $firstInvocation, $secondInvocation): void {
        pp3Adapter($first)->transcribe($firstInvocation);
        pp3Adapter($second)->transcribe($secondInvocation);
    });
    $payload = pp3LogPayloads($captured['records']);

    expect($payload)->toContain($firstInvocation->requestId)
        ->and($payload)->toContain($secondInvocation->requestId)
        ->and($first->requests[0]->attemptSeq)->toBe(0)
        ->and($second->requests[0]->attemptSeq)->toBe(0)
        ->and($first->requests[0]->processingAttemptId)->toBe(7)
        ->and($second->requests[0]->processingAttemptId)->toBe(8)
        ->and($first->requests[0]->chunkId)->not->toBe($second->requests[0]->chunkId);

    // No second request identity is minted: every outbound request reuses the
    // invocation identity.
    foreach ([$first, $second] as $index => $fake) {
        $expected = $index === 0 ? $firstInvocation->requestId : $secondInvocation->requestId;
        foreach ($fake->requests as $request) {
            expect($request->requestId)->toBe($expected);
        }
    }
});

it('performs zero network I/O from the adapter boundary', function () {
    $sources = ['ReferenceExternalTranscriptionProvider.php', 'ReferenceExternalTransport.php', 'ReferenceExternalChunkRequest.php', 'ReferenceExternalChunkResponse.php', 'ReferenceExternalChunkFailureKind.php'];

    foreach ($sources as $file) {
        $code = (string) file_get_contents(app_path('Transcription/'.$file));

        foreach (['Http::', 'curl_', 'file_get_contents', 'fsockopen', 'stream_socket', 'Guzzle', 'Socket::', 'env('] as $forbidden) {
            expect($code)->not->toContain($forbidden, "forbidden network/secret-loading call [{$forbidden}] in [{$file}]");
        }
    }
});

it('rejects unknown failure shapes without inventing taxonomy cases', function () {
    $transport = new FakeReferenceExternalTransport;
    $transport->script[0] = ReferenceExternalChunkResponse::failure(
        ReferenceExternalChunkFailureKind::Unknown,
        true,
        'vendor said maybe',
    );

    try {
        pp3Adapter($transport)->transcribe(pp3Invocation());
        expect(false)->toBeTrue('expected TranscriptionException was not thrown');
    } catch (TranscriptionException $e) {
        // Advisory retryable hint is ignored: unknown stays non-retryable.
        expect($e->failure)->toBe(TranscriptionFailure::InvalidWorkerResponse)
            ->and($e->failure->isRetryable())->toBeFalse()
            ->and($e->getMessage())->toBe('vendor said maybe');
    }
});
