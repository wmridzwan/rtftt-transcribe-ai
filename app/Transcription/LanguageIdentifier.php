<?php

namespace App\Transcription;

/**
 * BCP 47-compatible language identifier for RTFTT transcription.
 *
 * Required vocabulary: ms, en, zh, ta, und.
 */
enum LanguageIdentifier: string
{
    case Malay = 'ms';
    case English = 'en';
    case Chinese = 'zh';
    case Tamil = 'ta';
    case Undetermined = 'und';

    /**
     * Resolve a BCP 47 tag to a known LanguageIdentifier.
     *
     * Returns Undetermined for unrecognized tags.
     */
    public static function fromBcp47(string $tag): self
    {
        $lower = strtolower($tag);

        foreach (self::cases() as $case) {
            if ($case->value === $lower) {
                return $case;
            }
        }

        // Attempt prefix match (e.g., 'en-US' -> 'en')
        $prefix = explode('-', $lower)[0];

        foreach (self::cases() as $case) {
            if ($case->value === $prefix) {
                return $case;
            }
        }

        return self::Undetermined;
    }
}
