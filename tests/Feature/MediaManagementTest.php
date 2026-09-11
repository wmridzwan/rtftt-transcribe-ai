<?php

use App\Livewire\Media\Show;
use App\Models\Folder;
use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

test('user must explicitly confirm deleting media file with transcriptions', function () {
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
    $response->assertSessionHasErrors('confirm_cascade');
    $this->assertDatabaseHas('media_files', ['id' => $mediaFile->id]);
    $this->assertDatabaseHas('transcriptions', ['media_file_id' => $mediaFile->id]);
});

test('user can delete media file and associated transcriptions after explicit confirmation', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'media/test-file.mp3',
    ]);
    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->delete(route('media.destroy', $mediaFile), [
        'confirm_cascade' => '1',
    ]);

    $response->assertRedirect(route('media.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('media_files', ['id' => $mediaFile->id]);
    $this->assertDatabaseMissing('transcriptions', ['id' => $transcription->id]);
});

test('user cannot delete another users media through the controller', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $otherUser->id]);

    $this->delete(route('media.destroy', $mediaFile), ['confirm_cascade' => '1'])
        ->assertForbidden();

    $this->assertDatabaseHas('media_files', ['id' => $mediaFile->id]);
});

test('persisted display name is returned and original filename is the fallback', function () {
    $user = User::factory()->create();
    $persistedName = 'Renamed meeting.mp3';

    $namedMedia = MediaFile::factory()->create([
        'user_id' => $user->id,
        'display_name' => $persistedName,
    ]);
    $unnamedMedia = MediaFile::factory()->create([
        'user_id' => $user->id,
        'display_name' => null,
    ]);

    expect($namedMedia->fresh()->display_name)->toBe($persistedName)
        ->and($unnamedMedia->fresh()->display_name)->toBe($unnamedMedia->original_filename);
});

test('owner can delete media with transcriptions through Livewire after explicit confirmation', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'storage_path' => 'media/test-file.mp3',
    ]);
    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->call('openDeleteModal')
        ->call('destroy')
        ->assertHasErrors(['confirmCascade']);

    expect($mediaFile->fresh())->not->toBeNull();

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->call('openDeleteModal')
        ->set('confirmCascade', true)
        ->call('destroy');

    expect($mediaFile->fresh())->toBeNull();
    $this->assertDatabaseMissing('transcriptions', ['id' => $transcription->id]);
});

test('Livewire deletion rechecks transcriptions created after mount', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $component = Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->call('openDeleteModal');

    Transcription::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $component->call('destroy')->assertHasErrors(['confirmCascade']);

    expect($mediaFile->fresh())->not->toBeNull();
});

test('media deletion modal clearly warns about cascading transcriptions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    Transcription::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->get(route('media.show', $mediaFile))
        ->assertSee('Deleting it will also delete all associated transcriptions.')
        ->assertSee('I understand that associated transcriptions will also be deleted.');
});

test('owner can delete media without transcriptions through Livewire', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->call('openDeleteModal')->call('destroy');

    expect($mediaFile->fresh())->toBeNull();
});

test('user cannot delete another users media through Livewire', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);

    $component = Livewire::test(Show::class, ['mediaFile' => $mediaFile]);

    $this->actingAs($otherUser);
    $component->call('destroy')->assertForbidden();

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

test('user cannot move media to another users folder', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $otherFolder = Folder::factory()->create(['user_id' => $otherUser->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);

    $response = $this->patch(route('media.move', $mediaFile), [
        'folder_id' => $otherFolder->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('folder_id');
    $response->assertSessionMissing('success');
    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
        'folder_id' => null,
    ]);

    $this->actingAs($otherUser)->get(route('folders.show', $otherFolder))
        ->assertOk()->assertDontSee($mediaFile->original_filename);
});

test('user cannot move another users media through the controller', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)->patch(route('media.move', $mediaFile), ['folder_id' => $folder->id])
        ->assertForbidden();

    expect($mediaFile->fresh()->folder_id)->toBeNull();
});

test('controller rejects a nonexistent destination without changing the current folder', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id, 'folder_id' => $folder->id]);

    $this->actingAs($user)->patch(route('media.move', $mediaFile), ['folder_id' => -1])
        ->assertSessionHasErrors('folder_id');

    expect($mediaFile->fresh()->folder_id)->toBe($folder->id);
});

test('admin can move another users media within the owners folders through the controller', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($admin)->patch(route('media.move', $mediaFile), ['folder_id' => $folder->id])
        ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

    expect($mediaFile->fresh()->folder_id)->toBe($folder->id);
});

test('admin cannot move another users media into the admins folder through the controller', function () {
    $admin = User::factory()->admin()->create();
    $folder = Folder::factory()->create(['user_id' => $admin->id]);
    $mediaFile = MediaFile::factory()->create();

    $this->actingAs($admin)->patch(route('media.move', $mediaFile), ['folder_id' => $folder->id])
        ->assertSessionHasErrors('folder_id');

    expect($mediaFile->fresh()->folder_id)->toBeNull();
});

test('owner can move media to an owned folder through Livewire', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->set('moveFolderId', $folder->id)->call('moveToFolder')->assertHasNoErrors();

    expect($mediaFile->fresh()->folder_id)->toBe($folder->id);
    $this->get(route('folders.show', $folder))->assertOk()->assertSee($mediaFile->original_filename);
});

test('owner can move media to root through Livewire', function () {
    $user = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id, 'folder_id' => $folder->id]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->call('openMoveModal')->set('moveFolderId', null)->call('moveToFolder')->assertHasNoErrors();

    expect($mediaFile->fresh()->folder_id)->toBeNull();
});

test('Livewire rejects another users folder without changing the current folder', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $otherFolder = Folder::factory()->create(['user_id' => $otherUser->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id, 'folder_id' => $folder->id]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->set('moveFolderId', $otherFolder->id)->call('moveToFolder')->assertHasErrors(['moveFolderId']);

    expect($mediaFile->fresh()->folder_id)->toBe($folder->id);
    $this->actingAs($otherUser)->get(route('folders.show', $otherFolder))
        ->assertOk()->assertDontSee($mediaFile->original_filename);
});

test('Livewire rejects a nonexistent destination folder', function () {
    $user = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->set('moveFolderId', -1)->call('moveToFolder')->assertHasErrors(['moveFolderId']);

    expect($mediaFile->fresh()->folder_id)->toBeNull();
});

test('Livewire reauthorizes media ownership when moving', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $mediaFile = MediaFile::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($owner);
    $component = Livewire::test(Show::class, ['mediaFile' => $mediaFile]);

    $this->actingAs($otherUser);
    $component->set('moveFolderId', null)->call('moveToFolder')->assertForbidden();

    expect($mediaFile->fresh()->folder_id)->toBeNull();
});

test('admin can move another users media within the owners folders through Livewire', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->create();
    $folder = Folder::factory()->create(['user_id' => $owner->id]);
    $mediaFile = MediaFile::factory()->create(['user_id' => $owner->id]);
    $this->actingAs($admin);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->set('moveFolderId', $folder->id)->call('moveToFolder')->assertHasNoErrors();

    expect($mediaFile->fresh()->folder_id)->toBe($folder->id);
});

test('admin cannot move another users media into the admins folder through Livewire', function () {
    $admin = User::factory()->admin()->create();
    $folder = Folder::factory()->create(['user_id' => $admin->id]);
    $mediaFile = MediaFile::factory()->create();
    $this->actingAs($admin);

    Livewire::test(Show::class, ['mediaFile' => $mediaFile])
        ->set('moveFolderId', $folder->id)->call('moveToFolder')->assertHasErrors(['moveFolderId']);

    expect($mediaFile->fresh()->folder_id)->toBeNull();
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
