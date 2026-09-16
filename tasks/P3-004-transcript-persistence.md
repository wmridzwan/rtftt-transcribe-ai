# P3-004 — Transcript Persistence

## Status

BACKLOG

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 2

## Objective

Persist canonical transcript-level output safely from the normalized worker result.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification)
- Existing `Transcription` model (status, full_text, detected_language, model, timestamps)

## Scope

Reuse/reconcile current Transcription schema. Persist:

- full_text (normalized transcript text)
- detected_language (dominant/primary language)
- model (which whisper model produced this)
- status timestamps (started_at, completed_at)
- relationship to MediaFile
- requested language vs detected language as distinct concepts

## Out of Scope

- Segment persistence (P3-005)
- Queue infrastructure (P3-006)
- Schema redesign (minimum additive migration only if strictly required)

## Dependencies

- P3-003 (real provider producing normalized results)

## Acceptance Criteria

1. Full transcript text persisted.
2. Dominant detected language persisted.
3. Requested-language hint not overwritten incorrectly.
4. Correct MediaFile relation preserved.
5. Cross-user relationship impossible.
6. Unicode/mixed scripts preserved (BM, English, Chinese, Tamil).
7. Long transcript supported within repository constraints.
8. DB failure does not produce false completion.
9. Repeated persistence is idempotent.
10. Existing schema reused where possible.
11. Only minimal additive migration allowed if strictly required.
12. All tests pass; Pint clean; PHPStan 0 errors.

## Review

Review File: None yet.
Review Status: PENDING

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED.
