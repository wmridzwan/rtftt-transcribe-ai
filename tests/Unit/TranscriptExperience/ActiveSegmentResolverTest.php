<?php

use App\TranscriptExperience\ActiveSegmentResolver;

function segmentRow(int $index, float $start, float $end): array
{
    return [
        'segment_index' => $index,
        'start_seconds' => $start,
        'end_seconds' => $end,
    ];
}

it('resolves the segment containing the current time', function () {
    $segments = [
        segmentRow(0, 0.0, 5.0),
        segmentRow(1, 5.0, 10.0),
        segmentRow(2, 15.0, 20.0),
    ];

    $active = (new ActiveSegmentResolver)->resolve($segments, 2.0);

    expect($active)->not->toBeNull()
        ->and($active['segment_index'])->toBe(0);
});

it('treats the exact start as active and the exact end as inactive', function () {
    $segments = [
        segmentRow(0, 0.0, 5.0),
        segmentRow(1, 5.0, 10.0),
    ];

    $resolver = new ActiveSegmentResolver;

    expect($resolver->resolve($segments, 5.0)['segment_index'])->toBe(1)
        ->and($resolver->resolve($segments, 4.999)['segment_index'])->toBe(0)
        ->and($resolver->resolve($segments, 10.0))->toBeNull();
});

it('returns no active segment in gaps', function () {
    $segments = [
        segmentRow(0, 0.0, 5.0),
        segmentRow(1, 10.0, 15.0),
    ];

    expect((new ActiveSegmentResolver)->resolve($segments, 7.5))->toBeNull();
});

it('never treats a zero-length segment as active', function () {
    $segments = [
        segmentRow(0, 0.0, 0.0),
        segmentRow(1, 2.0, 3.0),
    ];

    $resolver = new ActiveSegmentResolver;

    expect($resolver->resolve($segments, 0.0))->toBeNull()
        ->and($resolver->resolve($segments, 2.5)['segment_index'])->toBe(1);
});

it('returns no active segment before the first or after the final segment', function () {
    $segments = [
        segmentRow(0, 5.0, 10.0),
        segmentRow(1, 15.0, 20.0),
    ];

    $resolver = new ActiveSegmentResolver;

    expect($resolver->resolve($segments, 4.999))->toBeNull()
        ->and($resolver->resolve($segments, 20.0))->toBeNull()
        ->and($resolver->resolve($segments, 999.0))->toBeNull();
});

it('resolves overlapping segments to the lowest segment index', function () {
    $segments = [
        segmentRow(0, 0.0, 10.0),
        segmentRow(1, 5.0, 15.0),
    ];

    $active = (new ActiveSegmentResolver)->resolve($segments, 7.0);

    expect($active['segment_index'])->toBe(0);
});

it('orders by segment index rather than input array position', function () {
    $segments = [
        segmentRow(2, 5.0, 15.0),
        segmentRow(0, 0.0, 10.0),
        segmentRow(1, 10.0, 20.0),
    ];

    $active = (new ActiveSegmentResolver)->resolve($segments, 7.0);

    expect($active['segment_index'])->toBe(0);
});

it('resolves Eloquent-style objects exposing segment properties', function () {
    $segments = [
        (object) ['segment_index' => 0, 'start_seconds' => 0.0, 'end_seconds' => 5.0],
        (object) ['segment_index' => 1, 'start_seconds' => 5.0, 'end_seconds' => 10.0],
    ];

    $active = (new ActiveSegmentResolver)->resolve($segments, 7.0);

    expect($active->segment_index)->toBe(1);
});
