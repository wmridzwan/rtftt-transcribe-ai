<?php

namespace App\Editing;

use App\Transcription\LanguageIdentifier;
use InvalidArgumentException;

/**
 * A single ordered segment of a revision (D6-01 / D6-03).
 *
 * A revision segment carries its stable identity (navigation identity), its
 * contiguous `position`, validated timing, text, and carried language. Timing
 * is validated per segment only; overlaps and zero-length timings are legal at
 * the sequence level (see {@see TimingInvariants}).
 */
final readonly class RevisionSegmentData
{
    public function __construct(
        public RevisionSegmentIdentity $identity,
        public int $position,
        public float $startSeconds,
        public float $endSeconds,
        public string $text,
        public LanguageIdentifier $language,
    ) {
        if ($position < 0) {
            throw new InvalidArgumentException('Revision segment position must be non-negative.');
        }

        TimingInvariants::assertValidTiming($startSeconds, $endSeconds);
    }

    public function isZeroLength(): bool
    {
        return TimingInvariants::isZeroLength($this->startSeconds, $this->endSeconds);
    }

    public function hasSameTiming(self $other): bool
    {
        return (float) $this->startSeconds === (float) $other->startSeconds
            && (float) $this->endSeconds === (float) $other->endSeconds;
    }

    public function hasSameText(self $other): bool
    {
        return $this->text === $other->text;
    }
}
