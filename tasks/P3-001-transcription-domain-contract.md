# P3-001 — Transcription Domain Contract

## Status

IN_PROGRESS

## Ownership

Implementation Owner: OpenCode (per AGENTS.md agent model)
Reviewer: Claude Code

## Review Verdict History

Cycle 1 = CHANGES_REQUESTED (`reviews/PHASE3-BATCH1-independent-review.md`)
Cycle 2 = CHANGES_REQUESTED (`reviews/PHASE3-BATCH1-cycle2-independent-review.md`)

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
- `app/Transcription/TranscriptionMedia.php` — opaque storage reference
- `app/Transcription/TranscriptionFailure.php` — 12-category failure taxonomy
- `app/Transcription/TranscriptionException.php` — provider-neutral exception
- `app/Transcription/TranscriptionProvider.php` — provider-neutral interface
- `app/Transcription/TranscriptionInvocation.php` — invocation context (M1)
- `app/Transcription/TranscriptionLifecycle.php` — lifecycle transitions (M2)
- `app/Transcription/TranscriptionIdentity.php` — logical identity (M2)
- `app/Transcription/ProcessingAttemptIdentity.php` — attempt identity (M1/M2)
- `app/Transcription/TranscriptionOwnership.php` — ownership invariants (M2)

### Tests Created

- `tests/Unit/Transcription/LanguageIdentifierTest.php` — 4 tests
- `tests/Unit/Transcription/TranscriptSegmentDataTest.php` — 6 tests
- `tests/Unit/Transcription/NormalizedTranscriptTest.php` — 6 tests
- `tests/Unit/Transcription/TranscriptionOptionsTest.php` — 3 tests
- `tests/Unit/Transcription/TranscriptionMediaTest.php` — 8 tests (L1: Windows paths)
- `tests/Unit/Transcription/TranscriptionExceptionTest.php` — 5 tests
- `tests/Unit/Transcription/DomainContractTest.php` — 15 tests (M2)

### Quality Results

- 54 unit tests passed, 164 assertions
- Pint clean
- PHPStan 0 errors

### Correction Cycle 1 Changes

- M1: Added TranscriptionInvocation context with real IDs
- M2: Added TranscriptionLifecycle, TranscriptionIdentity,
  ProcessingAttemptIdentity, TranscriptionOwnership with tests
- L1: Added Windows drive-letter path rejection to TranscriptionMedia

## Review

Review File: reviews/PHASE3-BATCH1-cycle2-independent-review.md
Review Status: CHANGES_REQUESTED (batch cycle 2)

Cycle 1 findings for this task (M1, M2, L1) are independently confirmed
resolved in cycle 2: TranscriptionLifecycle/TranscriptionIdentity/
ProcessingAttemptIdentity/TranscriptionOwnership are implemented and tested
(15 tests in DomainContractTest.php), TranscriptionInvocation carries real
IDs, and AC4 (explicit language hint) now works end-to-end following the
P3-003 H2 fix. Cycle 2 records one new LOW for this task (L5): the L1
Windows-path fix does not reject forward-slash drive-letter paths
(`C:/...`) and is not exercised by any test; not independently exploitable
because the Python worker still rejects it. This task's own findings do not
block the task in isolation; the batch verdict remains CHANGES_REQUESTED
because of the batch-wide B1 (benchmark gate) and H4 (Python test-suite
import path) findings recorded against P3-003. See the review for full
detail.

### Correction Cycle 2 (2026-09-17)

L5: RESOLVED. `TranscriptionMedia.php`'s drive-letter regex tightened from
`/\A[a-zA-Z]:\\\\/i` (backslash only) to `/\A[a-zA-Z]:[\\\/]/i`, rejecting
both `C:\...` and `C:/...`. Two new tests added directly exercising the
regex (`tests/Unit/Transcription/TranscriptionMediaTest.php` now has 9
tests, not 7). `php artisan test --filter=TranscriptionMediaTest` → 9
passed, 19 assertions. This task's findings are now fully resolved; the
batch verdict still depends on P3-003's B1 (benchmark gate — see
`BENCHMARK-GATE-EVIDENCE.md`, still blocked on missing representative
media, not on Python installation).

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
