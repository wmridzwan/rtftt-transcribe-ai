# P5-002 — Translation Persistence / Atomic Writer

## Status

DONE — closed by the Human Product Owner on 2026-09-22
(`DECISION-PHASE5-TASK-CLOSURES-001`) on the basis of the recorded independent
VERIFIED verdict (`reviews/P5-002-independent-review.md`). Three MEDIUM findings
retained as non-blocking and carried into P5-002A. Historical review artifacts
preserved unchanged.

## Review

Review File: `reviews/P5-002-independent-review.md`

Review Status: VERIFIED (2026-09-21). MEDIUM-1 (translationId path not bound to
target/lifecycle), MEDIUM-2 (no source-alignment enforcement), MEDIUM-3 (missing
rollback/translationId/race tests); LOW-1 (unique collision surfaces raw),
LOW-2 (source_language informational), LOW-3 (SQLite-only guard, D7-01).

## Follow-Up

- P5-002A — writer target/lifecycle binding, alignment enforcement, collision
  handling, and missing tests, referencing the review above. Recommended before
  P5-004 starts.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Objective

Persist segment-aligned translations in dedicated tables (D5-06) with an
idempotent atomic writer that never mutates `transcriptions` or
`transcription_segments` (D5-05), supports multiple targets per source (D5-03),
and prevents duplicate active/successful translations per
`(transcription_id, target_language)`.

## Scope

1. Additive migrations: `translations` (transcription_id, target_language,
   status, source_language, provider, model, full_text, failure_code,
   started_at/completed_at, timestamps) and `translation_segments`
   (translation_id, segment_index, start_seconds, end_seconds, text,
   source_language) with unique `(translation_id, segment_index)`.
2. Models `Translation` and `TranslationSegment` with casts to the P5-001
   enums, relations, and ordering by `segment_index`.
3. Uniqueness boundary preventing two simultaneously active/successful
   translations for the same target (partial unique index or guarded write).
4. `TranslationResultWriter` persisting a `TranslationResult` atomically in one
   transaction, idempotent for the same attempt, never overwriting a completed
   translation.
5. Tests for schema invariants, atomicity, idempotency, duplicate prevention,
   and source immutability.

## Non-Scope

- queue/job wiring (P5-004); provider runtime (P5-003); UI/export (P5-006/007);
- split/merge or revision semantics (Phase 6);
- any change to Phase 3 tables.

## Dependencies

- P5-001 DONE.

## Acceptance Criteria

1. Migrations are additive, reversible, and SQLite-compatible.
2. Unique target boundary enforced at the database level.
3. Writer is transactional: partial translations are never persisted as
   completed.
4. Repeated writes for the same attempt do not duplicate rows.
5. A completed translation cannot be overwritten by a later write.
6. `transcriptions`/`transcription_segments` rows are unchanged by any write.
7. Tests, Pint, PHPStan pass.

## Verification Requirements

Feature tests with real SQLite; concurrency/duplicate tests; source
immutability assertions.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

No.

## Owner Decision Dependencies

D5-03, D5-05, D5-06 (frozen).