<?php

namespace App\TranscriptExperience;

/**
 * Resolved Phase 4 transcript workspace availability for one transcription
 * (P4-001, ADR-019).
 *
 * Flags are state only; no UI, streaming, or persistence behavior is implied.
 * Actual retry eligibility remains owned by the Phase 3 `TranscriptionRetry`
 * action; `retry` here indicates that the failed-state retry surface applies.
 */
final readonly class WorkspaceState
{
    public function __construct(
        public bool $mediaPlayer,
        public bool $transcriptInteraction,
        public bool $search,
        public bool $copy,
        public bool $export,
        public bool $retry,
        public bool $noSpeech,
    ) {}
}
