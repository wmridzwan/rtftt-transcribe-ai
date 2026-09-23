<?php

use App\Models\Transcription;
use App\Models\User;

function navigationTranscription(User $user): Transcription
{
    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);

    $segments = [
        ['segment_index' => 0, 'start_seconds' => 0, 'end_seconds' => 5, 'text' => 'Salam sejahtera.', 'language' => 'ms'],
        ['segment_index' => 1, 'start_seconds' => 5, 'end_seconds' => 10, 'text' => 'Welcome aboard.', 'language' => 'en'],
        ['segment_index' => 2, 'start_seconds' => 10, 'end_seconds' => 15, 'text' => '欢迎登机。', 'language' => 'zh'],
        ['segment_index' => 3, 'start_seconds' => 15, 'end_seconds' => 20, 'text' => 'வணக்கம்.', 'language' => 'ta'],
        ['segment_index' => 4, 'start_seconds' => 20, 'end_seconds' => 25, 'text' => 'mumble.', 'language' => 'und'],
    ];

    foreach ($segments as $segment) {
        $transcription->segments()->create($segment);
    }

    return $transcription;
}

it('renders keyboard navigation and language filter controls in the workspace', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = navigationTranscription($user);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('data-transcript-region', false);
    $response->assertSee('data-transcript-language-filter', false);
    $response->assertSee('data-transcript-nav-hint', false);
    $response->assertSee('navigateNext()', false);
    $response->assertSee('navigatePrevious()', false);
    $response->assertSee('jumpToFirst()', false);
    $response->assertSee('jumpToLast()', false);
    $response->assertSee('x-model="languageFilter"', false);
    $response->assertSee('tabindex="0"', false);
    $response->assertSee('aria-label="Transcript segments"', false);
})->group('p6-006');

it('exposes per-segment language on the row for client-side filtering', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = navigationTranscription($user);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('data-segment-row', false);
    $response->assertSee('data-segment-index="0"', false);
    $response->assertSee('data-segment-language="ms"', false);
    $response->assertSee('data-segment-language="zh"', false);
    $response->assertSee('data-segment-language="ta"', false);
    $response->assertSee('data-segment-language="und"', false);
})->group('p6-006');

it('renders a filter option for each language present in canonical order', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = navigationTranscription($user);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('All languages');
    $response->assertSee('<option value="ms">MS</option>', false);
    $response->assertSee('<option value="en">EN</option>', false);
    $response->assertSee('<option value="zh">ZH</option>', false);
    $response->assertSee('<option value="ta">TA</option>', false);
    $response->assertSee('<option value="und">UND</option>', false);

    $html = $response->getContent();
    $msAt = strpos($html, '<option value="ms">');
    $enAt = strpos($html, '<option value="en">');
    $zhAt = strpos($html, '<option value="zh">');
    $taAt = strpos($html, '<option value="ta">');
    $undAt = strpos($html, '<option value="und">');

    expect($msAt)->toBeLessThan($enAt)
        ->and($enAt)->toBeLessThan($zhAt)
        ->and($zhAt)->toBeLessThan($taAt)
        ->and($taAt)->toBeLessThan($undAt);
})->group('p6-006');

it('offers only the languages present, not a fixed list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $transcription->segments()->create([
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Only English.',
        'language' => 'en',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('<option value="en">EN</option>', false);
    $response->assertDontSee('<option value="zh">', false);
    $response->assertDontSee('<option value="ta">', false);
})->group('p6-006');

it('does not render navigation or filter controls when there are no segments', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->id,
        'speech_detected' => false,
        'full_text' => '',
    ]);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertDontSee('data-transcript-language-filter', false);
    $response->assertSee('No transcript segments');
})->group('p6-006');

it('preserves the existing search and copy surface alongside navigation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $transcription = navigationTranscription($user);

    $response = $this->get(route('transcriptions.show', $transcription));

    $response->assertOk();
    $response->assertSee('Search transcript');
    $response->assertSee('Copy transcript');
    $response->assertSee('transcriptSearch', false);
    $response->assertSee('copySegment(', false);
    $response->assertSee('Salam sejahtera.');
})->group('p6-006');
