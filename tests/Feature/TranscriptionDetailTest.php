<?php

use App\Models\Transcription;
use App\Models\User;

test('transcription detail renders seeded segments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Hello world.',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));
    $response->assertOk();
    $response->assertSee('Hello world.');
    $response->assertSee('00:00');
});

test('transcription detail shows details tab', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('transcriptions.show', $transcription));
    $response->assertOk();
    $response->assertSee('Details');
});

test('transcription detail shows processing tab', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $response = $this->get(route('transcriptions.show', $transcription));
    $response->assertOk();
    $response->assertSee('Processing');
});
