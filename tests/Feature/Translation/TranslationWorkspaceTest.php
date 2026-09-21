<?php

use App\Jobs\ProcessTranslation;
use App\Models\Transcription;
use App\Models\TranscriptionSegment;
use App\Models\Translation;
use App\Models\TranslationSegment;
use App\Models\User;
use App\Transcription\LanguageIdentifier;
use App\Translation\TranslationFailure;
use App\Translation\TranslationProvider;
use App\Translation\TranslationResult;
use App\Translation\TranslationSegmentData;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecordingTranslationProvider;

/*
 * P5-006 translation workspace: authorization, states, start/retry actions.
 * Deterministic test doubles only; the real provider/model is a P5-008 gate.
 */

function workspaceScenario(?User $owner = null): array
{
    $owner ??= User::factory()->create();

    $transcription = Transcription::factory()->completed()->create([
        'user_id' => $owner->getKey(),
        'title' => 'Weekly Meeting',
        'detected_language' => 'en',
    ]);

    foreach ([[0, 0, 5, 'Hello everyone'], [1, 5, 10, 'Welcome back']] as [$index, $start, $end, $text]) {
        TranscriptionSegment::factory()->create([
            'transcription_id' => $transcription->getKey(),
            'segment_index' => $index,
            'start_seconds' => $start,
            'end_seconds' => $end,
            'text' => $text,
            'language' => 'en',
        ]);
    }

    return [$owner, $transcription];
}

function workspaceCompletedTranslation(Transcription $transcription, string $target = 'ms', array $texts = ['Hai semua', 'Selamat kembali']): Translation
{
    $translation = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => $target,
        'attempt_token' => 'done-token',
        'dispatched_at' => now(),
        'full_text' => implode(' ', $texts),
    ]);

    foreach ($texts as $index => $text) {
        TranslationSegment::factory()->create([
            'translation_id' => $translation->getKey(),
            'segment_index' => $index,
            'start_seconds' => $index * 5,
            'end_seconds' => ($index + 1) * 5,
            'text' => $text,
            'source_language' => 'en',
        ]);
    }

    return $translation;
}

// --- authorization / isolation ------------------------------------------------

it('redirects guests to login for every workspace route', function (): void {
    [, $transcription] = workspaceScenario();
    $translation = workspaceCompletedTranslation($transcription);

    $this->get(route('transcriptions.translations.show', $transcription))->assertRedirect(route('login'));
    $this->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'ms'])->assertRedirect(route('login'));
    $this->get(route('translations.status', $translation))->assertRedirect(route('login'));
    $this->post(route('translations.retry', $translation))->assertRedirect(route('login'));
});

it('denies another user every workspace read and mutation without touching state', function (): void {
    Queue::fake();

    [, $transcription] = workspaceScenario();
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'en',
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('transcriptions.translations.show', $transcription))->assertForbidden();
    $this->actingAs($stranger)->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'ms'])->assertForbidden();
    $this->actingAs($stranger)->get(route('translations.status', $failed))->assertForbidden();
    $this->actingAs($stranger)->post(route('translations.retry', $failed))->assertForbidden();

    expect(Translation::query()->count())->toBe(1)
        ->and($failed->fresh()->status)->toBe(TranslationStatus::Failed);
    Queue::assertNothingPushed();
});

it('authorizes before validating so a stranger learns nothing about valid targets', function (): void {
    [, $transcription] = workspaceScenario();

    $this->actingAs(User::factory()->create())
        ->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'not-a-language'])
        ->assertForbidden();
});

it('lets an admin use the workspace', function (): void {
    Queue::fake();

    [, $transcription] = workspaceScenario();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('transcriptions.translations.show', $transcription))->assertOk();
    $this->actingAs($admin)->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'ms'])->assertRedirect();

    Queue::assertPushed(ProcessTranslation::class, 1);
});

// --- workspace states ----------------------------------------------------------

it('shows the empty state with a target selector for a completed transcript', function (): void {
    [$owner, $transcription] = workspaceScenario();

    $this->actingAs($owner)->get(route('transcriptions.translations.show', $transcription))
        ->assertOk()
        ->assertSee('data-translation-state="none"', false)
        ->assertSee('data-target-select', false)
        ->assertSee('Start translation')
        ->assertSee('Malay (Bahasa Melayu)')
        ->assertSee('Tamil (தமிழ்)');
});

it('explains that translation needs a completed transcript and offers no start action', function (): void {
    $owner = User::factory()->create();
    $transcription = Transcription::factory()->transcribing()->create(['user_id' => $owner->getKey()]);

    $this->actingAs($owner)->get(route('transcriptions.translations.show', $transcription))
        ->assertOk()
        ->assertSee('data-translation-unavailable', false)
        ->assertDontSee('data-translation-start', false);
});

it('renders queued and translating as accessible progress with automatic updates', function (string $status, string $label): void {
    [$owner, $transcription] = workspaceScenario();
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => $status,
        'dispatched_at' => now(),
    ]);

    $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'ms']))
        ->assertOk()
        ->assertSee('data-translation-state="'.$status.'"', false)
        ->assertSee('data-translation-progress', false)
        ->assertSee('role="status"', false)
        ->assertSee($label)
        ->assertSee('data-status-url="'.route('translations.status', $translation).'"', false)
        ->assertDontSee('data-translation-retry', false);
})->with([['queued', 'Queued'], ['translating', 'Translating']]);

it('offers to start again when a queued translation never reached the queue', function (): void {
    [$owner, $transcription] = workspaceScenario();
    Translation::factory()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'status' => 'queued',
        'dispatched_at' => null,
    ]);

    $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'ms']))
        ->assertOk()
        ->assertSee('data-translation-awaiting-dispatch', false)
        ->assertSee('Try again');
});

it('renders a completed translation with toggle, copy, export, and Unicode intact', function (): void {
    [$owner, $transcription] = workspaceScenario();
    $translation = workspaceCompletedTranslation($transcription, 'ta', ['வணக்கம் அனைவருக்கும்', 'மீண்டும் வருக']);
    $sourceBefore = $transcription->segments()->get()->map->only(['segment_index', 'text', 'start_seconds', 'end_seconds'])->all();

    $response = $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'ta']));

    $response->assertOk()
        ->assertSee('data-translation-state="completed"', false)
        ->assertSee('வணக்கம் அனைவருக்கும்')
        ->assertSee('மீண்டும் வருக')
        ->assertSee('Hello everyone')
        ->assertSee('data-view-toggle="translation"', false)
        ->assertSee('data-view-toggle="source"', false)
        ->assertSee('data-copy-full', false)
        ->assertSee('data-copy-segment="0"', false)
        ->assertSee('data-export="txt"', false)
        ->assertSee(route('translations.export.srt', $translation), false)
        ->assertSee(route('translations.export.vtt', $translation), false)
        ->assertSee(route('translations.export.docx', $translation), false);

    // Rendering is read-only: the source transcript is unchanged.
    expect($transcription->segments()->get()->map->only(['segment_index', 'text', 'start_seconds', 'end_seconds'])->all())->toBe($sourceBefore);
});

it('keeps a completed translation visible after a reload and defaults to it', function (): void {
    [$owner, $transcription] = workspaceScenario();
    workspaceCompletedTranslation($transcription, 'zh', ['大家好', '欢迎回来']);

    foreach (range(1, 2) as $ignored) {
        $this->actingAs($owner)->get(route('transcriptions.translations.show', $transcription))
            ->assertOk()
            ->assertSee('data-translation-state="completed"', false)
            ->assertSee('大家好');
    }
});

// --- failure UX: retryable vs non-retryable ------------------------------------------

it('offers Retry only for a retryable failure', function (): void {
    [$owner, $transcription] = workspaceScenario();
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);

    $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'ms']))
        ->assertOk()
        ->assertSee('data-translation-state="failed-retryable"', false)
        ->assertSee('data-translation-retry', false)
        ->assertSee(route('translations.retry', $failed), false)
        ->assertSee('The translation took too long and was stopped.')
        ->assertSee('Reference: PROVIDER_TIMEOUT');
});

it('shows a clear final failure with no Retry action for non-retryable failures', function (string $code): void {
    [$owner, $transcription] = workspaceScenario();
    Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => $code,
    ]);

    $failure = TranslationFailure::from($code);

    $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'ms']))
        ->assertOk()
        ->assertSee('data-translation-state="failed-final"', false)
        ->assertSee('data-retryable="false"', false)
        ->assertSee('data-translation-not-retryable', false)
        ->assertSee($failure->userMessage())
        ->assertSee('Reference: '.$code)
        ->assertDontSee('data-translation-retry', false)
        ->assertDontSee('data-translation-retry-form', false);
})->with(['CONFIGURATION_ERROR', 'MALFORMED_OUTPUT', 'PERSISTENCE_FAILED', 'UNSUPPORTED_SOURCE', 'MISSING_SEGMENTS', 'INVALID_REQUEST']);

it('never exposes credentials, endpoints, or provider internals in any failure message', function (): void {
    config()->set('translation.worker_token', 'super-secret-token');
    config()->set('translation.worker_url', 'http://internal-worker.local:8000');

    foreach (TranslationFailure::cases() as $failure) {
        expect($failure->userMessage())
            ->not->toContain('super-secret-token')
            ->not->toContain('internal-worker')
            ->not->toContain('token')
            ->not->toContain('Bearer')
            ->not->toContain('Exception');
    }
});

// --- start action ------------------------------------------------------------------

it('starts a translation for a valid target and returns to the workspace', function (): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();

    $this->actingAs($owner)
        ->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'zh'])
        ->assertRedirect(route('transcriptions.translations.show', [$transcription, 'target' => 'zh']))
        ->assertSessionHas('translation_notice');

    $translation = Translation::query()->sole();

    expect($translation->status)->toBe(TranslationStatus::Queued)
        ->and($translation->target_language->value)->toBe('zh')
        ->and($translation->dispatched_at)->not->toBeNull();
    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('converges double-submitted starts on one translation', function (): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($owner)
            ->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'ms'])
            ->assertRedirect();
    }

    expect(Translation::query()->count())->toBe(1);
    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('rejects an invalid or unsupported target safely without creating anything', function (?string $target): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();

    $this->actingAs($owner)
        ->from(route('transcriptions.translations.show', $transcription))
        ->post(route('transcriptions.translations.store', $transcription), ['target_language' => $target])
        ->assertRedirect(route('transcriptions.translations.show', $transcription))
        ->assertSessionHasErrors('target_language');

    expect(Translation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([null, '', 'und', 'fr', 'MS ', '../etc/passwd', 'ms; drop table translations']);

it('rejects an unsupported target in the workspace query without an error page', function (): void {
    [$owner, $transcription] = workspaceScenario();

    $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'und']))
        ->assertRedirect(route('transcriptions.translations.show', $transcription))
        ->assertSessionHas('translation_error');

    $this->actingAs($owner)->get(route('transcriptions.translations.show', $transcription).'?target[]=ms')
        ->assertRedirect(route('transcriptions.translations.show', $transcription));
});

it('reports a clear error when a transcript is not completed', function (): void {
    Queue::fake();

    $owner = User::factory()->create();
    $transcription = Transcription::factory()->transcribing()->create(['user_id' => $owner->getKey()]);

    $this->actingAs($owner)
        ->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'ms'])
        ->assertRedirect()
        ->assertSessionHas('translation_error');

    expect(Translation::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('does not leak internal detail when queueing fails', function (): void {
    [$owner, $transcription] = workspaceScenario();
    config()->set('translation.queue_connection', 'unconfigured-connection');

    $response = $this->actingAs($owner)
        ->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'ms']);

    $response->assertRedirect()->assertSessionHas('translation_error', 'The translation could not be queued. Please try again.');

    expect(session('translation_error'))->not->toContain('unconfigured-connection');
});

// --- retry action ----------------------------------------------------------------

it('retries a retryable failure after authorizing and reloading', function (): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_UNAVAILABLE',
        'attempt_token' => 'old-token',
    ]);

    $this->actingAs($owner)->post(route('translations.retry', $failed))
        ->assertRedirect(route('transcriptions.translations.show', [$transcription, 'target' => 'ms']))
        ->assertSessionHas('translation_notice');

    $fresh = $failed->fresh();

    expect($fresh->status)->toBe(TranslationStatus::Queued)
        ->and($fresh->attempt_token)->not->toBe('old-token');
    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('refuses to retry a non-retryable failure and leaves state untouched', function (): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'CONFIGURATION_ERROR',
    ]);

    $this->actingAs($owner)->post(route('translations.retry', $failed))
        ->assertRedirect()
        ->assertSessionHas('translation_error', 'This translation cannot be retried.');

    expect($failed->fresh()->status)->toBe(TranslationStatus::Failed)
        ->and($failed->fresh()->failure_code)->toBe(TranslationFailure::ConfigurationError);
    Queue::assertNothingPushed();
});

it('evaluates retry eligibility on the persisted row, not on stale page state', function (): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);

    // The page was rendered while the failure was retryable...
    $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'ms']))
        ->assertSee('data-translation-retry', false);

    // ...but the persisted failure became non-retryable before the click.
    $failed->forceFill(['failure_code' => 'MALFORMED_OUTPUT'])->save();

    $this->actingAs($owner)->post(route('translations.retry', $failed))->assertSessionHas('translation_error');

    expect($failed->fresh()->status)->toBe(TranslationStatus::Failed);
    Queue::assertNothingPushed();
});

it('converges a repeated retry click on the same attempt', function (): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_FAILED',
    ]);

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($owner)->post(route('translations.retry', $failed))
            ->assertRedirect()
            ->assertSessionMissing('translation_error');
    }

    expect(Translation::query()->count())->toBe(1);
    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('refuses to retry a completed translation', function (): void {
    Queue::fake();

    [$owner, $transcription] = workspaceScenario();
    $completed = workspaceCompletedTranslation($transcription);

    $this->actingAs($owner)->post(route('translations.retry', $completed))->assertSessionHas('translation_error');

    expect($completed->fresh()->status)->toBe(TranslationStatus::Completed);
    Queue::assertNothingPushed();
});

// --- status endpoint ----------------------------------------------------------------

it('serves a minimal status payload to the owner only', function (): void {
    [$owner, $transcription] = workspaceScenario();
    $failed = Translation::factory()->failed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
        'failure_code' => 'PROVIDER_TIMEOUT',
    ]);

    $this->actingAs($owner)->getJson(route('translations.status', $failed))
        ->assertOk()
        ->assertExactJson([
            'status' => 'failed',
            'awaiting_dispatch' => false,
            'failure_code' => 'PROVIDER_TIMEOUT',
            'retryable' => true,
        ]);
});

it('completes end to end through the queue with a deterministic provider and renders the result', function (): void {
    app()->instance(
        TranslationProvider::class,
        new RecordingTranslationProvider(new TranslationResult(
            targetLanguage: TranslationTarget::Malay,
            fullText: 'Hai semua Selamat datang',
            segments: [
                new TranslationSegmentData(0, 0.0, 5.0, 'Hai semua', LanguageIdentifier::English),
                new TranslationSegmentData(1, 5.0, 10.0, 'Selamat datang', LanguageIdentifier::English),
            ],
            provider: 'self-hosted',
            model: 'test-double',
        )),
    );

    [$owner, $transcription] = workspaceScenario();
    $this->actingAs($owner)->post(route('transcriptions.translations.store', $transcription), ['target_language' => 'ms'])->assertRedirect();

    $this->actingAs($owner)->get(route('transcriptions.translations.show', [$transcription, 'target' => 'ms']))
        ->assertOk()
        ->assertSee('data-translation-state="completed"', false)
        ->assertSee('Selamat datang');
});

// --- entry point --------------------------------------------------------------------

it('links to the translation workspace from a completed transcript only', function (): void {
    [$owner, $transcription] = workspaceScenario();

    $this->actingAs($owner)->get(route('transcriptions.show', $transcription))
        ->assertOk()
        ->assertSee('data-translate-link', false)
        ->assertSee(route('transcriptions.translations.show', $transcription), false);

    $processing = Transcription::factory()->transcribing()->create(['user_id' => $owner->getKey()]);

    $this->actingAs($owner)->get(route('transcriptions.show', $processing))
        ->assertOk()
        ->assertDontSee('data-translate-link', false);
});
