<?php

use App\Actions\TranscriptionResultWriter;
use App\Actions\TranscriptionRetry;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\NormalizedTranscripts;
use Tests\Support\RecordingTranscriptionProvider;
use Tests\Support\TranscriptionFixtures;

/*
 * P3-007 manual retry (ADR-018): same transcription, new attempt, manual-only.
 */

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * @return array{user: User, media: MediaFile, transcription: Transcription, attempt: ProcessingJob}
 */
function p3007FailedTranscription(
    TranscriptionFailure $failure = TranscriptionFailure::WorkerTimeout,
    bool $consistentOwnership = true,
): array {
    $fixture = TranscriptionFixtures::scenario(
        TranscriptionStatus::Failed,
        ProcessingStatus::Failed,
        consistentOwnership: $consistentOwnership,
    );

    $fixture['attempt']->forceFill([
        'failure_code' => $failure->value,
        'error_message' => 'Transcription worker did not complete in time.',
        'completed_at' => now(),
    ])->save();

    return $fixture;
}

function p3007Run(Transcription $transcription, ProcessingJob $attempt, $provider): void
{
    (new ProcessTranscription($transcription->getKey(), $attempt->getKey()))
        ->handle($provider, app(TranscriptionResultWriter::class));
}

test('a retryable failed transcription can be retried and creates exactly one new attempt', function () {
    Queue::fake();

    ['transcription' => $transcription, 'attempt' => $attempt] = p3007FailedTranscription();

    $newAttempt = app(TranscriptionRetry::class)->retry($transcription);

    expect($newAttempt)->toBeInstanceOf(ProcessingJob::class)
        ->and($newAttempt->getKey())->not->toBe($attempt->getKey())
        ->and($newAttempt->status)->toBe(ProcessingStatus::Queued)
        ->and($newAttempt->transcription_id)->toBe($transcription->getKey())
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Failed)
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(2);

    Queue::assertPushed(
        ProcessTranscription::class,
        fn (ProcessTranscription $job): bool => $job->transcriptionId === $transcription->getKey()
            && $job->processingAttemptId === $newAttempt->getKey(),
    );
});

test('a non-retryable failed transcription cannot be retried', function () {
    Queue::fake();

    ['transcription' => $transcription] = p3007FailedTranscription(TranscriptionFailure::ProcessingFailed);

    expect(app(TranscriptionRetry::class)->isEligible($transcription))->toBeFalse();

    expect(fn () => app(TranscriptionRetry::class)->retry($transcription))
        ->toThrow(TranscriptionException::class);

    expect($transcription->refresh()->status)->toBe(TranscriptionStatus::Failed)
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1);

    Queue::assertNothingPushed();
});

test('retry preserves the same transcription identity and the historical failed attempt', function () {
    Queue::fake();

    ['transcription' => $transcription, 'attempt' => $failedAttempt] = p3007FailedTranscription();

    $originalId = $transcription->getKey();
    $failedAt = $failedAttempt->completed_at;

    $newAttempt = app(TranscriptionRetry::class)->retry($transcription);

    expect($transcription->refresh()->getKey())->toBe($originalId)
        ->and($newAttempt->transcription_id)->toBe($originalId);

    $failedAttempt->refresh();

    expect($failedAttempt->status)->toBe(ProcessingStatus::Failed)
        ->and($failedAttempt->failure_code)->toBe(TranscriptionFailure::WorkerTimeout)
        ->and($failedAttempt->completed_at->equalTo($failedAt))->toBeTrue();
});

test('repeated retry requests do not create a duplicate active attempt', function () {
    Queue::fake();

    ['transcription' => $transcription] = p3007FailedTranscription();

    $retry = app(TranscriptionRetry::class);
    $first = $retry->retry($transcription);
    $second = $retry->retry($transcription->refresh());

    expect($second->getKey())->toBe($first->getKey())
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(2);

    Queue::assertPushed(ProcessTranscription::class, 1);
});

test('a completed transcription cannot be retried and is never overwritten', function () {
    Queue::fake();

    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Completed,
        ProcessingStatus::Completed,
    );

    $transcription->forceFill([
        'full_text' => 'Original transcript.',
        'detected_language' => 'en',
    ])->save();

    expect(app(TranscriptionRetry::class)->isEligible($transcription))->toBeFalse();

    expect(fn () => app(TranscriptionRetry::class)->retry($transcription))
        ->toThrow(TranscriptionException::class);

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Original transcript.')
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1);

    Queue::assertNothingPushed();
});

test('retry for an already-active transcription is an idempotent no-op', function () {
    Queue::fake();

    ['transcription' => $transcription, 'attempt' => $activeAttempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    $returned = app(TranscriptionRetry::class)->retry($transcription);

    expect($returned->getKey())->toBe($activeAttempt->getKey())
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1);

    Queue::assertNothingPushed();
});

test('retry cannot cross the user ownership boundary', function () {
    Queue::fake();

    ['transcription' => $transcription] = p3007FailedTranscription(consistentOwnership: false);

    expect(fn () => app(TranscriptionRetry::class)->retry($transcription))
        ->toThrow(TranscriptionException::class);

    expect(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1);

    Queue::assertNothingPushed();
});

test('a retried attempt can complete successfully', function () {
    Queue::fake();

    ['transcription' => $transcription, 'attempt' => $failedAttempt] = p3007FailedTranscription();

    $newAttempt = app(TranscriptionRetry::class)->retry($transcription);

    p3007Run($transcription->refresh(), $newAttempt, new RecordingTranscriptionProvider(NormalizedTranscripts::multilingual()));

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Selamat datang. Welcome. 欢迎. வணக்கம்.')
        ->and($transcription->segments()->count())->toBe(4)
        ->and($newAttempt->refresh()->status)->toBe(ProcessingStatus::Completed)
        ->and($failedAttempt->refresh()->status)->toBe(ProcessingStatus::Failed);
});

test('a second retryable failure remains valid and retryable', function () {
    Queue::fake();

    ['transcription' => $transcription] = p3007FailedTranscription();

    $newAttempt = app(TranscriptionRetry::class)->retry($transcription);

    p3007Run(
        $transcription->refresh(),
        $newAttempt,
        new RecordingTranscriptionProvider(exception: new TranscriptionException(TranscriptionFailure::WorkerTimeout)),
    );

    $transcription->refresh();
    $newAttempt->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Failed)
        ->and($newAttempt->status)->toBe(ProcessingStatus::Failed)
        ->and($newAttempt->failure_code)->toBe(TranscriptionFailure::WorkerTimeout)
        ->and(app(TranscriptionRetry::class)->isEligible($transcription))->toBeTrue()
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(2);
});

test('domain retry does not depend on laravel transport retry', function () {
    Queue::fake();

    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    p3007Run(
        $transcription,
        $attempt,
        new RecordingTranscriptionProvider(exception: new TranscriptionException(TranscriptionFailure::WorkerUnavailable)),
    );

    // The transport job is single-attempt; a domain failure never silently
    // creates a new domain retry attempt.
    expect((new ProcessTranscription(1, 1))->tries)->toBe(1)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Failed)
        ->and(ProcessingJob::query()->where('transcription_id', $transcription->getKey())->count())->toBe(1);
});

test('the database enforces at most one active attempt per transcription', function () {
    ['transcription' => $transcription] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Queued,
        ProcessingStatus::Queued,
    );

    expect(fn () => ProcessingJob::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'stage' => ProcessingStage::Transcribe,
        'status' => ProcessingStatus::Queued,
        'progress_percentage' => 0,
        'started_at' => null,
        'completed_at' => null,
        'processing_seconds' => null,
        'error_message' => null,
        'failure_code' => null,
    ]))->toThrow(QueryException::class);
});
