<?php

use App\Models\Folder;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('user can rename their own media file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $response = $this->patch(route('media.rename', $mediaFile), [
        'display_name' => 'My Renamed File',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
        'display_name' => 'My Renamed File',
    ]);
});

test('user cannot rename another users media file', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->patch(route('media.rename', $mediaFile), [
        'display_name' => 'Hacked Name',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
    ]);
    expect($mediaFile->fresh()->display_name)->not->toBe('Hacked Name');
});

test('user can delete media file with no transcriptions', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'media/test-file.mp3',
    ]);

    Storage::disk('local')->put('media/test-file.mp3', 'fake content');

    $response = $this->delete(route('media.destroy', $mediaFile));

    $response->assertRedirect(route('media.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('media_files', ['id' => $mediaFile->id]);
});

test('user cannot delete media file that has transcriptions', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'media/test-file.mp3',
    ]);

    Transcription::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->delete(route('media.destroy', $mediaFile));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('media_files', ['id' => $mediaFile->id]);
});

test('user can download their own media file', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'media/test-download.mp3',
    ]);

    Storage::disk('local')->put('media/test-download.mp3', 'fake audio content');

    $response = $this->get(route('media.download', $mediaFile));

    $response->assertOk();
    $response->assertHeader('Content-Disposition');
});

test('user cannot download another users media file', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $otherUser->id,
        'storage_path' => 'media/secret-file.mp3',
    ]);

    Storage::disk('local')->put('media/secret-file.mp3', 'secret audio content');

    $response = $this->get(route('media.download', $mediaFile));

    $response->assertForbidden();
});

test('user can move media to a folder', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $response = $this->patch(route('media.move', $mediaFile), [
        'folder_id' => $folder->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
        'folder_id' => $folder->id,
    ]);
});

test('user can move media to root (null folder)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
    ]);

    $response = $this->patch(route('media.move', $mediaFile), [
        'folder_id' => null,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
        'folder_id' => null,
    ]);
});

test('move validates folder belongs to user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $otherFolder = Folder::factory()->create(['user_id' => $otherUser->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $response = $this->patch(route('media.move', $mediaFile), [
        'folder_id' => $otherFolder->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
        'folder_id' => $otherFolder->id,
    ]);
});

test('rename validates display_name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $response = $this->patch(route('media.rename', $mediaFile), [
        'display_name' => '',
    ]);

    $response->assertSessionHasErrors('display_name');
});
