<?php

use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;
use App\Retention\RetentionPurge;
use Illuminate\Support\Facades\Storage;

/*
 * P7-011 corrective cycle 1, Finding 1: the transcription detail
 * surface must visibly disclose the purged-source state (permanent
 * retention removal, not a temporary failure) instead of rendering a
 * player against a 410 endpoint — while transcript content and text
 * exports keep working.
 */

beforeEach(function () {
    Storage::fake('local');
});

function seedPurgedTranscription(User $user): Transcription
{
    $media = MediaFile::factory()->create(['user_id' => $user->id]);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'completed_at' => now()->subDays(45)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($media->storage_path, 'BYTES');

    RetentionPurge::run();

    return $transcription->refresh();
}

it('discloses the purged source on the transcription surface without a player', function (): void {
    $user = User::factory()->create();
    $transcription = seedPurgedTranscription($user);

    $response = $this->actingAs($user)->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    // Visible disclosure with permanent-removal wording.
    $response->assertSee('Source media purged', false);
    $response->assertSee('data-purged-source', false);
    $response->assertSee('permanently deleted', false);
    // No usable player element for purged bytes (the data-media-player
    // string also appears in page JS, so assert element tags instead).
    $response->assertDontSee('<audio', false);
    $response->assertDontSee('<video', false);
});

it('keeps transcript content and exports available for purged sources', function (): void {
    $user = User::factory()->create();
    $transcription = seedPurgedTranscription($user);

    $show = $this->actingAs($user)->get(route('transcriptions.show', $transcription));
    $show->assertOk();
    // Export controls stay enabled (completed lifecycle).
    $show->assertSee('Export as TXT', false);

    $export = $this->actingAs($user)->get(route('transcriptions.export.txt', ['transcription' => $transcription->id]));
    $export->assertOk();
});

it('renders the player and no purged notice for available media', function (): void {
    $user = User::factory()->create();
    $media = MediaFile::factory()->create(['user_id' => $user->id]);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $media->id,
        'completed_at' => now()->subDays(5)->toDateTimeString(),
    ]);
    Storage::disk(config('media.storage_disk'))->put($media->storage_path, 'BYTES');

    $response = $this->actingAs($user)->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertDontSee('data-purged-source', false);

    $content = $response->getContent() ?: '';
    $hasPlayer = str_contains($content, '<audio') || str_contains($content, '<video');
    expect($hasPlayer)->toBeTrue('available media must render a player element');
});
