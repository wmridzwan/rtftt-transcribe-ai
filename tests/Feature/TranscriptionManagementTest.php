<?php

use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;

test('user can rename their own transcription', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Title',
    ]);

    $response = $this->patch(route('transcriptions.rename', $transcription), [
        'title' => 'New Title',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('transcriptions', [
        'id' => $transcription->id,
        'title' => 'New Title',
    ]);
});

test('transcription list rename action opens the rename modal', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = Transcription::factory()->create(['user_id' => $user->id]);

    $this->get(route('transcriptions.index'))
        ->assertOk()
        ->assertSee(route('transcriptions.show', ['transcription' => $transcription, 'rename' => 1]), false);

    $this->get(route('transcriptions.show', ['transcription' => $transcription, 'rename' => 1]))
        ->assertOk()
        ->assertSee('showRenameModal: true', false);
});

test('controller success feedback is visible in the application shell', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->withSession(['success' => 'Transcription renamed successfully.'])
        ->get(route('transcriptions.index'))
        ->assertSee('Transcription renamed successfully.');
});

test('user cannot rename another users transcription', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create([
        'user_id' => $otherUser->id,
        'title' => 'Original Title',
    ]);

    $response = $this->patch(route('transcriptions.rename', $transcription), [
        'title' => 'Hacked Title',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseHas('transcriptions', [
        'id' => $transcription->id,
        'title' => 'Original Title',
    ]);
});

test('user can delete their own transcription', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->delete(route('transcriptions.destroy', $transcription));

    $response->assertRedirect(route('transcriptions.index'));
    $response->assertSessionHas('success');
    $this->assertDatabaseMissing('transcriptions', ['id' => $transcription->id]);
});

test('user cannot delete another users transcription', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->delete(route('transcriptions.destroy', $transcription));

    $response->assertForbidden();
    $this->assertDatabaseHas('transcriptions', ['id' => $transcription->id]);
});

test('deleting transcription does not delete the media file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $this->delete(route('transcriptions.destroy', $transcription));

    $this->assertDatabaseHas('media_files', ['id' => $mediaFile->id]);
    $this->assertDatabaseMissing('transcriptions', ['id' => $transcription->id]);
});

test('rename validates title is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->patch(route('transcriptions.rename', $transcription), [
        'title' => '',
    ]);

    $response->assertSessionHasErrors('title');
});

test('rename validates title max 255 characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
    ]);

    $response = $this->patch(route('transcriptions.rename', $transcription), [
        'title' => str_repeat('a', 256),
    ]);

    $response->assertSessionHasErrors('title');
});

test('rename accepts title at exactly 255 characters', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create([
        'user_id' => $user->id,
    ]);

    $title = str_repeat('a', 255);
    $response = $this->patch(route('transcriptions.rename', $transcription), [
        'title' => $title,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('transcriptions', [
        'id' => $transcription->id,
        'title' => $title,
    ]);
});
