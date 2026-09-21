<?php

use App\Enums\UserRole;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Models\User;
use App\Translation\TranslationProvider;
use Tests\Support\RecordingTranslationProvider;

function exportScenario(): array
{
    $user = User::factory()->create();

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $user->getKey(),
        'title' => 'Weekly Meeting',
    ]);

    TranscriptionSegment::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Hello',
        'language' => 'en',
    ]);
    TranscriptionSegment::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'segment_index' => 1,
        'start_seconds' => 5,
        'end_seconds' => 10,
        'text' => 'Welcome',
        'language' => 'en',
    ]);

    $translation = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'full_text' => 'Hai Selamat datang',
    ]);

    TranslationSegment::factory()->create([
        'translation_id' => $translation->getKey(),
        'segment_index' => 0,
        'start_seconds' => 0,
        'end_seconds' => 5,
        'text' => 'Hai',
        'source_language' => 'en',
    ]);
    TranslationSegment::factory()->create([
        'translation_id' => $translation->getKey(),
        'segment_index' => 1,
        'start_seconds' => 5,
        'end_seconds' => 10,
        'text' => 'Selamat datang',
        'source_language' => 'en',
    ]);

    return [$user, $translation];
}

it('exports a translated txt with a target-suffixed filename', function () {
    [$user, $translation] = exportScenario();

    $response = $this->actingAs($user)->get(route('translations.export.txt', $translation));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain')
        ->and($response->headers->get('Content-Disposition'))->toContain('weekly-meeting-ms.txt')
        ->and($response->getContent())->toContain('Hai')
        ->and($response->getContent())->toContain('Selamat datang');
});

it('exports translated srt with inherited millisecond timestamps', function () {
    [$user, $translation] = exportScenario();

    $response = $this->actingAs($user)->get(route('translations.export.srt', $translation));

    $response->assertOk();
    expect($response->getContent())->toContain('00:00:00,000 --> 00:00:05,000')
        ->and($response->getContent())->toContain('00:00:05,000 --> 00:00:10,000')
        ->and($response->getContent())->toContain('Hai');
});

it('exports translated vtt with a webvtt header', function () {
    [$user, $translation] = exportScenario();

    $response = $this->actingAs($user)->get(route('translations.export.vtt', $translation));

    $response->assertOk();
    expect($response->getContent())->toStartWith('WEBVTT')
        ->and($response->getContent())->toContain('00:00:00.000 --> 00:00:05.000');
});

it('exports a translated docx', function () {
    [$user, $translation] = exportScenario();

    $response = $this->actingAs($user)->get(route('translations.export.docx', $translation));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('wordprocessingml')
        ->and(strlen((string) $response->getContent()))->toBeGreaterThan(0);
});

it('blocks export of a non-completed translation', function () {
    [$user, $translation] = exportScenario();
    $translation->forceFill(['status' => 'queued'])->save();

    $this->actingAs($user)->get(route('translations.export.txt', $translation))->assertForbidden();
});

it('denies export to a non-owner', function () {
    [, $translation] = exportScenario();
    $other = User::factory()->create();

    $this->actingAs($other)->get(route('translations.export.txt', $translation))->assertForbidden();
});

it('allows an admin to export', function () {
    [, $translation] = exportScenario();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('translations.export.txt', $translation))->assertOk();
});

it('never invokes the translation provider during export', function () {
    [$user, $translation] = exportScenario();

    $provider = new RecordingTranslationProvider;
    app()->instance(TranslationProvider::class, $provider);

    $this->actingAs($user)->get(route('translations.export.srt', $translation))->assertOk();

    expect($provider->calls)->toBe(0);
});
