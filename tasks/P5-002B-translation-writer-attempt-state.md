# P5-002B — Translation Writer Attempt-State Hardening (Follow-Up)

## Status

IMPLEMENTED_PENDING_REVIEW — corrective follow-up (2026-09-21) addressing
`reviews/P5-001A-P5-002A-P5-003-P5-004-P5-005-P5-007-independent-review.md`
P5-002A M-1, M-2, L-1, L-2. Independent review pending. Not VERIFIED; not DONE.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Origin

Consolidated independent review §2 (P5-002A findings). Recommended before P5-004.

## Objective

Make the translation writer act only on an explicit, correctly-stated attempt
row, and persist authoritative alignment from the source transcript.

## Scope

1. `translationId` is required and validated: the row must exist, belong to the
   transcription, and match the result target; a non-matching id is rejected.
2. Completion is only legal from `translating` (enforced via
   `TranslationLifecycle`); `failed`/`queued`/other states are rejected.
3. Persisted segment alignment (index, timestamps, source language) is copied
   from the authoritative source transcript rows, not from provider output.
4. A rollback test that reaches translated-segment persistence.

## Non-Scope

- attempt-token fencing (introduced in P5-004 cycle 2);
- orchestrator/retry identity model (P5-004/P5-005);
- provider boundary (P5-003).

## Acceptance Criteria

1. Non-matching `translationId` (wrong target/transcription) is rejected.
2. A `failed` or `queued` row cannot be completed directly.
3. Completed rows remain protected/idempotent.
4. Persisted timestamps and source language come from source rows.
5. Misaligned results (count/index/timestamp) are rejected.
6. A segment-persistence failure rolls back the whole write.
7. Tests, Pint, PHPStan pass.

## Review

Independent review pending.

## Completion

READY → IMPLEMENTING → IMPLEMENTED_PENDING_REVIEW → REVIEWING → VERIFIED → DONE.