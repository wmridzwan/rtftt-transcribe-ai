<?php

use App\Models\TranscriptionSegment;
use App\TranscriptExperience\TranscriptCopy;

function makeCopySegment(int $index, string $text): TranscriptionSegment
{
    $segment = new TranscriptionSegment;
    $segment->segment_index = $index;
    $segment->text = $text;

    return $segment;
}

it('joins ordered segment text with single newlines and no timestamps', function () {
    $segments = [
        makeCopySegment(0, 'First line.'),
        makeCopySegment(1, 'Second line.'),
    ];

    expect(TranscriptCopy::fullText($segments))->toBe("First line.\nSecond line.");
});

it('preserves multilingual unicode verbatim', function () {
    $segments = [
        makeCopySegment(0, 'Selamat datang'),
        makeCopySegment(1, '欢迎'),
        makeCopySegment(2, 'வணக்கம்'),
        makeCopySegment(3, 'und'),
    ];

    expect(TranscriptCopy::fullText($segments))->toBe("Selamat datang\n欢迎\nவணக்கம்\nund");
});

it('returns an empty string when there are no segments', function () {
    expect(TranscriptCopy::fullText([]))->toBe('');
});

it('copies a single segment text without a timestamp', function () {
    expect(TranscriptCopy::segmentText(makeCopySegment(0, 'Only this.')))->toBe('Only this.');
});
