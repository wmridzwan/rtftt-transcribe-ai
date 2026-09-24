<?php

namespace App\Editing;

use InvalidArgumentException;

/**
 * Composes a structural split over a base revision's segments (P6-005).
 *
 * A split replaces one revision segment with two ordered child segments
 * inserted at the original position. Each child receives a **new, opaque**
 * {@see RevisionSegmentIdentity}; the original identity is retired from the new
 * revision. Text and timing are distributed at a strict interior boundary
 * (DECISION-P6-005-SPLIT-BOUNDARY-001), both children inherit the source
 * segment's language marker, and the immutable machine source is never touched.
 *
 * The composer is pure: it validates and builds a fresh list of immutable
 * {@see RevisionSegmentData} value objects for a new revision. Persisting the
 * revision and invalidating translations are the service's responsibility.
 */
final class SplitComposer
{
    /**
     * @param  list<RevisionSegmentData>  $base  the base revision segments
     * @return list<RevisionSegmentData> ordered by position ascending
     *
     * @throws InvalidArgumentException when the segment is unknown, the boundary
     *                                  is not strictly interior, or the split is
     *                                  otherwise degenerate
     */
    public function compose(
        array $base,
        string $segmentKey,
        mixed $boundarySeconds,
        mixed $textOffset,
    ): array {
        $ordered = $this->orderedByPosition($base);

        $source = null;

        foreach ($ordered as $segment) {
            if ($segment->identity->key() === $segmentKey) {
                $source = $segment;

                break;
            }
        }

        if ($source === null) {
            throw new InvalidArgumentException('Unknown revision segment identity ['.$segmentKey.'] for split.');
        }

        $boundary = $this->normalizeSeconds($boundarySeconds);
        $offset = $this->normalizeOffset($textOffset);

        $start = (float) $source->startSeconds;
        $end = (float) $source->endSeconds;

        if (! ($start < $boundary && $boundary < $end)) {
            throw new InvalidArgumentException(sprintf(
                'A split boundary must be strictly interior to the segment timing [%s, %s]; [%s] is not allowed.',
                $this->format($start),
                $this->format($end),
                $this->format($boundary),
            ));
        }

        $length = mb_strlen($source->text);

        if (! ($offset > 0 && $offset < $length)) {
            throw new InvalidArgumentException(sprintf(
                'A split text offset must be strictly interior to the segment text (length %d); [%d] is not allowed.',
                $length,
                $offset,
            ));
        }

        $position = $source->position;
        $firstText = mb_substr($source->text, 0, $offset);
        $secondText = mb_substr($source->text, $offset);

        $result = [];

        foreach ($ordered as $segment) {
            if ($segment->position === $position) {
                $result[] = new RevisionSegmentData(
                    identity: RevisionSegmentIdentity::forStructuralEdit(),
                    position: $position,
                    startSeconds: $start,
                    endSeconds: $boundary,
                    text: $firstText,
                    language: $source->language,
                );

                $result[] = new RevisionSegmentData(
                    identity: RevisionSegmentIdentity::forStructuralEdit(),
                    position: $position + 1,
                    startSeconds: $boundary,
                    endSeconds: $end,
                    text: $secondText,
                    language: $source->language,
                );

                continue;
            }

            $shiftedPosition = $segment->position > $position ? $segment->position + 1 : $segment->position;

            $result[] = new RevisionSegmentData(
                identity: $segment->identity,
                position: $shiftedPosition,
                startSeconds: $segment->startSeconds,
                endSeconds: $segment->endSeconds,
                text: $segment->text,
                language: $segment->language,
                languageProvenance: $segment->languageProvenance,
            );
        }

        return $result;
    }

    /**
     * @param  list<RevisionSegmentData>  $base
     * @return list<RevisionSegmentData>
     */
    private function orderedByPosition(array $base): array
    {
        $ordered = $base;

        usort($ordered, static fn (RevisionSegmentData $a, RevisionSegmentData $b): int => $a->position <=> $b->position);

        return $ordered;
    }

    /**
     * Validate and canonicalize a split boundary to milliseconds, rejecting
     * non-numeric, non-finite, negative, and sub-millisecond values.
     */
    private function normalizeSeconds(mixed $value): float
    {
        if (is_bool($value) || $value === null || is_array($value) || is_object($value)) {
            throw new InvalidArgumentException('Split boundary seconds must be numeric.');
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || ! is_numeric($trimmed)) {
                throw new InvalidArgumentException('Split boundary seconds must be numeric.');
            }

            $value = (float) $trimmed;
        } else {
            $value = (float) $value;
        }

        if (! is_finite($value)) {
            throw new InvalidArgumentException('Split boundary seconds must be finite.');
        }

        if ($value < 0.0) {
            throw new InvalidArgumentException('Split boundary seconds must be non-negative.');
        }

        $scaled = $value * 1000.0;

        if (abs($scaled - round($scaled)) > 1e-6) {
            throw new InvalidArgumentException('Split boundary seconds must have at most millisecond precision.');
        }

        return round($scaled) / 1000.0;
    }

    /**
     * Validate a text offset as a non-negative integer code-point boundary.
     */
    private function normalizeOffset(mixed $value): int
    {
        if (is_bool($value) || $value === null || is_array($value) || is_object($value)) {
            throw new InvalidArgumentException('Split text offset must be a non-negative integer.');
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed === '' || ! ctype_digit($trimmed)) {
                throw new InvalidArgumentException('Split text offset must be a non-negative integer.');
            }

            return (int) $trimmed;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Split text offset must be a non-negative integer.');
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
