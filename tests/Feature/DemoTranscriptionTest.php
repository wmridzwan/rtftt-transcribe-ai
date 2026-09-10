<?php

use App\Models\Transcription;
use App\Models\User;

test('demo transcription creation works', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('transcriptions.store'), [
        'title' => 'Test Demo Transcription',
        'language' => 'en',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('transcriptions', [
        'title' => 'Test Demo Transcription',
        'user_id' => $user->id,
    ]);
    $this->assertDatabaseHas('media_files', [
        'user_id' => $user->id,
    ]);
});

test('created records belong to authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->post(route('transcriptions.store'), [
        'title' => 'Ownership Test',
    ]);

    $transcription = Transcription::where('title', 'Ownership Test')->first();
    $this->assertNotNull($transcription);
    $this->assertEquals($user->id, $transcription->user_id);

    $mediaFile = $transcription->mediaFile;
    $this->assertEquals($user->id, $mediaFile->user_id);
});

test('redirect reaches transcription detail', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('transcriptions.store'), [
        'title' => 'Redirect Test',
    ]);

    $transcription = Transcription::where('title', 'Redirect Test')->first();
    $response->assertRedirect(route('transcriptions.show', $transcription));
});
