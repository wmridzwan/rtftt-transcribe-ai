<?php

namespace App\Translation;

use InvalidArgumentException;

/**
 * Enforces valid translation lifecycle transitions (ADR-022, D5-05).
 *
 * Canonical lifecycle:
 *   pending → queued → translating → completed
 *                                   → failed
 *   failed → queued (explicit manual retry only)
 *
 * Completed is terminal and protected. Failed is terminal for orchestration
 * and only leaves through an authorized manual retry action.
 */
class TranslationLifecycle
{
    /**
     * @var array<string, list<string>>
     */
    private const VALID_TRANSITIONS = [
        TranslationStatus::Pending->value => [
            TranslationStatus::Queued->value,
        ],
        TranslationStatus::Queued->value => [
            TranslationStatus::Translating->value,
            TranslationStatus::Failed->value,
        ],
        TranslationStatus::Translating->value => [
            TranslationStatus::Completed->value,
            TranslationStatus::Failed->value,
        ],
        TranslationStatus::Completed->value => [],
        // ADR-022 / ADR-018 precedent: the only exit from terminal `failed` is
        // an explicit, authorized manual retry.
        TranslationStatus::Failed->value => [
            TranslationStatus::Queued->value,
        ],
    ];

    public static function canTransition(
        TranslationStatus $from,
        TranslationStatus $to,
    ): bool {
        return in_array($to->value, self::VALID_TRANSITIONS[$from->value], true);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertValidTransition(
        TranslationStatus $from,
        TranslationStatus $to,
    ): void {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException(
                "Invalid translation lifecycle transition: {$from->value} → {$to->value}."
            );
        }
    }
}
