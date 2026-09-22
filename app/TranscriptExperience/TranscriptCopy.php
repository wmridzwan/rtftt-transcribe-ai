<?php

namespace App\TranscriptExperience;

use App\Models\TranscriptionSegment;

/**
 * Plain-text assembly for transcript copy actions (P4-004, ADR-019).
 *
 * Copy output is plain text only and preserves persisted text verbatim. The
 * full transcript is the ordered persisted segments joined by a single newline
 * with no timestamps. This helper performs no highlighting, normalization, or
 * translation; client-side search/highlight behavior is a separate concern.
 */
final class TranscriptCopy
{
    /**
     * @param  iterable<int, TranscriptionSegment>  $segments
     */
    public static function fullText(iterable $segments): string
    {
        $lines = [];

        foreach ($segments as $segment) {
            $lines[] = (string) $segment->text;
        }

        return implode("\n", $lines);
    }

    public static function segmentText(TranscriptionSegment $segment): string
    {
        return (string) $segment->text;
    }
}
