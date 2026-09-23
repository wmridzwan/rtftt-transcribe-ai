<?php

namespace App\Editing;

use App\Transcription\LanguageIdentifier;
use InvalidArgumentException;

/**
 * Immutable snapshot of one machine transcript segment at materialization time.
 *
 * This is a pure, provider-neutral view of a completed
 * `App\Models\TranscriptionSegment` row: the domain contract never reads
 * Eloquent directly. Mapping the model to this snapshot is P6-002's
 * responsibility.
 */
final readonly class MachineSegmentSnapshot
{
    public function __construct(
        public int $segmentIndex,
        public float $startSeconds,
        public float $endSeconds,
        public string $text,
        public LanguageIdentifier $language,
    ) {
        if ($segmentIndex < 0) {
            throw new InvalidArgumentException('Machine segment index must be non-negative.');
        }

        TimingInvariants::assertValidTiming($startSeconds, $endSeconds);
    }
}
