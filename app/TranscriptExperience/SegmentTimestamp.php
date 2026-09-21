<?php

namespace App\TranscriptExperience;

use InvalidArgumentException;

/**
 * Canonical Phase 4 transcript timestamp primitive (P4-001, ADR-019).
 *
 * Persisted segment timestamps are `decimal(12,3)` seconds (at most millisecond
 * precision) and are the source of truth. The formatter also defensively accepts
 * higher-precision numeric input; such input is normalized by rounding to the
 * nearest millisecond with carry and is not claimed to originate from persisted
 * Phase 3 segment rows.
 *
 * Invalid input (negative, NaN, infinite, or non-numeric) is rejected with an
 * InvalidArgumentException. No product-visible fallback text is produced.
 */
final readonly class SegmentTimestamp
{
    private function __construct(
        private int $totalMilliseconds,
        private string $seek,
    ) {}

    /**
     * @throws InvalidArgumentException when the value is non-numeric, negative, or non-finite
     */
    public static function fromSeconds(int|float|string $seconds): self
    {
        $value = self::toFiniteNonNegativeFloat($seconds);

        return new self(
            totalMilliseconds: (int) round($value * 1000),
            seek: self::seekString($seconds, $value),
        );
    }

    /**
     * Human-readable display: `H:MM:SS.mmm` when hours > 0, otherwise `MM:SS.mmm`.
     */
    public function display(): string
    {
        [$hours, $minutes, $seconds, $milliseconds] = $this->parts();

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d.%03d', $hours, $minutes, $seconds, $milliseconds);
        }

        return sprintf('%02d:%02d.%03d', $minutes, $seconds, $milliseconds);
    }

    /**
     * Exact persisted numeric seconds for media seeking; never re-rounded.
     */
    public function seek(): string
    {
        return $this->seek;
    }

    /**
     * SRT timestamp: `HH:MM:SS,mmm`.
     */
    public function srt(): string
    {
        [$hours, $minutes, $seconds, $milliseconds] = $this->parts();

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $seconds, $milliseconds);
    }

    /**
     * WebVTT timestamp: `HH:MM:SS.mmm`.
     */
    public function vtt(): string
    {
        [$hours, $minutes, $seconds, $milliseconds] = $this->parts();

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $seconds, $milliseconds);
    }

    public function totalMilliseconds(): int
    {
        return $this->totalMilliseconds;
    }

    /**
     * @return array{int, int, int, int}
     */
    private function parts(): array
    {
        $milliseconds = $this->totalMilliseconds;

        return [
            intdiv($milliseconds, 3_600_000),
            intdiv($milliseconds % 3_600_000, 60_000),
            intdiv($milliseconds % 60_000, 1_000),
            $milliseconds % 1_000,
        ];
    }

    private static function toFiniteNonNegativeFloat(int|float|string $seconds): float
    {
        if (is_string($seconds)) {
            $trimmed = trim($seconds);

            if ($trimmed === '' || ! is_numeric($trimmed)) {
                throw new InvalidArgumentException('Timestamp must be numeric.');
            }

            $value = (float) $trimmed;
        } else {
            $value = (float) $seconds;
        }

        if (! is_finite($value)) {
            throw new InvalidArgumentException('Timestamp must be finite.');
        }

        if ($value < 0.0) {
            throw new InvalidArgumentException('Timestamp must be non-negative.');
        }

        return $value;
    }

    private static function seekString(int|float|string $seconds, float $value): string
    {
        if (is_string($seconds)) {
            return trim($seconds);
        }

        if (is_int($seconds)) {
            return (string) $seconds;
        }

        // Shortest round-trip representation; avoids locale/precision drift.
        $encoded = json_encode($value);

        return is_string($encoded) ? $encoded : (string) $value;
    }
}
