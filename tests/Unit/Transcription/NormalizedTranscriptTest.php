<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\NormalizedTranscript;
use App\Transcription\TranscriptSegmentData;

it('creates a valid transcript with segments', function () {
    $segments = [
        new TranscriptSegmentData(0, 0.0, 5.0, 'Hello world', LanguageIdentifier::English),
        new TranscriptSegmentData(1, 5.0, 10.0, 'Selamat pagi', LanguageIdentifier::Malay),
    ];

    $transcript = new NormalizedTranscript(
        text: 'Hello world. Selamat pagi.',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 10.0,
        speechDetected: true,
        segments: $segments,
    );

    expect($transcript->text)->toBe('Hello world. Selamat pagi.');
    expect($transcript->detectedLanguage)->toBe(LanguageIdentifier::English);
    expect($transcript->durationSeconds)->toBe(10.0);
    expect($transcript->speechDetected)->toBeTrue();
    expect($transcript->segments)->toHaveCount(2);
});

it('creates a no-speech result', function () {
    $transcript = NormalizedTranscript::noSpeech(30.0);

    expect($transcript->text)->toBe('');
    expect($transcript->detectedLanguage)->toBe(LanguageIdentifier::Undetermined);
    expect($transcript->durationSeconds)->toBe(30.0);
    expect($transcript->speechDetected)->toBeFalse();
    expect($transcript->segments)->toBe([]);
});

it('rejects negative duration', function () {
    new NormalizedTranscript(
        text: '',
        detectedLanguage: LanguageIdentifier::Undetermined,
        durationSeconds: -1.0,
        speechDetected: false,
        segments: [],
    );
})->throws(InvalidArgumentException::class, 'Duration seconds must be non-negative.');

it('rejects text when speech not detected', function () {
    new NormalizedTranscript(
        text: 'Hello',
        detectedLanguage: LanguageIdentifier::English,
        durationSeconds: 10.0,
        speechDetected: false,
        segments: [],
    );
})->throws(InvalidArgumentException::class, 'Speech not detected but text is non-empty.');

it('rejects segments when speech not detected', function () {
    $segments = [
        new TranscriptSegmentData(0, 0.0, 5.0, 'Hello', LanguageIdentifier::English),
    ];

    new NormalizedTranscript(
        text: '',
        detectedLanguage: LanguageIdentifier::Undetermined,
        durationSeconds: 10.0,
        speechDetected: false,
        segments: $segments,
    );
})->throws(InvalidArgumentException::class, 'Speech not detected but segments are non-empty.');

it('allows empty text with speech detected and no segments', function () {
    $transcript = new NormalizedTranscript(
        text: '',
        detectedLanguage: LanguageIdentifier::Undetermined,
        durationSeconds: 10.0,
        speechDetected: true,
        segments: [],
    );

    expect($transcript->speechDetected)->toBeTrue();
    expect($transcript->text)->toBe('');
    expect($transcript->segments)->toBe([]);
});
