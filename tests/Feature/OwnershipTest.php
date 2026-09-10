<?php

use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;

test('normal user can access own transcription', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('transcriptions.show', $transcription));
    $response->assertOk();
});

test('normal user cannot access another users transcription', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create(['user_id' => $otherUser->id]);
    $response = $this->get(route('transcriptions.show', $transcription));
    $response->assertForbidden();
});

test('admin can access any transcription', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->actingAs($admin);

    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('transcriptions.show', $transcription));
    $response->assertOk();
});

test('normal user can access own media', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('media.show', $mediaFile));
    $response->assertOk();
});

test('normal user cannot access another users media', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->create(['user_id' => $otherUser->id]);
    $response = $this->get(route('media.show', $mediaFile));
    $response->assertForbidden();
});

test('admin can access any media', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $this->actingAs($admin);

    $mediaFile = MediaFile::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('media.show', $mediaFile));
    $response->assertOk();
});
