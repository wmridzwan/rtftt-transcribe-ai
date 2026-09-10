<?php

use App\Models\Folder;
use App\Models\User;

test('user cannot view another users folder', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $otherFolder = Folder::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->get(route('folders.show', $otherFolder));

    $response->assertForbidden();
});

test('user cannot update another users folder', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $otherFolder = Folder::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Original Name',
    ]);

    $response = $this->patch(route('folders.update', $otherFolder), [
        'name' => 'Hacked Name',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('folders', [
        'id' => $otherFolder->id,
        'name' => 'Original Name',
    ]);
});

test('user cannot delete another users folder', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $otherFolder = Folder::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->delete(route('folders.destroy', $otherFolder));

    $response->assertForbidden();
    $this->assertDatabaseHas('folders', ['id' => $otherFolder->id]);
});

test('admin can view any folder', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->actingAs($admin);

    $folder = Folder::factory()->create(['user_id' => $user->id]);

    $response = $this->get(route('folders.show', $folder));

    $response->assertOk();
});
