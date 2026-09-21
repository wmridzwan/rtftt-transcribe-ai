<?php

namespace App\Translation;

use App\Transcription\LanguageIdentifier;
use InvalidArgumentException;

/**
 * Alignment policy for segment-aligned translation (ADR-022, D5-01).
 *
 * Translation preserves the source segment index, timestamps, and
 * source-language marker. Re-segmentation is not performed in Phase 5.
 */
final class TranslationAlignment
{
    /**
     * A source segment is passed through unchanged only when its language is
     * known and already equals the requested target. `und` is never treated as
     * passthrough because the source is not known to be the target language.
     */
    public static function isPassthrough(
        LanguageIdentifier $source,
        TranslationTarget $target,
    ): bool {
        return $source !== LanguageIdentifier::Undetermined
            && $source->value === $target->value;
    }

    /**
     * Build a passthrough translated segment from a source segment.
     *
     * @throws InvalidArgumentException when the segment is not a passthrough
     */
    public static function passthrough(
        TranslationSegmentData $source,
        TranslationTarget $target,
    ): TranslationSegmentData {
        if (! self::isPassthrough($source->sourceLanguage, $target)) {
            throw new InvalidArgumentException(
                'A passthrough segment requires the source language to equal the target language.'
            );
        }

        return new TranslationSegmentData(
            segmentIndex: $source->segmentIndex,
            startSeconds: $source->startSeconds,
            endSeconds: $source->endSeconds,
            text: $source->text,
            sourceLanguage: $source->sourceLanguage,
        );
    }
}
