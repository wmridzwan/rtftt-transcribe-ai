<?php

namespace Tests\Support;

use App\Editing\RevisionSegmentData;
use App\Editing\RevisionSegmentIdentity;
use App\Transcription\LanguageIdentifier;

/**
 * Shared Phase 6 editing-domain fixtures (P6-001).
 */
final class EditingFixtures
{
    public static function segment(
        int $position,
        float $start,
        float $end,
        string $text = 'segment text',
        ?LanguageIdentifier $language = null,
        ?string $identity = null,
    ): RevisionSegmentData {
        return new RevisionSegmentData(
            identity: RevisionSegmentIdentity::fromString($identity ?? 'seg-'.$position),
            position: $position,
            startSeconds: $start,
            endSeconds: $end,
            text: $text,
            language: $language ?? LanguageIdentifier::English,
        );
    }

    /**
     * A simple valid sequence: two contiguous, non-overlapping segments.
     *
     * @return list<RevisionSegmentData>
     */
    public static function sequence(): array
    {
        return [
            self::segment(0, 0.0, 5.0, 'first', LanguageIdentifier::Malay),
            self::segment(1, 5.0, 10.0, 'second', LanguageIdentifier::English),
        ];
    }
}
