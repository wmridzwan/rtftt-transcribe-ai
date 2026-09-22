<?php

use App\Actions\TranscriptionOrchestrator;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\ProcessingJob;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use App\Transcription\TranscriptionProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\NormalizedTranscripts;
use Tests\Support\RecordingTranscriptionProvider;
use Tests\Support\TranscriptionFixtures;

beforeEach(function (): void {
    Storage::fake('local');
});

test('requesting transcription queues work without invoking the provider in the request', function () {
    Queue::fake();

    ['transcription' => $transcription] = TranscriptionFixtures::ownedTranscription(TranscriptionStatus::Draft);

    $attempt = app(TranscriptionOrchestrator::class)->request($transcription);

    expect($attempt)->toBeInstanceOf(ProcessingJob::class)
        ->and($attempt->status)->toBe(ProcessingStatus::Queued)
        ->and($attempt->transcription_id)->toBe($transcription->getKey())
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued);

    Queue::assertPushedOn(
        'transcription',
        ProcessTranscription::class,
        fn (ProcessTranscription $job): bool => $job->transcriptionId === $transcription->getKey()
            && $job->processingAttemptId === $attempt->getKey(),
    );
});

test('repeated requests reuse the queued processing attempt', function () {
    Queue::fake();

    ['transcription' => $transcription] = TranscriptionFixtures::ownedTranscription(TranscriptionStatus::Draft);

    $orchestrator = app(TranscriptionOrchestrator::class);
    $first = $orchestrator->request($transcription);
    $second = $orchestrator->request($transcription);

    expect($second->getKey())->toBe($first->getKey())
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1);
});

test('terminal transcriptions cannot be requested again', function (TranscriptionStatus $status) {
    ['transcription' => $transcription] = TranscriptionFixtures::ownedTranscription($status);

    expect(fn () => app(TranscriptionOrchestrator::class)->request($transcription))
        ->toThrow(TranscriptionException::class);
})->with([
    'completed' => [TranscriptionStatus::Completed],
    'failed' => [TranscriptionStatus::Failed],
    'cancelled' => [TranscriptionStatus::Cancelled],
]);

test('the configured queue connection executes the job end to end', function () {
    $provider = new RecordingTranscriptionProvider(NormalizedTranscripts::multilingual());
    $this->app->instance(TranscriptionProvider::class, $provider);

    config([
        'transcription.queue_connection' => 'database',
        'transcription.queue' => 'transcription',
    ]);

    ['transcription' => $transcription, 'media' => $media] = TranscriptionFixtures::ownedTranscription(TranscriptionStatus::Draft);

    $attempt = app(TranscriptionOrchestrator::class)->request($transcription);

    expect(DB::table('jobs')->count())->toBe(1);

    $queued = DB::table('jobs')->first();
    $payload = (string) $queued->payload;

    expect($queued->queue)->toBe('transcription')
        ->and($payload)->not->toContain($media->storage_path)
        ->and($payload)->not->toContain('audio-bytes')
        ->and($payload)->toContain((string) $transcription->getKey())
        ->and($payload)->toContain((string) $attempt->getKey());

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--queue' => 'transcription',
    ])->run();

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Selamat datang. Welcome. 欢迎. வணக்கம்.')
        ->and($transcription->detected_language)->toBe('ms')
        ->and($transcription->segments()->count())->toBe(4)
        ->and($provider->callCount())->toBe(1)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Completed);
});

test('duplicate queue delivery does not duplicate inference or segments', function () {
    $provider = new RecordingTranscriptionProvider(NormalizedTranscripts::multilingual());
    $this->app->instance(TranscriptionProvider::class, $provider);

    config([
        'transcription.queue_connection' => 'database',
        'transcription.queue' => 'transcription',
    ]);

    ['transcription' => $transcription] = TranscriptionFixtures::ownedTranscription(TranscriptionStatus::Draft);

    $orchestrator = app(TranscriptionOrchestrator::class);
    $attempt = $orchestrator->request($transcription);
    $orchestrator->dispatch($attempt);

    expect(DB::table('jobs')->count())->toBe(2);

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--queue' => 'transcription',
    ])->run();

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--queue' => 'transcription',
    ])->run();

    $transcription->refresh();

    expect($provider->callCount())->toBe(1)
        ->and($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->segments()->count())->toBe(4)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('a stale queued attempt cannot overwrite a newer attempt result', function () {
    $provider = new RecordingTranscriptionProvider(NormalizedTranscripts::multilingual());
    $this->app->instance(TranscriptionProvider::class, $provider);

    config([
        'transcription.queue_connection' => 'database',
        'transcription.queue' => 'transcription',
    ]);

    ['transcription' => $transcription] = TranscriptionFixtures::ownedTranscription(TranscriptionStatus::Draft);

    $orchestrator = app(TranscriptionOrchestrator::class);
    $staleAttempt = $orchestrator->request($transcription);

    // Under the P3-007 one-active-attempt invariant a superseded attempt is
    // terminal while the newer attempt is active.
    $staleAttempt->forceFill([
        'status' => ProcessingStatus::Failed,
        'completed_at' => now(),
        'failure_code' => TranscriptionFailure::WorkerTimeout->value,
    ])->save();

    $newerAttempt = ProcessingJob::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'stage' => ProcessingStage::Transcribe,
        'status' => ProcessingStatus::Queued,
        'progress_percentage' => 0,
        'started_at' => null,
        'completed_at' => null,
        'processing_seconds' => null,
        'error_message' => null,
        'failure_code' => null,
    ]);

    $orchestrator->dispatch($newerAttempt);

    // Deliver the stale attempt first, then the newer authoritative attempt.
    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--queue' => 'transcription',
    ])->run();

    $this->artisan('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--queue' => 'transcription',
    ])->run();

    expect($provider->callCount())->toBe(1)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Completed)
        ->and($newerAttempt->refresh()->status)->toBe(ProcessingStatus::Completed)
        ->and($staleAttempt->refresh()->status)->toBe(ProcessingStatus::Failed);
});

test('a Redis outage is surfaced safely without corrupting state', function () {
    config([
        'transcription.queue_connection' => 'redis',
        'transcription.queue' => 'transcription',
        'database.redis.default.host' => '127.0.0.1',
        'database.redis.default.port' => 1,
        'database.redis.default.password' => null,
        'database.redis.options.prefix' => '',
    ]);

    ['transcription' => $transcription] = TranscriptionFixtures::ownedTranscription(TranscriptionStatus::Draft);

    $threw = false;

    try {
        app(TranscriptionOrchestrator::class)->request($transcription);
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue()
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and($transcription->full_text)->toBeNull()
        ->and($transcription->completed_at)->toBeNull();
});
