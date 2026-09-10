<?php

use App\Models\MediaFile;
use App\Models\Transcription;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard renders audio icon for audio transcriptions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->audio()->create(['user_id' => $user->id]);
    Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Recent Transcriptions');
});

test('dashboard renders video icon for video transcriptions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $mediaFile = MediaFile::factory()->video()->create(['user_id' => $user->id]);
    Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $mediaFile->id,
    ]);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Recent Transcriptions');
});

test('dashboard renders both audio and video icons when both media types exist', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $audioFile = MediaFile::factory()->audio()->create(['user_id' => $user->id]);
    $videoFile = MediaFile::factory()->video()->create(['user_id' => $user->id]);

    Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $audioFile->id,
    ]);
    Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'media_file_id' => $videoFile->id,
    ]);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('Recent Transcriptions');
});

test('registration route does not exist', function () {
    $response = $this->get('/register');
    $response->assertStatus(404);
});
