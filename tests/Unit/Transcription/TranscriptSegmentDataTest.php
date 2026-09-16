<?php

use App\Transcription\LanguageIdentifier;
use App\Transcription\TranscriptSegmentData;

it('creates a valid segment', function () {
    $segment = new TranscriptSegmentData(
        segmentIndex: 0,
        startSeconds: 0.0,
        endSeconds: 5.0,
        text: 'Hello world',
        language: LanguageIdentifier::English,
    );

    expect($segment->segmentIndex)->toBe(0);
    expect($segment->startSeconds)->toBe(0.0);
    expect($segment->endSeconds)->toBe(5.0);
    expect($segment->text)->toBe('Hello world');
    expect($segment->language)->toBe(LanguageIdentifier::English);
});

it('rejects negative segment index', function () {
    new TranscriptSegmentData(
        segmentIndex: -1,
        startSeconds: 0.0,
        endSeconds: 5.0,
        text: 'Hello',
        language: LanguageIdentifier::English,
    );
})->throws(InvalidArgumentException::class, 'Segment index must be non-negative.');

it('rejects negative start seconds', function () {
    new TranscriptSegmentData(
        segmentIndex: 0,
        startSeconds: -1.0,
        endSeconds: 5.0,
        text: 'Hello',
        language: LanguageIdentifier::English,
    );
})->throws(InvalidArgumentException::class, 'Start seconds must be non-negative.');

it('rejects end before start', function () {
    new TranscriptSegmentData(
        segmentIndex: 0,
        startSeconds: 5.0,
        endSeconds: 3.0,
        text: 'Hello',
        language: LanguageIdentifier::English,
    );
})->throws(InvalidArgumentException::class, 'End seconds must be greater than or equal to start seconds.');

it('allows zero-length segment', function () {
    $segment = new TranscriptSegmentData(
        segmentIndex: 0,
        startSeconds: 5.0,
        endSeconds: 5.0,
        text: '',
        language: LanguageIdentifier::Undetermined,
    );

    expect($segment->startSeconds)->toBe(5.0);
    expect($segment->endSeconds)->toBe(5.0);
});

it('supports mixed language segments', function () {
    $segments = [
        new TranscriptSegmentData(0, 0.0, 3.0, 'Hello', LanguageIdentifier::English),
        new TranscriptSegmentData(1, 3.0, 6.0, 'Selamat datang', LanguageIdentifier::Malay),
        new TranscriptSegmentData(2, 6.0, 9.0, '你好', LanguageIdentifier::Chinese),
        new TranscriptSegmentData(3, 9.0, 12.0, 'வணக்கம்', LanguageIdentifier::Tamil),
    ];

    expect($segments)->toHaveCount(4);
    expect($segments[0]->language)->toBe(LanguageIdentifier::English);
    expect($segments[1]->language)->toBe(LanguageIdentifier::Malay);
    expect($segments[2]->language)->toBe(LanguageIdentifier::Chinese);
    expect($segments[3]->language)->toBe(LanguageIdentifier::Tamil);
});
