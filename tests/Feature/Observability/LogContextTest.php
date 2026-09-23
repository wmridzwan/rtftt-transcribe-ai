<?php

use App\Models\ProcessingJob;
use App\Models\Transcription;
use App\Models\Translation;
use App\Models\User;
use App\Observability\LogContext;

it('builds transcription correlation context from the available ADR-017 fields', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $attempt = ProcessingJob::factory()->create(['transcription_id' => $transcription->id]);

    $context = LogContext::forTranscription($transcription, $attempt);

    expect($context)->toHaveKeys([
        'transcription_id',
        'media_file_id',
        'model',
        'processing_job_id',
        'attempt_number',
        'stage',
    ])
        ->and($context['transcription_id'])->toBe($transcription->getKey())
        ->and($context['processing_job_id'])->toBe($attempt->getKey())
        ->and($context['attempt_number'])->toBe(1);
})->group('p7-005');

it('counts attempt ordinals across attempts for the same transcription', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->create(['user_id' => $user->id]);

    ProcessingJob::factory()->create(['transcription_id' => $transcription->id]);
    $second = ProcessingJob::factory()->create(['transcription_id' => $transcription->id]);

    $context = LogContext::forTranscription($transcription, $second);

    expect($context['attempt_number'])->toBe(2);
})->group('p7-005');

it('omits attempt fields when no processing attempt is provided', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->create(['user_id' => $user->id]);

    $context = LogContext::forTranscription($transcription);

    expect($context)->toHaveKeys(['transcription_id', 'media_file_id', 'model'])
        ->and($context)->not->toHaveKey('processing_job_id');
})->group('p7-005');

it('builds translation correlation context', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->completed()->create(['user_id' => $user->id]);
    $translation = Translation::factory()->create([
        'transcription_id' => $transcription->id,
        'target_language' => 'ms',
    ]);

    $context = LogContext::forTranslation($translation);

    expect($context)->toHaveKeys([
        'translation_id',
        'transcription_id',
        'target_language',
        'model',
    ])
        ->and($context['translation_id'])->toBe($translation->getKey())
        ->and($context['transcription_id'])->toBe($transcription->getKey())
        ->and($context['target_language'])->toBe('ms');
})->group('p7-005');

it('returns a partial translation context instead of throwing on a bad model', function () {
    $translation = new Translation;

    $context = LogContext::forTranslation($translation, ['extra' => 'kept']);

    expect($context)->toBeArray()
        ->and($context['extra'])->toBe('kept');
})->group('p7-005');

it('returns a partial transcription context instead of throwing on a bad attempt', function () {
    $user = User::factory()->create();
    $transcription = Transcription::factory()->create(['user_id' => $user->id]);
    $attempt = new ProcessingJob;

    $context = LogContext::forTranscription($transcription, $attempt, ['extra' => 'kept']);

    expect($context)->toBeArray()
        ->and($context['transcription_id'])->toBe($transcription->getKey())
        ->and($context['extra'])->toBe('kept');
})->group('p7-005');
