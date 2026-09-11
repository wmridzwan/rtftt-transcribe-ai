<?php

use App\Enums\UserRole;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\User;

test('user has many media files', function () {
    $user = User::factory()->create();
    MediaFile::factory()->count(3)->create(['user_id' => $user->id]);

    $this->assertCount(3, $user->mediaFiles);
});

test('user has many transcriptions', function () {
    $user = User::factory()->create();
    Transcription::factory()->count(3)->create(['user_id' => $user->id]);

    $this->assertCount(3, $user->transcriptions);
});

test('media file belongs to user', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $this->assertEquals($user->id, $mediaFile->user_id);
});

test('transcription belongs to user', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->create(['user_id' => $user->id]);

    $this->assertEquals($user->id, $transcription->user_id);
});

test('transcription belongs to media file', function () {
    $mediaFile = MediaFile::factory()->create();
    $transcription = Transcription::factory()->create(['media_file_id' => $mediaFile->id]);

    $this->assertEquals($mediaFile->id, $transcription->media_file_id);
});

test('transcription has many segments', function () {
    $transcription = Transcription::factory()->create();
    TranscriptionSegment::factory()->count(5)->create(['transcription_id' => $transcription->id]);

    $this->assertCount(5, $transcription->segments);
});

test('transcription has many processing jobs', function () {
    $transcription = Transcription::factory()->create();
    ProcessingJob::factory()->count(3)->create(['transcription_id' => $transcription->id]);

    $this->assertCount(3, $transcription->processingJobs);
});

test('processing job belongs to transcription', function () {
    $transcription = Transcription::factory()->create();
    $job = ProcessingJob::factory()->create(['transcription_id' => $transcription->id]);

    $this->assertEquals($transcription->id, $job->transcription_id);
});

test('completed processing job has valid timeline', function () {
    $job = ProcessingJob::factory()->completed()->create();

    $this->assertNotNull($job->started_at);
    $this->assertNotNull($job->completed_at);
    $this->assertTrue($job->started_at->lte($job->completed_at), 'started_at must be before completed_at');
    $this->assertEquals(100, $job->progress_percentage);
});

test('failed processing job has valid timeline', function () {
    $job = ProcessingJob::factory()->failed()->create();

    $this->assertNotNull($job->started_at);
    $this->assertNotNull($job->completed_at);
    $this->assertTrue($job->started_at->lte($job->completed_at), 'started_at must be before completed_at');
});

test('running processing job has started_at but no completed_at', function () {
    $job = ProcessingJob::factory()->running()->create();

    $this->assertNotNull($job->started_at);
    $this->assertNull($job->completed_at);
});

test('transcription derived values use its related media and owner role remains an enum', function () {
    $admin = User::factory()->admin()->create();
    $media = MediaFile::factory()->create(['user_id' => $admin->id, 'duration_seconds' => 120]);
    $transcription = Transcription::factory()->create([
        'user_id' => $admin->id,
        'media_file_id' => $media->id,
        'processing_seconds' => 30,
    ]);

    expect($transcription->fresh()->formatted_duration)->toBe('02:00')
        ->and($transcription->fresh()->real_time_factor)->toBe(0.25)
        ->and($admin->fresh()->role)->toBe(UserRole::Admin)
        ->and($admin->fresh()->isAdmin())->toBeTrue();
});
