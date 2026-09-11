<?php

use App\Models\Transcription;
use App\Models\User;

test('txt export returns correct format', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'title' => 'My Test Transcript',
    ]);

    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Hello world.',
    ]);

    $transcription->segments()->create([
        'segment_index' => 1,
        'start_seconds' => 5,
        'end_seconds' => 10,
        'text' => 'Second segment.',
    ]);

    $response = $this->get(route('transcriptions.export.txt', $transcription));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="my-test-transcript.txt"');

    $content = $response->getContent();
    expect($content)->toContain('My Test Transcript');
    expect($content)->toContain('Hello world.');
    expect($content)->toContain('Second segment.');
});

test('txt export falls back to full_text when no segments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'title' => 'No Segments',
        'full_text' => 'Fallback text content.',
    ]);

    $response = $this->get(route('transcriptions.export.txt', $transcription));

    $response->assertOk();
    $content = $response->getContent();
    expect($content)->toContain('Fallback text content.');
});

test('srt export returns correct format with segment numbers and timestamps', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'title' => 'SRT Test',
    ]);

    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'First line.',
    ]);

    $transcription->segments()->create([
        'segment_index' => 1,
        'start_seconds' => 65,
        'end_seconds' => 70,
        'text' => 'Second line.',
    ]);

    $response = $this->get(route('transcriptions.export.srt', $transcription));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/x-subrip; charset=utf-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="srt-test.srt"');

    $content = $response->getContent();
    expect($content)->toContain("1\n00:00:00,000 --> 00:00:05,000\nFirst line.");
    expect($content)->toContain("2\n00:01:05,000 --> 00:01:10,000\nSecond line.");
});

test('vtt export returns correct format with webvtt header', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'title' => 'VTT Test',
    ]);

    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Hello.',
    ]);

    $response = $this->get(route('transcriptions.export.vtt', $transcription));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/vtt; charset=utf-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="vtt-test.vtt"');

    $content = $response->getContent();
    expect($content)->toStartWith('WEBVTT');
    expect($content)->toContain('00:00:00.000 --> 00:00:05.000');
    expect($content)->toContain('Hello.');
});

test('docx export returns a downloadable file', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'title' => 'DOCX Test',
    ]);

    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Document content.',
    ]);

    $response = $this->get(route('transcriptions.export.docx', $transcription));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    $response->assertHeader('Content-Disposition', 'attachment; filename="docx-test.docx"');

    $content = $response->getContent();
    expect(strlen($content))->toBeGreaterThan(0);
});

test('export is forbidden for non-owner', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $otherUser->id,
    ]);

    $this->get(route('transcriptions.export.txt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.srt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.vtt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.docx', $transcription))->assertForbidden();
});

test('export returns 403 for non-completed transcriptions', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->transcribing()->create([
        'user_id' => $user->id,
    ]);

    $this->get(route('transcriptions.export.txt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.srt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.vtt', $transcription))->assertForbidden();
    $this->get(route('transcriptions.export.docx', $transcription))->assertForbidden();
});

test('export returns 403 for unauthenticated users', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
    ]);

    $this->get(route('transcriptions.export.txt', $transcription))->assertRedirect('/login');
    $this->get(route('transcriptions.export.srt', $transcription))->assertRedirect('/login');
    $this->get(route('transcriptions.export.vtt', $transcription))->assertRedirect('/login');
    $this->get(route('transcriptions.export.docx', $transcription))->assertRedirect('/login');
});

test('export controls depend on completed status and use normal links', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $completed = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $response = $this->get(route('transcriptions.show', $completed));
    $exportUrl = route('transcriptions.export.txt', $completed);
    $position = strpos($response->getContent(), $exportUrl);

    expect($position)->toBeInt()
        ->and(substr($response->getContent(), max(0, $position - 80), 160))->not->toContain('wire:navigate');

    $queued = Transcription::factory()->queued()->create(['user_id' => $user->id]);
    $this->get(route('transcriptions.show', $queued))
        ->assertSee('disabled', false);
});
