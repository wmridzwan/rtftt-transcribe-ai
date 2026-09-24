<?php

namespace App\Editing;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

/**
 * Stable, opaque identity of a segment within one revision.
 *
 * This is the navigation/selection identity for an edited transcript (P6-006
 * handoff): after split/merge the machine `segment_index` is provenance only,
 * and navigation follows this identity instead. An identity is unique within a
 * revision and is never reused once retired.
 */
final readonly class RevisionSegmentIdentity implements Stringable
{
    private function __construct(private string $key)
    {
        if (trim($key) === '') {
            throw new InvalidArgumentException('Revision segment identity must be non-empty.');
        }
    }

    public static function fromString(string $key): self
    {
        return new self($key);
    }

    /**
     * Deterministic identity used when materializing the initial revision from
     * the immutable machine source, preserving machine provenance.
     */
    public static function forMachineSegment(int $segmentIndex): self
    {
        if ($segmentIndex < 0) {
            throw new InvalidArgumentException('Machine segment index must be non-negative.');
        }

        return new self('machine:'.$segmentIndex);
    }

    /**
     * A new, opaque identity for a structurally created revision segment
     * (split child or merged output). It is deliberately **not** derived from
     * machine `segment_index` provenance; machine-style `machine:<index>`
     * identities are never manufactured for structural edits.
     */
    public static function forStructuralEdit(): self
    {
        return new self('struct:'.Str::uuid());
    }

    public function key(): string
    {
        return $this->key;
    }

    public function equals(self $other): bool
    {
        return $this->key === $other->key;
    }

    public function __toString(): string
    {
        return $this->key;
    }
}
