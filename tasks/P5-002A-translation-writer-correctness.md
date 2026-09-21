# P5-002A — Translation Writer Correctness and Test Hardening (Follow-Up)

## Status

IMPLEMENTED_PENDING_REVIEW — writer fixes, tests, and internal pre-review
complete (2026-09-21). Not VERIFIED; not DONE. Independent review pending.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Origin

`reviews/P5-002-independent-review.md` §5. Recommended to complete before P5-004
consumes the writer.

## Objective

Close the writer's data-integrity boundary: bind the `translationId` path to the
result target and a legal lifecycle source state, enforce source alignment,
handle unique-index collisions deterministically, and add the missing tests.

## Scope

1. **MEDIUM-1:** when `translationId` is supplied, require the row's
   `transcription_id` and `target_language` to match the result target; reject a
   `failed` row (raise `TranslationException(InvalidRequest)`); return a
   `completed` same-target row idempotently; reject a non-matching id.
2. **MEDIUM-2:** enforce source alignment at the persistence boundary: persisted
   result segment indices/timestamps must equal the source transcription's
   segment set; otherwise raise `TranslationException` (missing/malformed).
3. **LOW-1:** catch a unique-index collision and raise
   `TranslationException(PersistenceFailed)` (or converge on the winning row)
   rather than leaking a raw query exception.
4. **MEDIUM-3 tests:** rollback on mid-write failure; the `translationId` path
   (match, wrong-target, failed-row); alignment rejection; a simulated
   collision race.

## Non-Scope

- provider boundary (P5-003); queue/CAS (P5-004);
- changing the SQLite-only index policy (D7-01);
- changing `source_language` semantics (LOW-2).

## Dependencies

- P5-002 VERIFIED; P5-001A is independent.

## Acceptance Criteria

1. Wrong-target `translationId` throws `TranslationException` and writes nothing.
2. A `failed` row cannot be completed directly.
3. Completed same-target row remains protected/idempotent.
4. A result whose segment set/timestamps differ from the source is rejected.
5. Unique collision does not leak a raw query exception.
6. Tests exist for rollback, `translationId` path, alignment, and collision.
7. Full regression, Pint, PHPStan pass.

## Review

Independent review pending.

## Completion

READY → IMPLEMENTING → IMPLEMENTED_PENDING_REVIEW → REVIEWING → VERIFIED → DONE.