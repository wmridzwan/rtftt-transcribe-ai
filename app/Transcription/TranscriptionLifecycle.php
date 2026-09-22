<?php

namespace App\Transcription;

use App\Enums\TranscriptionStatus;
use InvalidArgumentException;

/**
 * Enforces valid transcription lifecycle transitions.
 *
 * Canonical lifecycle:
 *   draft → queued → preparing → transcribing → completed
 *                                               → failed
 *                                               → cancelled
 */
class TranscriptionLifecycle
{
    /**
     * Define valid transitions for the transcription lifecycle.
     *
     * @var array<string, list<string>>
     */
    private const VALID_TRANSITIONS = [
        TranscriptionStatus::Draft->value => [
            TranscriptionStatus::Queued->value,
            TranscriptionStatus::Cancelled->value,
        ],
        TranscriptionStatus::Queued->value => [
            TranscriptionStatus::Preparing->value,
            TranscriptionStatus::Cancelled->value,
            TranscriptionStatus::Failed->value,
        ],
        TranscriptionStatus::Preparing->value => [
            TranscriptionStatus::Transcribing->value,
            TranscriptionStatus::Failed->value,
            TranscriptionStatus::Cancelled->value,
        ],
        TranscriptionStatus::Transcribing->value => [
            TranscriptionStatus::Completed->value,
            TranscriptionStatus::Failed->value,
            TranscriptionStatus::Cancelled->value,
        ],
        TranscriptionStatus::Completed->value => [],
        // P3-007 / ADR-018: the only way out of the terminal `failed` state is
        // an explicit, authorized retry action (App\Actions\TranscriptionRetry).
        // A failed processing attempt itself never returns to `queued`.
        TranscriptionStatus::Failed->value => [
            TranscriptionStatus::Queued->value,
        ],
        TranscriptionStatus::Cancelled->value => [],
    ];

    /**
     * Check if a status transition is valid.
     */
    public static function canTransition(
        TranscriptionStatus $from,
        TranscriptionStatus $to,
    ): bool {
        return in_array($to->value, self::VALID_TRANSITIONS[$from->value], true);
    }

    /**
     * Assert that a status transition is valid.
     *
     * @throws InvalidArgumentException if transition is invalid
     */
    public static function assertValidTransition(
        TranscriptionStatus $from,
        TranscriptionStatus $to,
    ): void {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException(
                "Invalid lifecycle transition: {$from->value} → {$to->value}."
            );
        }
    }
}
