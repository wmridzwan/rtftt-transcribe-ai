<?php

use App\Actions\TranscriptionResultWriter;
use App\Enums\ProcessingStatus;
use App\Enums\TranscriptionStatus;
use App\Models\TranscriptionSegment;
use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptSegmentData;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TranscriptionFixtures;

beforeEach(function (): void {
    Storage::fake('local');
});

function p3005ThreeSegmentResult(): NormalizedTranscript
{
    return new NormalizedTranscript(
        text: 'One Two Three',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 6.0,
        speechDetected: true,
        segments: [
            new TranscriptSegmentData(0, 0.0, 2.0, 'One', LanguageIdentifier::English),
            new TranscriptSegmentData(1, 2.0, 4.0, 'Two', LanguageIdentifier::English),
            new TranscriptSegmentData(2, 4.0, 6.0, 'Three', LanguageIdentifier::English),
        ],
    );
}

test('a segment persistence failure never exposes a completed transcription', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    $calls = 0;

    TranscriptionSegment::creating(function () use (&$calls): void {
        $calls++;

        if ($calls === 2) {
            throw new RuntimeException('Injected segment persistence failure.');
        }
    });

    try {
        expect(fn () => app(TranscriptionResultWriter::class)
            ->persist($transcription, $attempt, p3005ThreeSegmentResult(), 'large-v3'))
            ->toThrow(RuntimeException::class, 'Injected segment persistence failure.');
    } finally {
        TranscriptionSegment::flushEventListeners();
    }

    $transcription->refresh();
    $attempt->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Transcribing)
        ->and($transcription->full_text)->toBeNull()
        ->and($transcription->detected_language)->toBeNull()
        ->and($transcription->completed_at)->toBeNull()
        ->and($transcription->segments()->count())->toBe(0)
        ->and($attempt->status)->toBe(ProcessingStatus::Running)
        ->and($attempt->completed_at)->toBeNull();
});

test('completed status is only visible together with the full segment set', function () {
    ['transcription' => $transcription, 'attempt' => $attempt] = TranscriptionFixtures::scenario(
        TranscriptionStatus::Transcribing,
        ProcessingStatus::Running,
    );

    app(TranscriptionResultWriter::class)->persist($transcription, $attempt, p3005ThreeSegmentResult(), 'large-v3');

    $transcription->refresh();

    expect($transcription->status)->toBe(TranscriptionStatus::Completed)
        ->and($transcription->segments()->count())->toBe(3)
        ->and($transcription->full_text)->toBe('One Two Three');
});
