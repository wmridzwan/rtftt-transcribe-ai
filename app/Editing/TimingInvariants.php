<?php

namespace App\Editing;

/**
 * Canonical Phase 6 timing policy for the editable revision layer (D6-03).
 *
 * Explicit, non-implicit semantics:
 * - per segment: finite, non-negative, `start <= end`;
 * - zero-length (`start == end`) is legal but is never active for playback;
 * - overlaps are legal; resolution uses the lowest `position` (mirroring the
 *   frozen Phase 4 lowest-`segment_index` rule);
 * - ordering is by `position`, never by timestamp;
 * - cross-segment timestamp monotonicity is NOT required.
 */
final class TimingInvariants
{
    /**
     * @throws \InvalidArgumentException
     */
    public static function assertValidTiming(int|float $startSeconds, int|float $endSeconds): void
    {
        if (! is_finite((float) $startSeconds)) {
            throw new \InvalidArgumentException('Start seconds must be a finite number.');
        }

        if (! is_finite((float) $endSeconds)) {
            throw new \InvalidArgumentException('End seconds must be a finite number.');
        }

        if ($startSeconds < 0) {
            throw new \InvalidArgumentException('Start seconds must be non-negative.');
        }

        if ($endSeconds < 0) {
            throw new \InvalidArgumentException('End seconds must be non-negative.');
        }

        if ($endSeconds < $startSeconds) {
            throw new \InvalidArgumentException('End seconds must be greater than or equal to start seconds.');
        }
    }

    public static function isValidTiming(int|float $startSeconds, int|float $endSeconds): bool
    {
        try {
            self::assertValidTiming($startSeconds, $endSeconds);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    public static function isZeroLength(int|float $startSeconds, int|float $endSeconds): bool
    {
        return (float) $startSeconds === (float) $endSeconds;
    }

    /**
     * Half-open interval overlap `[startA, endA) ∩ [startB, endB)`. Zero-length
     * intervals never overlap.
     */
    public static function overlaps(
        int|float $startA,
        int|float $endA,
        int|float $startB,
        int|float $endB,
    ): bool {
        return (float) $startA < (float) $endB && (float) $startB < (float) $endA;
    }

    /**
     * Validate a revision's ordered segments: unique identities, unique and
     * contiguous positions starting at 0. Overlap and zero-length timings are
     * accepted by design.
     *
     * @param  list<RevisionSegmentData>  $segments
     *
     * @throws \InvalidArgumentException
     */
    public static function assertValidSequence(array $segments): void
    {
        $identities = [];
        $positions = [];

        foreach ($segments as $segment) {
            $key = $segment->identity->key();

            if (isset($identities[$key])) {
                throw new \InvalidArgumentException("Duplicate revision segment identity [{$key}].");
            }

            $identities[$key] = true;

            if (isset($positions[$segment->position])) {
                throw new \InvalidArgumentException("Duplicate revision segment position [{$segment->position}].");
            }

            $positions[$segment->position] = true;
        }

        $count = count($segments);

        for ($expected = 0; $expected < $count; $expected++) {
            if (! isset($positions[$expected])) {
                throw new \InvalidArgumentException('Revision segment positions must be contiguous from 0.');
            }
        }
    }

    /**
     * Resolve which revision segment is active at `$time` using the canonical
     * half-open rule and lowest-`position` overlap resolution. Returns the
     * matching segment or null (gap / zero-length only / empty).
     *
     * @param  list<RevisionSegmentData>  $segments
     */
    public static function activeAt(array $segments, int|float $time): ?RevisionSegmentData
    {
        $time = (float) $time;
        /** @var list<RevisionSegmentData> $ordered */
        $ordered = $segments;

        usort($ordered, static fn (RevisionSegmentData $a, RevisionSegmentData $b): int => $a->position <=> $b->position);

        foreach ($ordered as $segment) {
            if ((float) $segment->startSeconds <= $time && $time < (float) $segment->endSeconds) {
                return $segment;
            }
        }

        return null;
    }
}
