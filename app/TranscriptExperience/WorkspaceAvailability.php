<?php

namespace App\TranscriptExperience;

use App\Enums\TranscriptionStatus;
use App\Models\Transcription;

/**
 * Phase 4 completed-only workspace availability helper (P4-001, ADR-019).
 *
 * Consumes the frozen Phase 3 lifecycle and persisted model; it does not change
 * lifecycle semantics, media storage, or transcript content.
 *
 * Matrix:
 * - completed: media when a physical file exists; transcript interaction,
 *   search, and copy when segments exist; export always; no-speech remains valid;
 * - queued / preparing / transcribing: no transcript interaction, search, copy,
 *   or export;
 * - failed: no transcript interaction, search, copy, or export; Phase 3 retry
 *   surface applies;
 * - draft / cancelled: no transcript interaction, search, copy, or export.
 */
final class WorkspaceAvailability
{
    public static function for(Transcription $transcription): WorkspaceState
    {
        $completed = $transcription->status === TranscriptionStatus::Completed;
        $hasSegments = $transcription->segments()->exists();
        $interaction = $completed && $hasSegments;

        return new WorkspaceState(
            mediaPlayer: $transcription->mediaFile?->hasPhysicalFile() ?? false,
            transcriptInteraction: $interaction,
            search: $interaction,
            copy: $interaction,
            export: $completed,
            retry: $transcription->status === TranscriptionStatus::Failed,
            noSpeech: self::detectNoSpeech($transcription, $hasSegments),
        );
    }

    /**
     * Canonical no-speech / empty completed transcript:
     * `status = completed`, `speech_detected = false`, empty `full_text`, no segments.
     */
    public static function isNoSpeech(Transcription $transcription): bool
    {
        return self::detectNoSpeech($transcription, $transcription->segments()->exists());
    }

    private static function detectNoSpeech(Transcription $transcription, bool $hasSegments): bool
    {
        return $transcription->status === TranscriptionStatus::Completed
            && $transcription->speech_detected === false
            && ($transcription->full_text === null || $transcription->full_text === '')
            && ! $hasSegments;
    }
}
