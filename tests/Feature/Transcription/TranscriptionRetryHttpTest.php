<?php

use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use App\Transcription\TranscriptionFailure;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TranscriptionFixtures;

/*
 * P3-007 user-facing retry surface (minimal; ADR-018 manual retry only).
 */

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();
});

/**
 * @return array{user: User, media: MediaFile, transcription: Transcription, attempt: ProcessingJob}
 */
function p3007HttpFailedTranscription(
    User $user,
    TranscriptionFailure $failure = TranscriptionFailure::WorkerTimeout,
): array {
    $fixture = TranscriptionFixtures::scenario(
        TranscriptionStatus::Failed,
        ProcessingStatus::Failed,
        user: $user,
    );

    $fixture['attempt']->forceFill([
        'failure_code' => $failure->value,
        'error_message' => 'Transcription worker did not complete in time.',
        'completed_at' => now(),
    ])->save();

    $fixture['transcription']->forceFill([
        'error_message' => 'Transcription worker did not complete in time.',
        'completed_at' => now(),
    ])->save();

    return $fixture;
}

test('a user can retry their own retryable failed transcription', function () {
    $user = User::factory()->create();
    ['transcription' => $transcription] = p3007HttpFailedTranscription($user);

    $response = $this->actingAs($user)->post(route('transcriptions.retry', $transcription));

    $response->assertRedirect(route('transcriptions.show', $transcription));

    expect($transcription->refresh()->status)->toBe(TranscriptionStatus::Queued)
        ->and($transcription->processingJobs()->count())->toBe(2);
});

test('a user cannot retry another user transcription', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    ['transcription' => $transcription] = p3007HttpFailedTranscription($owner);

    $response = $this->actingAs($other)->post(route('transcriptions.retry', $transcription));

    $response->assertForbidden();

    expect($transcription->refresh()->status)->toBe(TranscriptionStatus::Failed)
        ->and($transcription->processingJobs()->count())->toBe(1);
});

test('the retry action is shown for retryable failures', function () {
    $user = User::factory()->create();
    ['transcription' => $transcription] = p3007HttpFailedTranscription($user);

    $response = $this->actingAs($user)->get(route('transcriptions.show', $transcription));

    $response->assertOk()
        ->assertSee('Retry transcription')
        ->assertSee('Transcription worker did not complete in time.');
});

test('the retry action is hidden for non-retryable failures', function () {
    $user = User::factory()->create();
    ['transcription' => $transcription] = p3007HttpFailedTranscription($user, TranscriptionFailure::ProcessingFailed);

    $response = $this->actingAs($user)->get(route('transcriptions.show', $transcription));

    $response->assertOk()
        ->assertSee('This failure is not retryable.')
        ->assertDontSee('Retry transcription');
});

test('retry requires authentication', function () {
    $user = User::factory()->create();
    ['transcription' => $transcription] = p3007HttpFailedTranscription($user);

    $this->post(route('transcriptions.retry', $transcription))
        ->assertRedirect(route('login'));
});
