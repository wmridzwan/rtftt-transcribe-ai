# P5-002A — Translation Writer Correctness and Test Hardening (Follow-Up)

## Status

VERIFIED — independent review `reviews/P5-002A-independent-review.md`
(2026-09-21) returned VERIFIED with no BLOCKER/HIGH; one MEDIUM (`translationId`
not validated when a completed same-target row exists) and three LOW recorded as
non-blocking. Not DONE; HPO closure required.

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

Review File: `reviews/P5-002A-independent-review.md`

Review Status: VERIFIED (2026-09-21). MEDIUM-1 (supplied `translationId` bypassed
when a completed same-target row exists; recommended fix before P5-004); LOW-1
(lifecycle source state enforced only as "not failed"), LOW-2 (collision test
proves the error boundary, not convergence), LOW-3 (alignment check outside the
transaction).

## Completion

READY → IMPLEMENTING → IMPLEMENTED_PENDING_REVIEW → REVIEWING → VERIFIED → DONE.