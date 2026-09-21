# P5-004B — Pre-UI Translation Hardening (Follow-Up)

## Status

IMPLEMENTED_PENDING_REVIEW — corrective pass 2026-09-22 addressing
`reviews/P5-004B-P5-006-independent-review.md` P4B-1, P4B-2, P4B-3 (and P4B-4,
P4B-5). Pending light fresh independent re-review; all three fences
mutation-killed. Not VERIFIED; not DONE.

## Ownership

Implementation Owner: Claude Code (explicit HPO reassignment for this task; the
same agent performed the preceding independent review, so **this task requires a
different independent reviewer / fresh session**).
Reviewer: an independent reviewer that did not implement it.

## Authorized Phase

Phase 5 — Translation (ADR-022). Authorized by the HPO instruction of
2026-09-21 ("final pre-UI Phase 5 hardening") after
`reviews/P5-corrective-batch-cycle2-independent-review.md` (VERIFIED for P5-003
c2, P5-002B, P5-004 c2, P5-005 c2).

## Origin

`reviews/P5-corrective-batch-cycle2-independent-review.md`: P5-004 M-1, M-2, M-3,
L-2; P5-005 L-1; P5-002B L-1/L-2; INFO-2 (worker dependency governance).

## Scope

1. Attempt-fence regression coverage (late in-flight failure/success after stale
   recovery and after a newer retry; recovery after the token changes; writer
   nonexistent/foreign/wrong-target ids; source-authoritative timestamps under
   in-tolerance provider drift).
2. `TranslationOrchestrator::request()` converges concurrent first requests
   (no raw `UniqueConstraintViolationException`, lock-aware backoff).
3. Re-dispatch of a `queued` row whose dispatch failed (`dispatched_at`), same
   attempt token, no second row; post-claim setup failures flow through the
   failure taxonomy.
4. Retryability enforced in the persisted retry compare-and-set; the persisted
   row is reloaded before eligibility; pending/completed siblings converge.
5. Worker model dependencies pinned exactly (approved Phase 5 dependencies).

## Non-Scope

Real-model gate (P5-008), job `$timeout`/`failed()` and worker `--timeout`
runbook, zero-segment handling, UI (P5-006), the wider Phase 3/4 baseline.

## Acceptance Criteria

1. Each new fence test fails when its production fence is removed (mutation
   evidence recorded in the pre-review).
2. Concurrent first requests never surface a raw constraint/lock exception
   (deterministic simulation + genuine two-process test).
3. A stranded `queued` row is re-dispatched by the next request/retry with the
   same attempt token and no duplicate row; a dispatched row is not re-dispatched.
4. A stale in-memory model cannot requeue a non-retryable failure.
5. Worker model dependencies are `==` pinned; no real-model gate is claimed.
6. Tests, Pint, PHPStan pass.

## Review

Pre-review: `reviews/pre-review/P5-004B-pre-review.md` (initial) and
`reviews/pre-review/P5-004B-corrective-pre-review.md` (corrective).
Independent review: `reviews/P5-004B-P5-006-independent-review.md`
(CHANGES_REQUESTED → corrective pass). Independent re-review pending.
