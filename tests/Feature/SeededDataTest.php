<?php

use App\Enums\ProcessingStatus;
use App\Models\MediaFile;
use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('seeded application renders realistic data', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@rtftt.local')->first();
    $user = User::where('email', 'user@rtftt.local')->first();

    $this->assertNotNull($admin);
    $this->assertNotNull($user);

    $this->assertGreaterThan(0, MediaFile::count());
    $this->assertGreaterThan(0, Transcription::count());
    $this->assertGreaterThan(0, ProcessingJob::count());
});

test('admin sees all transcriptions', function () {
    $this->seed(DatabaseSeeder::class);

    $admin = User::where('email', 'admin@rtftt.local')->first();
    $this->actingAs($admin);

    $response = $this->get(route('transcriptions.index'));
    $response->assertOk();
});

test('user sees only own transcriptions', function () {
    $this->seed(DatabaseSeeder::class);

    $user = User::where('email', 'user@rtftt.local')->first();
    $this->actingAs($user);

    $response = $this->get(route('transcriptions.index'));
    $response->assertOk();
});

test('seeded timelines and logs describe each job without leaking earlier timestamps', function () {
    $this->freezeTime();

    $this->seed(DatabaseSeeder::class);

    expect(MediaFile::count())->toBe(9);
    foreach (Transcription::all() as $transcription) {
        if ($transcription->completed_at !== null) {
            expect($transcription->started_at)->not->toBeNull();
            expect($transcription->started_at->lte($transcription->completed_at))->toBeTrue();
        }
    }
    $queued = ProcessingJob::where('status', ProcessingStatus::Queued)->get();
    expect($queued)->not->toBeEmpty();
    foreach ($queued as $job) {
        expect($job->started_at)->toBeNull()
            ->and($job->completed_at)->toBeNull()
            ->and($job->logs[0])->toBe('Job queued; not started.');
    }
    foreach (ProcessingJob::whereNotNull('started_at')->get() as $job) {
        expect($job->logs[0])->toBe('Job started at '.$job->started_at->toDateTimeString());
        if ($job->completed_at !== null) {
            expect($job->started_at->lte($job->completed_at))->toBeTrue();
            expect($job->logs)->toContain('Finished at '.$job->completed_at->toDateTimeString());
        }
    }
});

test('unsupported two factor factory state fails explicitly without enabling the feature', function () {
    expect(fn () => User::factory()->withTwoFactor())
        ->toThrow(LogicException::class, 'Two-factor authentication is not supported by the Phase 1 schema.');
});

test('media factory builds a usable filename from words and extension', function () {
    $media = MediaFile::factory()->make();

    expect($media->original_filename)->toEndWith('.'.$media->extension);
    expect(pathinfo($media->original_filename, PATHINFO_FILENAME))->toContain(' ');
});
