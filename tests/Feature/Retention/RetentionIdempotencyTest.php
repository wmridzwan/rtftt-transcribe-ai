<?php

use App\Models\MediaFile;
use App\Models\RetentionPurgeAudit;
use App\Models\Transcription;
use App\Models\User;
use App\Retention\RetentionPurge;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/*
 * P7-011: idempotency, failure, and resume. A second run must never
 * double-delete or double-count; failures must be recorded, never
 * presented as success; a follow-up run must complete what an
 * interrupted run left behind.
 */

beforeEach(function () {
    Storage::fake('local');
});

function seedOldCompleted(User $user): Transcription
{
    $media = MediaFile::factory()->create(['user_id' => $user->id]);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'completed_at' => now()->subDays(45)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($media->storage_path, 'BYTES');

    return $transcription;
}

it('treats a second run as idempotent re-runs, not new purges', function (): void {
    $user = User::factory()->create();
    seedOldCompleted($user);

    $first = RetentionPurge::run();
    $second = RetentionPurge::run();

    expect($first['outcomes'][RetentionPurgeAudit::OUTCOME_PURGED])->toBe(1)
        ->and($second['outcomes'][RetentionPurgeAudit::OUTCOME_PURGED])->toBe(0)
        ->and($second['outcomes'][RetentionPurgeAudit::OUTCOME_SKIPPED])->toBe(1)
        ->and(RetentionPurgeAudit::query()->where('outcome', 'purged')->count())->toBe(1);
});

it('records already-absent bytes without error and still tombstones', function (): void {
    $user = User::factory()->create();
    $transcription = seedOldCompleted($user);
    Storage::disk(config('media.storage_disk'))->delete($transcription->mediaFile->storage_path);

    $result = RetentionPurge::run();

    expect($result['outcomes'][RetentionPurgeAudit::OUTCOME_ABSENT])->toBe(1)
        ->and($result['outcomes'][RetentionPurgeAudit::OUTCOME_FAILED])->toBe(0)
        ->and($transcription->mediaFile->refresh()->purged_at)->not->toBeNull();
});

it('records filesystem failure without tombstoning and resumes on the next run', function (): void {
    $user = User::factory()->create();
    $transcription = seedOldCompleted($user);
    $disk = Storage::disk(config('media.storage_disk'));
    $path = $transcription->mediaFile->storage_path;

    // A directory where the file should be: the adapter cannot delete
    // the "file", exercising the failure path deterministically.
    $disk->delete($path);
    $disk->makeDirectory($path);

    $first = RetentionPurge::run();

    expect($first['outcomes'][RetentionPurgeAudit::OUTCOME_FAILED])->toBe(1)
        ->and($transcription->mediaFile->refresh()->purged_at)->toBeNull()
        ->and(Artisan::call('retention:purge'))->not->toBe(0);

    // "Fix" the filesystem (directory removed, bytes still absent) and
    // resume: the follow-up run completes the object as absent.
    $disk->deleteDirectory($path);

    $second = RetentionPurge::run();

    expect($second['outcomes'][RetentionPurgeAudit::OUTCOME_ABSENT])->toBe(1)
        ->and($second['outcomes'][RetentionPurgeAudit::OUTCOME_FAILED])->toBe(0)
        ->and($transcription->mediaFile->refresh()->purged_at)->not->toBeNull();
});

it('backs off cleanly when the user deletes the media concurrently', function (): void {
    $user = User::factory()->create();
    $transcription = seedOldCompleted($user);

    // Simulate the ADR-005 user path winning the race: the media row is
    // gone (its transcription cascades with it), so the purge finds no
    // candidate, records nothing, and fails nothing.
    MediaFile::query()->where('id', $transcription->media_file_id)->delete();

    $result = RetentionPurge::run();

    expect($result['outcomes'])->toBe([
        'purged' => 0, 'absent' => 0, 'failed' => 0, 'skipped' => 0,
    ]);
});

it('keeps per-run audit scope so runs never double-count', function (): void {
    $user = User::factory()->create();
    seedOldCompleted($user);

    $first = RetentionPurge::run();
    $second = RetentionPurge::run();

    expect(RetentionPurgeAudit::query()->where('run_id', $first['run_id'])->count())->toBe(1)
        ->and(RetentionPurgeAudit::query()->where('run_id', $second['run_id'])->count())->toBe(1)
        ->and($first['run_id'])->not->toBe($second['run_id']);
});
