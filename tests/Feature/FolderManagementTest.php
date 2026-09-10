<?php

use App\Models\Folder;
use App\Models\MediaFile;
use App\Models\User;
use Illuminate\Database\QueryException;

test('user can create a folder', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('folders.store'), [
        'name' => 'My New Folder',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('folders', [
        'user_id' => $user->id,
        'name' => 'My New Folder',
    ]);
});

test('user can see their folders', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Visible Folder']);

    $response = $this->get(route('folders.show', $folder));

    $response->assertOk();
    $response->assertSee('Visible Folder');
});

test('user cannot see another users folders', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $otherFolder = Folder::factory()->create(['user_id' => $otherUser->id, 'name' => 'Secret Folder']);

    $response = $this->get(route('folders.show', $otherFolder));

    $response->assertForbidden();
});

test('user can rename their folder', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $response = $this->patch(route('folders.update', $folder), [
        'name' => 'New Name',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('folders', [
        'id' => $folder->id,
        'name' => 'New Name',
    ]);
});

test('user can delete their folder', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = Folder::factory()->create(['user_id' => $user->id]);

    $response = $this->delete(route('folders.destroy', $folder));

    $response->assertRedirect(route('folders.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
});

test('deleting folder nulls media folder_id does not delete media', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = Folder::factory()->create(['user_id' => $user->id]);
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
    ]);

    $this->delete(route('folders.destroy', $folder));

    $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
    $this->assertDatabaseHas('media_files', [
        'id' => $mediaFile->id,
        'folder_id' => null,
    ]);
});

test('folder name is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('folders.store'), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors('name');
});

test('folder name is unique per user at database level', function () {
    $user = User::factory()->create();

    Folder::factory()->create([
        'user_id' => $user->id,
        'name' => 'Existing Folder',
    ]);

    $this->assertDatabaseCount('folders', 1);

    $this->expectException(QueryException::class);

    $user->folders()->create(['name' => 'Existing Folder']);
});

test('user can see folder contents', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $folder = Folder::factory()->create(['user_id' => $user->id, 'name' => 'Content Folder']);
    $mediaFile = MediaFile::factory()->create([
        'user_id' => $user->id,
        'folder_id' => $folder->id,
    ]);

    $response = $this->get(route('folders.show', $folder));

    $response->assertOk();
    $response->assertSee($mediaFile->original_filename);
});
