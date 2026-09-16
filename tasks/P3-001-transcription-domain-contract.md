# P3-001 — Transcription Domain Contract

## Status

BACKLOG

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 1

## Objective

Establish the provider-neutral transcription domain contract for the Laravel application.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `Phase 3 — Real Transcription Engine Technical Specification` (approved)
- `architecture.md`
- `DECISIONS.md` (ADR-002, ADR-013, ADR-017)
- Existing models: `Transcription`, `TranscriptionSegment`, `ProcessingJob`
- Existing enums: `TranscriptionStatus`, `ProcessingStatus`, `ProcessingStage`

## Scope

Define and reconcile:

- `TranscriptionOptions` (requested_language, nullable)
- `TranscriptionMedia` (opaque storage-backed reference)
- `NormalizedTranscript` (text, language, duration_seconds, speech_detected, segments)
- `TranscriptSegmentData` (segment_index, start_seconds, end_seconds, text, language)
- `LanguageIdentifier` (BCP 47-compatible; `ms`, `en`, `zh`, `ta`, `und`)
- No-speech semantics (speech_detected=false, text="", language=und, segments=[])
- Retry vs retranscription distinction
- Logical transcription identity and attempt identity
- Existing status lifecycle reuse (draft → queued → preparing → transcribing → completed/failed/cancelled)

## Out of Scope

- No Python implementation
- No real HTTP worker call
- No Redis execution
- No faster-whisper integration
- No schema migrations (reuse existing where possible; minimum additive migration if required)

## Dependencies

- Phase 2 COMPLETE_WITH_DEFERRED_DEBT (DONE)

## Acceptance Criteria

1. Provider-neutral domain contract exists in PHP.
2. Existing lifecycle vocabulary (TranscriptionStatus, ProcessingStatus) is reused.
3. Requested language is nullable; null means auto-detect.
4. Explicit requested language remains a hint, not a guarantee.
5. Transcript detected_language means dominant language.
6. Segment language exists; one language value per segment.
7. `und` is a valid language value.
8. Mixed-language segments are supported.
9. Timestamp invariants defined (start >= 0, end >= start).
10. No-speech is distinct from failure.
11. Ownership invariants defined (Transcription belongs to MediaFile belongs to User).
12. Retry and retranscription are distinct concepts.
13. Logical and attempt identities defined.
14. No faster-whisper type leaks into PHP domain.
15. No translation behavior introduced.
16. All tests pass; Pint clean; PHPStan 0 errors.

## Required Tests

- Valid lifecycle transitions
- Invalid lifecycle transitions
- Auto language (null requested)
- Explicit language hint
- BCP 47 values (ms, en, zh, ta, und)
- Mixed segments (different languages)
- Timestamps
- No-speech result
- Retry identity preservation
- Retranscription identity independence
- Ownership isolation

## Implementation Notes

TBD by implementation owner.

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
