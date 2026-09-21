<?php

namespace App\Translation;

/**
 * Supported Phase 5 translation target languages (ADR-022, D5-02).
 *
 * Targets mirror the source vocabulary minus `und`; `und` is a valid source
 * marker but never a valid target.
 */
enum TranslationTarget: string
{
    case Malay = 'ms';
    case English = 'en';
    case Chinese = 'zh';
    case Tamil = 'ta';

    /**
     * Resolve a BCP 47 tag to a supported target.
     *
     * Returns null for unsupported tags and for `und` (undefined is not a
     * translatable target). Prefix matching mirrors LanguageIdentifier.
     */
    public static function fromBcp47(string $tag): ?self
    {
        $lower = strtolower(trim($tag));

        if ($lower === '') {
            return null;
        }

        $prefix = explode('-', $lower)[0];

        foreach (self::cases() as $case) {
            if ($case->value === $prefix) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Human-readable name for selectors and headings.
     */
    public function label(): string
    {
        return match ($this) {
            self::Malay => 'Malay (Bahasa Melayu)',
            self::English => 'English',
            self::Chinese => 'Chinese (中文)',
            self::Tamil => 'Tamil (தமிழ்)',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }
}
