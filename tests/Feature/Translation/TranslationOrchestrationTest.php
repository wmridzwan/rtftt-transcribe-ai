<?php

use App\Actions\TranslationOrchestrator;
use App\Jobs\ProcessTranslation;
use App\Models\Transcription;
use App\Models\Translation;
use App\Translation\TranslationException;
use App\Translation\TranslationProvider;
use App\Translation\TranslationStatus;
use App\Translation\TranslationTarget;
use Illuminate\Support\Facades\Queue;
use Tests\Support\RecordingTranslationProvider;

it('creates a queued translation and dispatches the job', function () {
    Queue::fake();

    $transcription = translationSource();

    $translation = app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);

    expect($translation->status)->toBe(TranslationStatus::Queued)
        ->and($translation->target_language)->toBe(TranslationTarget::Malay)
        ->and($translation->transcription_id)->toBe($transcription->getKey());

    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('is idempotent across repeated requests for the same target', function () {
    Queue::fake();

    $transcription = translationSource();
    $orchestrator = app(TranslationOrchestrator::class);

    $first = $orchestrator->request($transcription, TranslationTarget::Malay);
    $second = $orchestrator->request($transcription, TranslationTarget::Malay);

    expect($second->getKey())->toBe($first->getKey());

    Queue::assertPushed(ProcessTranslation::class, 1);
});

it('returns an existing completed translation without dispatching', function () {
    Queue::fake();

    $transcription = translationSource();
    $completed = Translation::factory()->completed()->create([
        'transcription_id' => $transcription->getKey(),
        'target_language' => 'ms',
    ]);

    $translation = app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);

    expect($translation->getKey())->toBe($completed->getKey())
        ->and($translation->status)->toBe(TranslationStatus::Completed);

    Queue::assertNotPushed(ProcessTranslation::class);
});

it('allows multiple targets for the same source', function () {
    Queue::fake();

    $transcription = translationSource();
    $orchestrator = app(TranslationOrchestrator::class);

    $malay = $orchestrator->request($transcription, TranslationTarget::Malay);
    $chinese = $orchestrator->request($transcription, TranslationTarget::Chinese);

    expect($malay->getKey())->not->toBe($chinese->getKey());
});

it('rejects a transcription that is not completed', function () {
    $transcription = Transcription::factory()->transcribing()->create();

    expect(fn () => app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay))
        ->toThrow(TranslationException::class);
});

it('completes end to end with a synchronous queue and provider', function () {
    app()->instance(TranslationProvider::class, new RecordingTranslationProvider(alignedTranslationResult()));

    $transcription = translationSource();

    $translation = app(TranslationOrchestrator::class)->request($transcription, TranslationTarget::Malay);

    expect($translation->fresh()->status)->toBe(TranslationStatus::Completed)
        ->and($translation->segments()->count())->toBe(2)
        ->and($transcription->fresh()->full_text)->toBe('Hello Welcome');
});
