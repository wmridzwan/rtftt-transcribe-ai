<?php

namespace App\Translation;

/**
 * Canonical Phase 5 translation lifecycle vocabulary (ADR-022, D5-05).
 *
 * Translation is derived data with its own lifecycle; it must never be
 * written onto the transcription state machine.
 */
enum TranslationStatus: string
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Translating = 'translating';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * A terminal status never transitions again through normal orchestration.
     * `Failed` remains manually retryable through an explicit action, matching
     * the Phase 3 ADR-018 model.
     */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
