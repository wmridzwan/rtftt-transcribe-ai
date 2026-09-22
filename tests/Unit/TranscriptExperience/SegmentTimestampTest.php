<?php

use App\TranscriptExperience\SegmentTimestamp;

it('formats persisted decimal(12,3) values across all representations', function () {
    $cases = [
        ['seconds' => '5.999', 'display' => '00:05.999', 'srt' => '00:00:05,999', 'vtt' => '00:00:05.999'],
        ['seconds' => '6.000', 'display' => '00:06.000', 'srt' => '00:00:06,000', 'vtt' => '00:00:06.000'],
        ['seconds' => '65.123', 'display' => '01:05.123', 'srt' => '00:01:05,123', 'vtt' => '00:01:05.123'],
        ['seconds' => '3600.001', 'display' => '1:00:00.001', 'srt' => '01:00:00,001', 'vtt' => '01:00:00.001'],
    ];

    foreach ($cases as $case) {
        $timestamp = SegmentTimestamp::fromSeconds($case['seconds']);

        expect($timestamp->display())->toBe($case['display'])
            ->and($timestamp->srt())->toBe($case['srt'])
            ->and($timestamp->vtt())->toBe($case['vtt']);
    }
});

it('preserves the persisted numeric seek value without re-rounding', function () {
    expect(SegmentTimestamp::fromSeconds('5.999')->seek())->toBe('5.999')
        ->and(SegmentTimestamp::fromSeconds('65.123')->seek())->toBe('65.123')
        ->and(SegmentTimestamp::fromSeconds('3600.001')->seek())->toBe('3600.001');
});

it('preserves float seek values as their exact numeric value', function () {
    expect((float) SegmentTimestamp::fromSeconds(5.999)->seek())->toBe(5.999)
        ->and((float) SegmentTimestamp::fromSeconds(65.123)->seek())->toBe(65.123)
        ->and((float) SegmentTimestamp::fromSeconds(3600.001)->seek())->toBe(3600.001);
});

it('formats ordinary whole seconds with millisecond zero padding', function () {
    $timestamp = SegmentTimestamp::fromSeconds(0);

    expect($timestamp->display())->toBe('00:00.000')
        ->and($timestamp->seek())->toBe('0')
        ->and($timestamp->srt())->toBe('00:00:00,000')
        ->and($timestamp->vtt())->toBe('00:00:00.000');
});

it('defensively normalizes higher-precision input with millisecond carry', function () {
    $timestamp = SegmentTimestamp::fromSeconds(5.9996);

    expect($timestamp->display())->toBe('00:06.000')
        ->and($timestamp->srt())->toBe('00:00:06,000')
        ->and($timestamp->vtt())->toBe('00:00:06.000');
});

it('never emits 1000 milliseconds', function () {
    foreach ([5.9995, 5.9999, 59.9999, 3599.9999] as $seconds) {
        $timestamp = SegmentTimestamp::fromSeconds($seconds);

        expect($timestamp->display())->not->toContain('.1000')
            ->and($timestamp->srt())->not->toContain(',1000')
            ->and($timestamp->vtt())->not->toContain('.1000');
    }
});

it('rejects negative timestamps', function () {
    SegmentTimestamp::fromSeconds(-0.001);
})->throws(InvalidArgumentException::class);

it('rejects NaN timestamps', function () {
    SegmentTimestamp::fromSeconds(NAN);
})->throws(InvalidArgumentException::class);

it('rejects infinite timestamps', function () {
    SegmentTimestamp::fromSeconds(INF);
})->throws(InvalidArgumentException::class);

it('rejects non-numeric string timestamps', function () {
    SegmentTimestamp::fromSeconds('not-a-timestamp');
})->throws(InvalidArgumentException::class);
