# P5-005 — Translation Failure / Retry / Recovery

## Status

IMPLEMENTED_PENDING_REVIEW — manual retry, stale recovery, genuine two-process
retry concurrency evidence, tests, and internal pre-review complete
(2026-09-21). Not VERIFIED; not DONE. Independent review pending.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Objective

Harden translation failure, manual retry, and abandoned-attempt recovery,
mirroring Phase 3 ADR-018: manual retry only, no automatic schedule, one new
active attempt per valid retry, stale recovery to a terminal recoverable state,
and completed-translation protection.

## Scope

1. Failure taxonomy enforcement and user-safe messaging (P5-001 taxonomy).
2. Manual retry action creating a new attempt via the lifecycle
   `failed → queued`; concurrent retries produce exactly one new active attempt.
3. Abandoned `translating` attempt recovery to a terminal recoverable state,
   with a stale-authority guard, using a conservative threshold derived from the
   provider timeout.
4. Completed translations remain protected and are never overwritten.
5. Tests including genuine concurrency evidence for the retry claim.

## Non-Scope

- automatic retry/backoff; retranslation of completed translations;
- UI (P5-006); provider runtime (P5-003).

## Dependencies

- P5-004 DONE.

## Acceptance Criteria

1. Retry is manual-only; no scheduler or automatic backoff is introduced.
2. Concurrent identical retries yield exactly one new active attempt.
3. Stale `translating` attempts recover to a terminal recoverable state without
   starting inference.
4. Old writers cannot overwrite newer authoritative state.
5. Completed translations are immutable.
6. Tests (including genuine concurrency), Pint, PHPStan pass.

## Verification Requirements

Feature + concurrency tests; evidence of one-active-attempt under genuine
independent processes/connections (ADR-013/016 precedent).

## Expected Reviewer

Claude Code.

## Browser Evidence Required

No.

## Owner Decision Dependencies

D5-03, D5-05 (frozen).