<?php

namespace App\TranscriptExperience;

use InvalidArgumentException;

/**
 * Deterministic active-segment resolver (P4-001, ADR-019).
 *
 * Canonical rule: a segment is active when
 * `start_seconds <= currentTime < end_seconds`.
 *
 * Semantics:
 * - gaps, before the first segment, and after the final segment: no active segment;
 * - exact start is active; exact end is inactive;
 * - zero-length segments (`start == end`) are never active;
 * - overlapping/malformed intervals resolve to the lowest `segment_index`;
 * - ordering depends on `segment_index`, never on input array position.
 *
 * The resolver is pure and has no database, DOM, or player dependency. It accepts
 * arrays or objects (including Eloquent models) exposing `segment_index`,
 * `start_seconds`, and `end_seconds`, and returns the matching input item
 * unchanged or null.
 */
final class ActiveSegmentResolver
{
    /**
     * @param  iterable<object|array<string, mixed>>  $segments
     * @return object|array<string, mixed>|null
     */
    public function resolve(iterable $segments, int|float|string $currentTime): object|array|null
    {
        $time = (float) $currentTime;

        /** @var list<array{index: int, start: float, end: float, item: object|array<string, mixed>}> $ordered */
        $ordered = [];

        foreach ($segments as $segment) {
            /** @var object|array<string, mixed> $segment */
            $ordered[] = [
                'index' => (int) self::value($segment, 'segment_index'),
                'start' => (float) self::value($segment, 'start_seconds'),
                'end' => (float) self::value($segment, 'end_seconds'),
                'item' => $segment,
            ];
        }

        usort($ordered, static fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        foreach ($ordered as $row) {
            if ($row['start'] <= $time && $time < $row['end']) {
                return $row['item'];
            }
        }

        return null;
    }

    /**
     * @param  object|array<string, mixed>  $item
     */
    private static function value(object|array $item, string $key): mixed
    {
        if (is_array($item)) {
            if (! array_key_exists($key, $item)) {
                throw new InvalidArgumentException("Segment is missing [{$key}].");
            }

            return $item[$key];
        }

        if (isset($item->{$key}) || property_exists($item, $key)) {
            return $item->{$key};
        }

        throw new InvalidArgumentException("Segment is missing [{$key}].");
    }
}
