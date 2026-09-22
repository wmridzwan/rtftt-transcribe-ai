<?php

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Transcription\TranscriptionInvocation;
use App\Transcription\TranscriptionProvider;
use App\Transcription\TranscriptSegmentData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\RecordingTranscriptionProvider;
use Tests\Support\TranscriptionFixtures;

beforeEach(function (): void {
    Storage::fake('local');
});

function p3006RealisticResult(): NormalizedTranscript
{
    return new NormalizedTranscript(
        text: 'Selamat datang. Welcome. 欢迎. வணக்கம்.',
        detectedLanguage: LanguageIdentifier::Malay,
        durationSeconds: 12.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 3.0, 'Selamat datang.', LanguageIdentifier::Malay),
            new TranscriptSegmentData(1, 3.0, 6.0, 'Welcome.', LanguageIdentifier::English),
            new TranscriptSegmentData(2, 6.0, 9.0, '欢迎.', LanguageIdentifier::Chinese),
            new TranscriptSegmentData(3, 9.0, 12.0, 'வணக்கம்.', LanguageIdentifier::Tamil),
        ],
    );
}

function p3006Run(Transcription $transcription, ProcessingJob $attempt, TranscriptionProvider $provider): void
{
    (new ProcessTranscription($transcription->getKey(), $attempt->getKey()))
        ->handle($provider, app(TranscriptionResultWriter::class));
}

test('successful delivery persists transcript and segments and completes the attempt', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);

    $transcription->refresh();

    expect($provider->callCount())->toBe(1)
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Selamat datang. Welcome. 欢迎. வணக்கம்.')
        ->and($transcription->detected_language)->toBe('ms')
        ->and($transcription->speech_detected)->toBeTrue()
        ->and($transcription->segments()->count())->toBe(4)
        ->and($transcription->segments()->pluck('language')->map->value->all())->toBe(['ms', 'en', 'zh', 'ta'])
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Completed);
});

test('the invocation carries server identifiers and an opaque media reference', function () {
    ['transcription' => $transcription, 'attempt' => $attempt, 'media' => $media] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);

    $invocation = $provider->invocations[0];

    expect($invocation->transcriptionId)->toBe($transcription->getKey())
        ->and($invocation->processingAttemptId)->toBe($attempt->getKey())
        ->and($invocation->media->storageKey)->toBe($media->storage_path)
        ->and($invocation->media->storageKey)->not->toStartWith('/')
        ->and($invocation->options->requestedLanguage)->toBe(LanguageIdentifier::English)
        ->and($invocation->requestId)->not->toBe('');
});

test('the transcription is transcribing when the provider is invoked', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $provider = new class implements TranscriptionProvider
    {
        public ?TranscriptionStatus $statusAtInvocation = null;

        public function transcribe(TranscriptionInvocation $invocation): NormalizedTranscript
        {
            $this->statusAtInvocation = Transcription::query()->find($invocation->transcriptionId)?->status;

            return p3006RealisticResult();
        }
    };

    p3006Run($transcription, $attempt, $provider);

    expect($provider->statusAtInvocation)->toBe(TranscriptionStatus::Transcribing);
});

test('worker inference is invoked outside any database transaction', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    // RefreshDatabase already holds the test-level transaction. The job must
    // not add its own transaction around the long-running provider call.
    $baseline = DB::transactionLevel();

    $provider = new class($baseline) implements TranscriptionProvider
    {
        public ?int $transactionLevelAtInvocation = null;

        public function __construct(private readonly int $baseline) {}

        public function transcribe(TranscriptionInvocation $invocation): NormalizedTranscript
        {
            $this->transactionLevelAtInvocation = DB::transactionLevel();

            return p3006RealisticResult();
        }
    };

    p3006Run($transcription, $attempt, $provider);

    expect($provider->transactionLevelAtInvocation)->toBe($baseline);
});

test('a missing private storage object fails safely without invoking the provider', function () {
    ['transcription' => $transcription, 'attempt' => $attempt, 'media' => $media] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
        withPhysicalFile: false,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);

    $transcription->refresh();

    expect($provider->callCount())->toBe(0)
        ->and($transcription->status)->toBe(TranscriptionStatus::Failed)
        ->and($transcription->completed_at)->not->toBeNull()
        ->and($transcription->error_message)->not->toContain($media->storage_path)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Failed);
});

test('an inconsistent media ownership relationship fails safely', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
        consistentOwnership: false,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);

    expect($provider->callCount())->toBe(0)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Failed);
});

test('a cancelled transcription is never processed', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Cancelled,
        ProcessingStatus::Queued,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);

    expect($provider->callCount())->toBe(0)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Cancelled)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Queued);
});

test('a completed transcription is not reprocessed', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Completed,
        ProcessingStatus::Completed,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);

    expect($provider->callCount())->toBe(0)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Completed);
});

test('duplicate delivery does not duplicate inference, transcript or segments', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);
    p3006Run($transcription, $attempt, $provider);

    expect($provider->callCount())->toBe(1)
        ->and($transcription->refresh()->segments()->count())->toBe(4)
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed);
});

test('an attempt already claimed by another worker is not processed', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Running,
    );

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $attempt, $provider);

    expect($provider->callCount())->toBe(0)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Running);
});

test('a stale attempt is ignored while the newer attempt processes', function () {
    // Under the P3-007 one-active-attempt invariant a superseded attempt is
    // terminal (failed) while the newer attempt is active. The stale delivery
    // must still be a no-op and must not touch the newer authoritative state.
    ['transcription' => $transcription, 'attempt' => $oldAttempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Failed,
    );

    $newAttempt = ProcessingJob::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'stage' => ProcessingStage::Transcribe,
        'status' => ProcessingStatus::Queued,
        'progress_percentage' => 0,
        'started_at' => null,
        'completed_at' => null,
        'processing_seconds' => null,
        'error_message' => null,
    ]);

    $provider = new RecordingTranscriptionProvider(p3006RealisticResult());

    p3006Run($transcription, $oldAttempt, $provider);

    expect($provider->callCount())->toBe(0)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued);

    p3006Run($transcription, $newAttempt, $provider);

    expect($provider->callCount())->toBe(1)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Completed);
});

test('a provider failure leaves a valid terminal state', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $provider = new RecordingTranscriptionProvider(
        exception: new TranscriptionException(TranscriptionFailure::WorkerUnavailable),
    );

    p3006Run($transcription, $attempt, $provider);

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Failed)
        ->and($transcription->error_message)->toBe('Transcription worker unavailable.')
        ->and($transcription->completed_at)->not->toBeNull()
        ->and($transcription->segments()->count())->toBe(0)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Failed);
});

test('a persistence failure never produces a false completion', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    TranscriptionSegment::creating(function (): void {
        throw new RuntimeException('Injected persistence failure.');
    });

    try {
        p3006Run($transcription, $attempt, new RecordingTranscriptionProvider(p3006RealisticResult()));
    } finally {
        TranscriptionSegment::flushEventListeners();
    }

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Failed)
        ->and($transcription->full_text)->toBeNull()
        ->and($transcription->completed_at)->not->toBeNull()
        ->and($transcription->segments()->count())->toBe(0)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Failed);
});
