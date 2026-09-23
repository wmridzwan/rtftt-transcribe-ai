<?php

namespace App\Editing;

use InvalidArgumentException;

/**
 * Composes a text-only edit over a base revision's segments (P6-003).
 *
 * The composer is deliberately narrow: it replaces segment `text` only. Each
 * segment's stable {@see RevisionSegmentIdentity}, contiguous `position`,
 * timing (`startSeconds` / `endSeconds`), and carried language are preserved
 * verbatim. Structural or timing changes are not representable here; those are
 * owned by P6-004/P6-005.
 *
 * The resulting sequence is a fresh list of immutable
 * {@see RevisionSegmentData} value objects, so the same per-segment /
 * cross-segment validation applies as for any revision.
 */
final class TextEditComposer
{
    /**
     * @param  list<RevisionSegmentData>  $base  the base revision segments
     * @param  array<int, mixed>  $textsByPosition  replacement text keyed by `position`
     * @return list<RevisionSegmentData> ordered by position ascending
     *
     * @throws InvalidArgumentException when the submission does not supply
     *                                  exactly one text per base segment
     */
    public function compose(array $base, array $textsByPosition): array
    {
        $byPosition = [];

        foreach ($base as $segment) {
            $byPosition[$segment->position] = $segment;
        }

        ksort($byPosition);

        if (count($textsByPosition) !== count($byPosition)) {
            throw new InvalidArgumentException(sprintf(
                'A text edit must supply exactly one text per segment: base has %d, submitted %d.',
                count($byPosition),
                count($textsByPosition),
            ));
        }

        $composed = [];

        foreach ($byPosition as $position => $segment) {
            if (! array_key_exists($position, $textsByPosition)) {
                throw new InvalidArgumentException(
                    'A text edit is missing text for segment position ['.$position.'].',
                );
            }

            $text = $textsByPosition[$position];

            if (! is_string($text)) {
                throw new InvalidArgumentException(
                    'Segment position ['.$position.'] text must be a string.',
                );
            }

            $composed[] = new RevisionSegmentData(
                identity: $segment->identity,
                position: $position,
                startSeconds: $segment->startSeconds,
                endSeconds: $segment->endSeconds,
                text: $text,
                language: $segment->language,
            );
        }

        return $composed;
    }
}
