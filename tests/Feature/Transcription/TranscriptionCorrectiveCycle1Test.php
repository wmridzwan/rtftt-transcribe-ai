<?php

use App\Actions\TranscriptionOrchestrator;
use App\Actions\TranscriptionRetry;
use App\Enums\MediaStatus;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Jobs\ProcessTranscription;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionException;
use App\Transcription\TranscriptionFailure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
 * P7-009-CORR-01 corrective cycle 1 (HPO DECISION-P7-009-CORR-01-CYCLE1-001).
 *
 * F-1 (MEDIUM): a queued-but-undispatched attempt must have a safe,
 * authorized, idempotent UI resume path that reuses the existing rows.
 * F-2 (LOW): retry must respect the Start serialization boundary — no
 * competing active transcription for one media file.
 *
 * Concurrency note: SQLite cannot reproduce row locking (`lockForUpdate()` is
 * a no-op there), so these tests prove the *decision logic* and the atomic
 * rollback deterministically on one connection. The PostgreSQL interleavings
 * (Start ∥ Retry, Retry ∥ Retry) are argued in the `TranscriptionRetry` class
 * docblock and remain a target-host check; they are not executed here.
 */

beforeEach(function (): void {
    Storage::fake('local');
});

function corr01Media(?User $owner = null): MediaFile
{
    $media = MediaFile::factory()->create([
        'user_id' => ($owner ?? User::factory()->create())->id,
        'storage_path' => 'media/'.Str::uuid().'.mp3',
        'extension' => 'mp3',
        'mime_type' => 'audio/mpeg',
        'status' => MediaStatus::Uploaded,
    ]);

    Storage::disk((string) config('media.storage_disk'))->put($media->storage_path, 'audio-bytes');

    return $media;
}

function corr01RetryableFailed(MediaFile $media, User $owner): Transcription
{
    $transcription = Transcription::factory()->create([
        'user_id' => $owner->id,
        'media_file_id' => $media->id,
        'status' => TranscriptionStatus::Failed,
    ]);

    ProcessingJob::factory()->failed()->create([
        'transcription_id' => $transcription->id,
        'status' => ProcessingStatus::Failed,
        'failure_code' => TranscriptionFailure::WorkerTimeout->value,
    ]);

    return $transcription->refresh();
}

function corr01ActiveTranscription(MediaFile $media, User $owner): Transcription
{
    $active = Transcription::factory()->queued()->create([
        'user_id' => $owner->id,
        'media_file_id' => $media->id,
    ]);

    ProcessingJob::factory()->create([
        'transcription_id' => $active->id,
        'status' => ProcessingStatus::Queued,
    ]);

    return $active;
}

/**
 * Drives the real Start request while the queue push fails after the database
 * commit, leaving the production "stranded" state: Transcription=Queued,
 * ProcessingJob=Queued, nothing on the queue.
 */
function corr01StrandWithFailedDispatch(mixed $test, User $owner, MediaFile $media): mixed
{
    $failing = Mockery::mock(TranscriptionOrchestrator::class, [app(DatabaseManager::class)])->makePartial();
    $failing->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('queue down'));
    app()->instance(TranscriptionOrchestrator::class, $failing);

    $response = $test->actingAs($owner)->post(route('media.transcriptions.store', $media));

    app()->forgetInstance(TranscriptionOrchestrator::class);

    return $response;
}

test('a dispatch failure after commit redirects to the stranded attempt instead of returning a 500', function () {
    Exceptions::fake();
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);

    $response = corr01StrandWithFailedDispatch($this, $owner, $media);

    $stranded = Transcription::query()->sole();
    $response->assertRedirect(route('transcriptions.show', $stranded))
        ->assertSessionHas('error');
    expect($stranded->status)->toBe(TranscriptionStatus::Queued)
        ->and($stranded->media_file_id)->toBe($media->id)
        ->and(ProcessingJob::query()->sole()->status)->toBe(ProcessingStatus::Queued);
    Queue::assertNothingPushed();
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'queue down');
});

test('resuming a stranded attempt re-dispatches the same transcription and attempt without creating rows', function () {
    Exceptions::fake();
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    corr01StrandWithFailedDispatch($this, $owner, $media);
    $transcription = Transcription::query()->sole();
    $attempt = ProcessingJob::query()->sole();

    $this->get(route('media.show', $media))
        ->assertSee('Resume Transcription')
        ->assertDontSee('Start Transcription')
        ->assertSee(route('media.transcriptions.store', $media), false);
    $this->get(route('transcriptions.show', $transcription))
        ->assertSee('Resume Transcription')
        ->assertSee(route('media.transcriptions.store', $media), false);

    $this->post(route('media.transcriptions.store', $media))
        ->assertRedirect(route('transcriptions.show', $transcription));

    expect(Transcription::query()->sole()->is($transcription))->toBeTrue()
        ->and(ProcessingJob::query()->sole()->is($attempt))->toBeTrue()
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued);
    Queue::assertPushed(
        ProcessTranscription::class,
        fn (ProcessTranscription $job): bool => $job->transcriptionId === $transcription->id
            && $job->processingAttemptId === $attempt->id,
    );
    Queue::assertPushedTimes(ProcessTranscription::class, 1);
});

test('repeating resume stays idempotent and only ever re-dispatches the one attempt', function () {
    Exceptions::fake();
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    corr01StrandWithFailedDispatch($this, $owner, $media);
    $transcription = Transcription::query()->sole();
    $attempt = ProcessingJob::query()->sole();

    $this->post(route('media.transcriptions.store', $media))
        ->assertRedirect(route('transcriptions.show', $transcription));
    $this->post(route('media.transcriptions.store', $media))
        ->assertRedirect(route('transcriptions.show', $transcription));

    expect(Transcription::query()->count())->toBe(1)
        ->and(ProcessingJob::query()->count())->toBe(1)
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and(Queue::pushed(ProcessTranscription::class)->pluck('processingAttemptId')->unique()->all())->toBe([$attempt->id]);
});

test('resuming a Draft transcription that never reached the orchestrator queues it on the same row', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $transcription = Transcription::factory()->draft()->create([
        'user_id' => $owner->id,
        'media_file_id' => $media->id,
    ]);

    $this->actingAs($owner)->get(route('media.show', $media))
        ->assertSee('Resume Transcription')
        ->assertDontSee('Start Transcription');

    $this->post(route('media.transcriptions.store', $media))
        ->assertRedirect(route('transcriptions.show', $transcription));

    expect(Transcription::query()->sole()->is($transcription))->toBeTrue()
        ->and($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and(ProcessingJob::query()->sole()->transcription_id)->toBe($transcription->id);
    Queue::assertPushedTimes(ProcessTranscription::class, 1);
});

test('resume is offered only while the attempt is Draft or Queued', function (TranscriptionStatus $status) {
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $transcription = Transcription::factory()->create([
        'user_id' => $owner->id,
        'media_file_id' => $media->id,
        'status' => $status,
    ]);

    $this->actingAs($owner)->get(route('media.show', $media))
        ->assertDontSee('Resume Transcription');
    $this->get(route('transcriptions.show', $transcription))
        ->assertDontSee('Resume Transcription');
})->with([
    'preparing' => TranscriptionStatus::Preparing,
    'transcribing' => TranscriptionStatus::Transcribing,
    'completed' => TranscriptionStatus::Completed,
    'failed' => TranscriptionStatus::Failed,
    'cancelled' => TranscriptionStatus::Cancelled,
]);

test('a non-owner cannot resume another user stranded attempt and nothing is dispatched', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    corr01ActiveTranscription($media, $owner);

    $this->actingAs(User::factory()->create())
        ->post(route('media.transcriptions.store', $media))
        ->assertForbidden();

    expect(Transcription::query()->count())->toBe(1)
        ->and(ProcessingJob::query()->count())->toBe(1);
    Queue::assertNothingPushed();
});

test('retrying an older failed transcription is refused while a newer active transcription exists', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $failed = corr01RetryableFailed($media, $owner);
    $active = corr01ActiveTranscription($media, $owner);

    $this->actingAs($owner)->post(route('transcriptions.retry', $failed))
        ->assertRedirect()
        ->assertSessionHas('error', 'Another transcription is already active for this media file.');

    expect($failed->refresh()->status)->toBe(TranscriptionStatus::Failed)
        ->and(ProcessingJob::query()->where('transcription_id', $failed->id)->count())->toBe(1)
        ->and($active->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and(ProcessingJob::query()->where('transcription_id', $active->id)->count())->toBe(1)
        ->and(Transcription::query()->where('media_file_id', $media->id)->count())->toBe(2);
    Queue::assertNothingPushed();
});

test('the failed transcription page explains that retry is blocked instead of calling the failure not retryable', function () {
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $failed = corr01RetryableFailed($media, $owner);
    corr01ActiveTranscription($media, $owner);

    $this->actingAs($owner)->get(route('transcriptions.show', $failed))
        ->assertSee('Another transcription is already active for this media file.')
        ->assertDontSee('Retry transcription')
        ->assertDontSee('This failure is not retryable.');
});

test('retry rolls back its state change and attempt when competing work appears before the media lock', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $failed = corr01RetryableFailed($media, $owner);
    $competingStarted = false;

    // Reproduces, on one connection, the PostgreSQL interleaving where a Start
    // commits a Draft after Retry's pre-check passed but before Retry takes the
    // media lock: the competing row is created inside Retry's own transaction,
    // after the CAS and the new attempt, and before the authoritative check.
    ProcessingJob::creating(function () use (&$competingStarted, $media, $owner): void {
        if ($competingStarted) {
            return;
        }

        $competingStarted = true;
        Transcription::factory()->draft()->create([
            'user_id' => $owner->id,
            'media_file_id' => $media->id,
        ]);
    });

    expect(fn () => app(TranscriptionRetry::class)->retry($failed))
        ->toThrow(TranscriptionException::class, 'Another transcription is already active for this media file.');

    expect($competingStarted)->toBeTrue()
        ->and($failed->refresh()->status)->toBe(TranscriptionStatus::Failed)
        ->and(ProcessingJob::query()->where('transcription_id', $failed->id)->count())->toBe(1)
        ->and(ProcessingJob::query()->where('status', ProcessingStatus::Queued->value)->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('retry becomes available again once the competing transcription is terminal', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $failed = corr01RetryableFailed($media, $owner);
    $active = corr01ActiveTranscription($media, $owner);
    $active->forceFill(['status' => TranscriptionStatus::Completed])->save();
    ProcessingJob::query()->where('transcription_id', $active->id)->update(['status' => ProcessingStatus::Completed->value]);

    $this->actingAs($owner)->post(route('transcriptions.retry', $failed))
        ->assertRedirect(route('transcriptions.show', $failed))
        ->assertSessionHas('success');

    expect($failed->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and(ProcessingJob::query()->where('transcription_id', $failed->id)->where('status', ProcessingStatus::Queued->value)->count())->toBe(1);
    Queue::assertPushedTimes(ProcessTranscription::class, 1);
});

test('retry queues a new attempt normally when no other transcription is active for the media', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $failed = corr01RetryableFailed($media, $owner);

    $attempt = app(TranscriptionRetry::class)->retry($failed);

    expect($attempt->transcription_id)->toBe($failed->id)
        ->and($attempt->status)->toBe(ProcessingStatus::Queued)
        ->and($failed->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and(ProcessingJob::query()->where('transcription_id', $failed->id)->count())->toBe(2);
    Queue::assertPushed(
        ProcessTranscription::class,
        fn (ProcessTranscription $job): bool => $job->processingAttemptId === $attempt->id,
    );
});

test('starting after a retry reuses the retried transcription instead of creating a second active one', function () {
    Queue::fake();
    $owner = User::factory()->create();
    $media = corr01Media($owner);
    $failed = corr01RetryableFailed($media, $owner);
    app(TranscriptionRetry::class)->retry($failed);

    $this->actingAs($owner)->post(route('media.transcriptions.store', $media))
        ->assertRedirect(route('transcriptions.show', $failed));

    expect(Transcription::query()->where('media_file_id', $media->id)->count())->toBe(1)
        ->and(ProcessingJob::query()->where('status', ProcessingStatus::Queued->value)->count())->toBe(1);
});
