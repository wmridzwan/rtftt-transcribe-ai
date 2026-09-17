# P3-001 — Transcription Domain Contract

## Status

IN_PROGRESS

## Ownership

Implementation Owner: OpenCode (per AGENTS.md agent model)
Reviewer: Claude Code

## Review Verdict History

Cycle 1 = CHANGES_REQUESTED (`reviews/PHASE3-BATCH1-independent-review.md`)

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 1

## Objective

Establish the provider-neutral transcription domain contract for the Laravel application.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
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

### Files Created

- `app/Transcription/LanguageIdentifier.php` — BCP 47 enum (ms, en, zh, ta, und)
- `app/Transcription/TranscriptSegmentData.php` — immutable segment DTO
- `app/Transcription/NormalizedTranscript.php` — immutable result DTO
- `app/Transcription/TranscriptionOptions.php` — nullable requested language
- `app/Transcription/TranscriptionMedia.php` — opaque storage-backed reference
- `app/Transcription/TranscriptionFailure.php` — failure taxonomy enum
- `app/Transcription/TranscriptionException.php` — provider-neutral exception
- `app/Transcription/TranscriptionProvider.php` — provider-neutral interface

### Tests Created

- `tests/Unit/Transcription/LanguageIdentifierTest.php` — 4 tests
- `tests/Unit/Transcription/TranscriptSegmentDataTest.php` — 6 tests
- `tests/Unit/Transcription/NormalizedTranscriptTest.php` — 6 tests
- `tests/Unit/Transcription/TranscriptionOptionsTest.php` — 3 tests
- `tests/Unit/Transcription/TranscriptionMediaTest.php` — 7 tests
- `tests/Unit/Transcription/TranscriptionExceptionTest.php` — 5 tests

### Quality Results

- 31 unit tests passed, 90 assertions
- Pint clean (fixed minor formatting)
- PHPStan 0 errors

## Review

Review File: reviews/PHASE3-BATCH1-independent-review.md
Review Status: CHANGES_REQUESTED (batch cycle 1)

Unresolved MEDIUM findings for this task: M2 (P3-001's own Required Tests
for lifecycle transitions, retry identity preservation, retranscription
identity independence, and ownership isolation were never implemented or
tested; AC 11-13 are asserted only in prose), M1 (transcription/attempt
identity fields exist but cannot be populated with real values by any
current caller). AC4 (explicit language hint) cannot be verified as working
because of a P3-003 finding (H2) that discards it entirely downstream. See
the review for full detail and required changes.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
