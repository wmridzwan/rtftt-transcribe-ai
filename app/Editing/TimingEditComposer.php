<?php

namespace App\Editing;

use InvalidArgumentException;

/**
 * Composes a timing-only edit over a base revision's segments (P6-004).
 *
 * The composer is deliberately narrow: it replaces each segment's
 * `startSeconds` / `endSeconds` only. Each segment's stable
 * {@see RevisionSegmentIdentity}, contiguous `position`, `text`, and carried
 * `language` are preserved verbatim. Structural or textual changes are not
 * representable here; those are owned by P6-003 (text) and P6-005 (structure).
 *
 * Timing is validated per segment under the frozen D6-03 invariants (finite,
 * non-negative, `start <= end`, millisecond precision with no silent rounding).
 * The cross-segment policy is intentionally permissive: overlaps, nested
 * overlaps, equal starts/ends, zero-length, out-of-time-order positions, and
 * extending a segment past a neighbor are all legal, so no monotonicity rule is
 * introduced here or in {@see TimingInvariants}.
 *
 * The resulting sequence is a fresh list of immutable
 * {@see RevisionSegmentData} value objects, so the same per-segment /
 * cross-segment validation applies as for any revision.
 */
final class TimingEditComposer
{
    /**
     * @param  list<RevisionSegmentData>  $base  the base revision segments
     * @param  array<int, mixed>  $timingsByPosition  replacement timing keyed by `position`,
     *                                                each value an array with `start` and `end`
     * @return list<RevisionSegmentData> ordered by position ascending
     *
     * @throws InvalidArgumentException when the submission does not supply
     *                                  exactly one valid timing per base segment
     */
    public function compose(array $base, array $timingsByPosition): array
    {
        $byPosition = [];

        foreach ($base as $segment) {
            $byPosition[$segment->position] = $segment;
        }

        ksort($byPosition);

        if (count($timingsByPosition) !== count($byPosition)) {
            throw new InvalidArgumentException(sprintf(
                'A timing edit must supply exactly one timing per segment: base has %d, submitted %d.',
                count($byPosition),
                count($timingsByPosition),
            ));
        }

        $composed = [];

        foreach ($byPosition as $position => $segment) {
            if (! array_key_exists($position, $timingsByPosition)) {
                throw new InvalidArgumentException(
                    'A timing edit is missing timing for segment position ['.$position.'].',
                );
            }

            $timing = $timingsByPosition[$position];

            if (! is_array($timing)) {
                throw new InvalidArgumentException(
                    'Segment position ['.$position.'] timing must be an array with start and end.',
                );
            }

            if (! array_key_exists('start', $timing) || ! array_key_exists('end', $timing)) {
                throw new InvalidArgumentException(
                    'Segment position ['.$position.'] timing must supply both start and end.',
                );
            }

            $start = $this->normalizeSeconds($timing['start'], 'start', $position);
            $end = $this->normalizeSeconds($timing['end'], 'end', $position);

            if ($end < $start) {
                throw new InvalidArgumentException(
                    'Segment position ['.$position.'] end time must be greater than or equal to start time.',
                );
            }

            $composed[] = new RevisionSegmentData(
                identity: $segment->identity,
                position: $position,
                startSeconds: $start,
                endSeconds: $end,
                text: $segment->text,
                language: $segment->language,
            );
        }

        return $composed;
    }

    /**
     * Validate and canonicalize a submitted timing value to milliseconds.
     *
     * Rejects non-numeric values, NaN/±INF, negative values, and any value
     * carrying more than millisecond precision. Values are canonicalized by
     * rounding to the nearest millisecond **only** after the precision check, so
     * no silent rounding of an out-of-contract value can occur.
     *
     * @throws InvalidArgumentException
     */
    private function normalizeSeconds(mixed $value, string $field, int $position): float
    {
        if (is_bool($value) || $value === null || is_array($value) || is_object($value)) {
            throw new InvalidArgumentException(
                'Segment position ['.$position.'] '.$field.' time must be numeric.',
            );
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || ! is_numeric($trimmed)) {
                throw new InvalidArgumentException(
                    'Segment position ['.$position.'] '.$field.' time must be numeric.',
                );
            }

            $value = (float) $trimmed;
        } elseif (is_int($value) || is_float($value)) {
            $value = (float) $value;
        } else {
            throw new InvalidArgumentException(
                'Segment position ['.$position.'] '.$field.' time must be numeric.',
            );
        }

        if (! is_finite($value)) {
            throw new InvalidArgumentException(
                'Segment position ['.$position.'] '.$field.' time must be finite.',
            );
        }

        if ($value < 0.0) {
            throw new InvalidArgumentException(
                'Segment position ['.$position.'] '.$field.' time must be non-negative.',
            );
        }

        $scaled = $value * 1000.0;

        if (abs($scaled - round($scaled)) > 1e-6) {
            throw new InvalidArgumentException(
                'Segment position ['.$position.'] '.$field.' time must have at most millisecond precision.',
            );
        }

        return round($scaled) / 1000.0;
    }
}
