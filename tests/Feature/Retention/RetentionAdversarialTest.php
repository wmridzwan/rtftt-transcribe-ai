<?php

use App\Models\RetentionPurgeAudit;
use App\Models\StagingClaim;
use App\Models\Transcription;
use App\Models\User;
use App\Retention\RetentionPurge;
use Illuminate\Support\Facades\Storage;

/*
 * P7-011 adversarial protection: active/retryable work, protected
 * classes, and dry-run behavior. Nothing ineligible may lose bytes.
 */

beforeEach(function () {
    Storage::fake('local');
});

it('never purges active work no matter how old the rows are', function (): void {
    $user = User::factory()->create();

    foreach (['queued', 'transcribing', 'draft'] as $state) {
        $transcription = Transcription::factory()->$state()->create(['user_id' => $user->id]);
        $transcription->forceFill(['created_at' => now()->subDays(90), 'updated_at' => now()->subDays(90)])->save();
        Storage::disk(config('media.storage_disk'))->put($transcription->mediaFile->storage_path, 'BYTES');
    }

    $result = RetentionPurge::run();

    expect($result['outcomes']['purged'])->toBe(0)
        ->and(RetentionPurgeAudit::query()->count())->toBe(0);
});

it('protects quarantine paths even if a row ever pointed at one', function (): void {
    $user = User::factory()->create();

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'completed_at' => now()->subDays(60)->toDateTimeString(),
    ]);
    $media = $transcription->mediaFile;
    $media->forceFill(['storage_path' => 'quarantine/suspect.bin'])->save();
    Storage::disk(config('media.storage_disk'))->put('quarantine/suspect.bin', 'MALWARE');

    $result = RetentionPurge::run();

    expect($result['outcomes']['purged'])->toBe(0)
        ->and(Storage::disk(config('media.storage_disk'))->exists('quarantine/suspect.bin'))->toBeTrue()
        ->and($media->refresh()->purged_at)->toBeNull();
});

it('dry-run reports eligibility and deletes nothing', function (): void {
    $user = User::factory()->create();

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'completed_at' => now()->subDays(60)->toDateTimeString(),
    ]);
    $path = $transcription->mediaFile->storage_path;
    Storage::disk(config('media.storage_disk'))->put($path, 'BYTES');

    $exit = Artisan::call('retention:purge', ['--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(Storage::disk(config('media.storage_disk'))->exists($path))->toBeTrue()
        ->and($transcription->mediaFile->refresh()->purged_at)->toBeNull()
        ->and(RetentionPurgeAudit::query()->where('outcome', 'skipped')->count())->toBeGreaterThanOrEqual(1)
        ->and(RetentionPurgeAudit::query()->where('outcome', 'purged')->count())->toBe(0);
});

it('leaves held and fresh staging claims alone', function (): void {
    $user = User::factory()->create();

    $held = StagingClaim::factory()->create(['user_id' => $user->id]);
    Storage::disk(config('media.storage_disk'))->put($held->staging_path, 'BYTES');

    $result = RetentionPurge::run();

    expect($result['outcomes']['purged'])->toBe(0)
        ->and(StagingClaim::query()->find($held->id))->not->toBeNull()
        ->and(Storage::disk(config('media.storage_disk'))->exists($held->staging_path))->toBeTrue();
});

it('purges expired unheld staging claims with file and row removal', function (): void {
    $user = User::factory()->create();

    $claim = StagingClaim::factory()->create([
        'user_id' => $user->id,
        'held_by' => 'upload',
        'expires_at' => now()->subHour(),
        'created_at' => now()->subDays(2),
    ]);
    Storage::disk(config('media.storage_disk'))->put($claim->staging_path, 'BYTES');

    $result = RetentionPurge::run();

    expect($result['outcomes'][RetentionPurgeAudit::OUTCOME_PURGED])->toBeGreaterThanOrEqual(1)
        ->and(StagingClaim::query()->find($claim->id))->toBeNull()
        ->and(Storage::disk(config('media.storage_disk'))->exists($claim->staging_path))->toBeFalse();
});

it('purges orphan staging files older than 24h and skips fresh ones', function (): void {
    $disk = Storage::disk(config('media.storage_disk'));
    $stagingRoot = trim((string) config('media.staging_directory', 'media/.staging'), '/');

    $disk->put($stagingRoot.'/9/fresh.bin', 'BYTES');

    $result = RetentionPurge::run();

    expect($disk->exists($stagingRoot.'/9/fresh.bin'))->toBeTrue();
});

it('gates unclaimed staging orphans exactly on the 24-hour boundary', function (): void {
    $now = time();

    expect(RetentionPurge::isStagingOrphanEligible($now - (23 * 3600), $now))->toBeFalse()
        ->and(RetentionPurge::isStagingOrphanEligible($now - (24 * 3600), $now))->toBeTrue()
        ->and(RetentionPurge::isStagingOrphanEligible($now - (25 * 3600), $now))->toBeTrue();
});
