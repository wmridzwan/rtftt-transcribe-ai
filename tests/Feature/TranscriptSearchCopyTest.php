<?php

use App\Models\Transcription;
use App\Models\User;

it('renders client-side search and copy controls for a completed transcript with segments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'First line.',
        'language' => 'en',
    ]);
    $transcription->segments()->create([
        'segment_index' => 1,
        'start_seconds' => 5,
        'end_seconds' => 10,
        'text' => 'Second line.',
        'language' => 'en',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('Search transcript');
    $response->assertSee('Copy transcript');
    $response->assertSee('transcriptSearch', false);
    $response->assertSee('data-segment-text', false);
    $response->assertSee('fullText:', false);
    $response->assertSee('First line.');
    $response->assertSee('Second line.');
    $response->assertSee('aria-live="polite"', false);
    $response->assertSee('aria-label="Copy segment"', false);
});

it('does not render search controls when there are no segments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'speech_detected' => false,
        'full_text' => '',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertDontSee('Search transcript');
    $response->assertSee('No transcript segments');
});

it('supports multilingual segment text in the copy payload', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => '欢迎 வணக்கம்',
        'language' => 'zh',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('欢迎 வணக்கம்');
});
