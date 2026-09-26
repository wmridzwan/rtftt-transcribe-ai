<?php

use App\Models\MediaFile;
use App\Models\RetentionPurgeAudit;
use App\Models\Transcription;
use App\Models\User;
use App\Retention\RetentionPurge;
use Illuminate\Support\Facades\Storage;

/*
 * P7-011 / D7-06: eligibility matrix. Clock: completed_at + 30d.
 * completed_at null → never eligible. Only completed lifecycles age
 * into eligibility; everything else is excluded by the query itself
 * (no audit rows, no touches).
 */

beforeEach(function () {
    Storage::fake('local');
});

function seedCompletedTranscription(User $user, ?string $completedAt, string $bytes = 'AUDIO-BYTES'): Transcription
{
    // Media must share the user's ownership: the nested factory would
    // otherwise attribute it to a stranger and authorization tests
    // would see a foreign row.
    $media = MediaFile::factory()->create(['user_id' => $user->id]);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'completed_at' => $completedAt,
    ]);

    Storage::disk(config('media.storage_disk'))->put($media->storage_path, $bytes);

    return $transcription;
}

it('purges bytes and tombstones the row for a 31-day-old completion', function (): void {
    $user = User::factory()->create();
    $transcription = seedCompletedTranscription($user, now()->subDays(31)->toDateTimeString());
    $path = $transcription->mediaFile->storage_path;

    $result = RetentionPurge::run();

    expect($result['outcomes'][RetentionPurgeAudit::OUTCOME_PURGED])->toBe(1)
        ->and(Storage::disk(config('media.storage_disk'))->exists($path))->toBeFalse()
        ->and($transcription->mediaFile->refresh()->purged_at)->not->toBeNull()
        // History rows survive: transcription + audit ledger intact.
        ->and(Transcription::query()->find($transcription->id))->not->toBeNull()
        ->and(RetentionPurgeAudit::query()->where('outcome', 'purged')->count())->toBe(1);
});

it('skips a 29-day-old completion without touching it', function (): void {
    $user = User::factory()->create();
    $transcription = seedCompletedTranscription($user, now()->subDays(29)->toDateTimeString());
    $path = $transcription->mediaFile->storage_path;

    $result = RetentionPurge::run();

    expect($result['outcomes'])->toBe([
        'purged' => 0, 'absent' => 0, 'failed' => 0, 'skipped' => 0,
    ])
        ->and(Storage::disk(config('media.storage_disk'))->exists($path))->toBeTrue()
        ->and($transcription->mediaFile->refresh()->purged_at)->toBeNull();
});

it('excludes non-completed lifecycles even when old', function (): void {
    $user = User::factory()->create();

    $failed = Transcription::factory()->failed()->create([
        'user_id' => $user->id,
        'completed_at' => now()->subDays(90)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($failed->mediaFile->storage_path, 'BYTES');

    $transcribing = Transcription::factory()->transcribing()->create(['user_id' => $user->id]);
    Storage::disk(config('media.storage_disk'))->put($transcribing->mediaFile->storage_path, 'BYTES');

    $result = RetentionPurge::run();

    expect($result['outcomes']['purged'])->toBe(0)
        ->and(Storage::disk(config('media.storage_disk'))->exists($failed->mediaFile->storage_path))->toBeTrue()
        ->and(Storage::disk(config('media.storage_disk'))->exists($transcribing->mediaFile->storage_path))->toBeTrue();
});

it('excludes completed rows with a null completed_at', function (): void {
    $user = User::factory()->create();

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'completed_at' => null,
    ]);
    Storage::disk(config('media.storage_disk'))->put($transcription->mediaFile->storage_path, 'BYTES');

    $result = RetentionPurge::run();

    expect($result['outcomes']['purged'])->toBe(0)
        ->and($transcription->mediaFile->refresh()->purged_at)->toBeNull();
});

it('treats the 30-day boundary as inclusive', function (): void {
    $user = User::factory()->create();
    $transcription = seedCompletedTranscription($user, now()->subDays(30)->subMinute()->toDateTimeString());

    $result = RetentionPurge::run();

    expect($result['outcomes'][RetentionPurgeAudit::OUTCOME_PURGED])->toBe(1);
});
