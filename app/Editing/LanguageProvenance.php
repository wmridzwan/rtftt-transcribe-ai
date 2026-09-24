<?php

namespace App\Editing;

use App\Transcription\LanguageIdentifier;
use InvalidArgumentException;

/**
 * Explicit, ordered mixed-language provenance of a structurally created
 * revision segment (P6-005; DECISION-P6-005-LANGUAGE-PROVENANCE-001).
 *
 * A merge of contributors with differing language markers sets the merged
 * segment's language to `und` and records the original contributor language
 * markers here, in contributor (position) order. It is never used to silently
 * pick a canonical language; the `language` value is always the frozen BCP 47
 * vocabulary value.
 *
 * Nullable when unnecessary: materialization, text/timing edits, splits, and
 * same-language merges carry no provenance.
 */
final readonly class LanguageProvenance
{
    /**
     * @param  list<LanguageIdentifier>  $contributors  ordered contributor markers
     *
     * @throws InvalidArgumentException when empty
     */
    private function __construct(public array $contributors)
    {
        if ($contributors === []) {
            throw new InvalidArgumentException('Language provenance must contain at least one contributor marker.');
        }
    }

    /**
     * Build provenance from contributor markers in contributor order.
     *
     * @param  list<LanguageIdentifier>  $contributors
     */
    public static function fromIdentifiers(array $contributors): self
    {
        return new self($contributors);
    }

    /**
     * Rehydrate provenance from its persisted (JSON array of BCP 47 markers).
     *
     * @param  list<string>  $markers
     *
     * @throws InvalidArgumentException on an unknown marker
     */
    public static function fromArray(array $markers): self
    {
        $contributors = [];

        foreach ($markers as $marker) {
            $identifier = LanguageIdentifier::tryFrom(strtolower($marker));

            if ($identifier === null) {
                throw new InvalidArgumentException('Unknown language provenance marker ['.$marker.'].');
            }

            $contributors[] = $identifier;
        }

        return new self($contributors);
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (LanguageIdentifier $identifier): string => $identifier->value,
            $this->contributors,
        );
    }

    public function contributorCount(): int
    {
        return count($this->contributors);
    }

    /**
     * True when the recorded contributors do not all share one language marker.
     */
    public function isMixed(): bool
    {
        $values = $this->toArray();

        return count(array_unique($values)) > 1;
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
