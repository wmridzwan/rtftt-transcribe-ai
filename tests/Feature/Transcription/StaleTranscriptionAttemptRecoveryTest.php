<?php

use App\Actions\StaleTranscriptionAttemptRecovery;
use App\Actions\TranscriptionResultWriter;
use App\Actions\TranscriptionRetry;
use App\Enums\ProcessingStage;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionFailure;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\NormalizedTranscripts;
use Tests\Support\TranscriptionFixtures;

/*
 * P3-007 abandoned running-attempt recovery (ADR-018 / B3-05).
 */

beforeEach(function (): void {
    Storage::fake('local');
    config([
        'transcription.timeout_seconds' => 300,
        'transcription.attempt_stale_seconds' => null,
    ]);
});

/**
 * @return array{user: User, media: MediaFile, transcription: Transcription, attempt: ProcessingJob}
 */
function p3007RunningAttempt(CarbonInterface $startedAt): array
{
    $fixture = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $fixture['attempt']->forceFill(['started_at' => $startedAt])->save();

    return $fixture;
}

test('a fresh running attempt is not recovered', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = p3007RunningAttempt(now()->subSeconds(100));

    expect(app(StaleTranscriptionAttemptRecovery::class)->recover())->toBe(0)
        ->and($attempt->refresh()->status)->toBe(ProcessingStatus::Running)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Transcribing);
});

test('a stale running attempt is recovered to a recoverable failure', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = p3007RunningAttempt(now()->subSeconds(400));

    expect(app(StaleTranscriptionAttemptRecovery::class)->recover())->toBe(1);

    expect($attempt->refresh()->status)->toBe(ProcessingStatus::Failed)
        ->and($attempt->failure_code)->toBe(TranscriptionFailure::WorkerTimeout)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Failed)
        ->and(app(TranscriptionRetry::class)->isEligible($transcription))->toBeTrue();
});

test('recovery does not automatically run inference', function () {
    Queue::fake();

    p3007RunningAttempt(now()->subSeconds(400));

    app(StaleTranscriptionAttemptRecovery::class)->recover();

    Queue::assertNothingPushed();
});

test('a recovered failure becomes eligible for manual retry', function () {
    Queue::fake();

    ['transcription' => $transcription] = p3007RunningAttempt(now()->subSeconds(400));

    app(StaleTranscriptionAttemptRecovery::class)->recover();

    $transcription->refresh();

    expect(app(TranscriptionRetry::class)->isEligible($transcription))->toBeTrue();

    $newAttempt = app(TranscriptionRetry::class)->retry($transcription);

    expect($newAttempt->status)->toBe(ProcessingStatus::Queued)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued);
});

test('the stale threshold is derived from the provider timeout with an explicit safety margin', function () {
    expect(app(StaleTranscriptionAttemptRecovery::class)->staleThresholdSeconds())->toBe(360);

    config(['transcription.attempt_stale_seconds' => 42]);

    expect(app(StaleTranscriptionAttemptRecovery::class)->staleThresholdSeconds())->toBe(42);
});

test('an attempt just inside the threshold is not recovered but one at the threshold is', function () {
    $now = now();
    $threshold = app(StaleTranscriptionAttemptRecovery::class)->staleThresholdSeconds();

    ['attempt' => $insideAttempt] = p3007RunningAttempt($now->copy()->subSeconds($threshold - 1));

    expect(app(StaleTranscriptionAttemptRecovery::class)->recover($now))->toBe(0)
        ->and($insideAttempt->refresh()->status)->toBe(ProcessingStatus::Running);

    ['attempt' => $atThresholdAttempt] = p3007RunningAttempt($now->copy()->subSeconds($threshold));

    expect(app(StaleTranscriptionAttemptRecovery::class)->recover($now))->toBe(1)
        ->and($atThresholdAttempt->refresh()->status)->toBe(ProcessingStatus::Failed);
});

test('recovery never overwrites a completed transcription', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = p3007RunningAttempt(now()->subSeconds(400));

    $transcription->forceFill([
        'status' => TranscriptionStatus::Completed,
        'full_text' => 'Completed result.',
        'completed_at' => now(),
    ])->save();

    app(StaleTranscriptionAttemptRecovery::class)->recover();

    expect($attempt->refresh()->status)->toBe(ProcessingStatus::Failed)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Completed result.');
});

test('an obsolete running attempt is failed without touching newer authoritative state', function () {
    ['transcription' => $transcription, 'attempt' => $staleAttempt] = p3007RunningAttempt(now()->subSeconds(400));

    ProcessingJob::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'stage' => ProcessingStage::Transcribe,
        'status' => ProcessingStatus::Completed,
        'progress_percentage' => 100,
        'started_at' => now()->subMinutes(10),
        'completed_at' => now()->subMinutes(9),
        'processing_seconds' => 60,
        'error_message' => null,
        'failure_code' => null,
    ]);

    $transcription->forceFill([
        'status' => TranscriptionStatus::Completed,
        'full_text' => 'Newer result.',
    ])->save();

    app(StaleTranscriptionAttemptRecovery::class)->recover();

    expect($staleAttempt->refresh()->status)->toBe(ProcessingStatus::Failed)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->full_text)->toBe('Newer result.');
});

test('an obsolete worker cannot overwrite newer state after recovery and retry', function () {
    Queue::fake();

    ['transcription' => $transcription, 'attempt' => $oldAttempt] = p3007RunningAttempt(now()->subSeconds(400));

    app(StaleTranscriptionAttemptRecovery::class)->recover();

    $oldAttempt->refresh();
    expect($oldAttempt->status)->toBe(ProcessingStatus::Failed);

    $newAttempt = app(TranscriptionRetry::class)->retry($transcription->refresh());

    // The obsolete worker resumes and attempts to persist its stale result.
    app(TranscriptionResultWriter::class)->persist(
        $transcription->refresh(),
        $oldAttempt,
        NormalizedTranscripts::multilingual(),
        'large-v3',
    );

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Queued)
        ->and($transcription->full_text)->toBeNull()
        ->and($transcription->segments()->count())->toBe(0)
        ->and($oldAttempt->refresh()->status)->toBe(ProcessingStatus::Failed)
        ->and($newAttempt->refresh()->status)->toBe(ProcessingStatus::Queued);
});
