# P5-004B — Corrective Internal Adversarial Pre-Review

Task: P5-004B — Pre-UI Translation Hardening (corrective pass)
Date: 2026-09-22
Origin: `reviews/P5-004B-P5-006-independent-review.md` §2 P4B-1, P4B-2, P4B-3
(plus optional P4B-4, P4B-5)
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification. P5-004B remains
`IMPLEMENTED_PENDING_REVIEW`.

## Changed files

- `app/Actions/TranslationDispatcher.php` (P4B-1)
- `app/Actions/TranslationOrchestrator.php` (P4B-3 guard log, P4B-5)
- `app/Jobs/ProcessTranslation.php` (P4B-4)
- `tests/Feature/Translation/TranslationHardeningTest.php` (P4B-1/2/3 tests)

## Findings addressed

### P4B-1 — dispatched_at stamp failure after a successful push — CLOSED
The stamp write now sits in its own `try/catch (Throwable)` **after** the
successful push. A failure is logged with translation id, transcription id,
attempt token, exception class and message, and is swallowed; the caller is not
told dispatch failed. Regression:
`does not surface a dispatch failure when only the dispatched_at stamp fails (P4B-1)`
uses `DB::beforeExecuting` to fail only the stamp update, then asserts the
request returns a valid queued attempt (`isAwaitingDispatch()` true) and the job
was pushed.

### P4B-2 — dispatched_at token fence regression — CLOSED
New test `does not stamp dispatched_at for a superseded attempt token (P4B-2)`
dispatching with an old token against a row holding a newer token; the stamp
must not apply and `dispatched_at` stays null.

### P4B-3 — unrelated DB errors must not be swallowed — CLOSED
New test `propagates an unrelated database error instead of treating it as a
concurrency retry (P4B-3)` forces a base `QueryException` (non-concurrency
message) from the row insert and asserts it propagates as a `QueryException`,
not a `TranslationException` convergence failure.

### P4B-4 (LOW, optional) — CLOSED
`ProcessTranslation::claim()` now wraps the post-claim `refresh()` in the
token-fenced failure contract: on failure it logs and routes through `fail()`
instead of stranding the row in `translating`.

### P4B-5 (LOW, optional) — CLOSED
`TranslationOrchestrator` logs `Translation request convergence exhausted.` with
source, target, attempts and the underlying exception message before throwing.

## Mutation checks (executed, reverted)

| Mutation | Test | Result |
|---|---|---|
| Remove the stamp `try/catch` (P4B-1 fix) | `P4B-1` | **Killed** — raw `QueryException('stamp write failed')` escapes |
| Remove `->where('attempt_token', …)` from the stamp (M08) | `P4B-2` | **Killed** — `dispatched_at` becomes non-null on the newer row |
| Replace `if (! causedByConcurrencyError(...))` with `if (false)` (M11) | `P4B-3` | **Killed** — propagates as `TranslationException` instead of `QueryException` |

All three fences were restored after mutation; the three tests pass again.

## Evidence

- P5-004B targeted (translation dir filters): P4B-1 1/1, P4B-2 1/1, P4B-3 1/1.
- Translation suite: **176 passed, 685 assertions**.
- Two-process concurrency (`TranslationClaimConcurrencyTest`,
  `TranslationRetryConcurrencyTest`, `TranslationRequestConcurrencyTest`):
  3/3 passed on 3 consecutive runs (37 assertions each).
- Full suite: **609 tests, 608 passed, 1 skipped (pre-existing 2FA),
  2 warnings, 0 failures**.
- Pint: passed. PHPStan: 0 errors.

## Residual findings

- P4B-6 (LOW): race tests hand-build schema; index drift not caught. Deferred.
- P4B-7/P4B-8, X1 (committed `busy_timeout`), X2 (queue runbook), the job
  `$timeout`/`failed()` handler, scheduled recovery, and the
  `PERSISTENCE_FAILED` taxonomy decision remain outside this narrowly scoped
  pass and are still required before P5-008.

## Verdict

PRE_REVIEW_PASS. P5-004B remains `IMPLEMENTED_PENDING_REVIEW`; a light fresh
independent re-review is recommended (scope confined to the dispatcher,
orchestrator, job and one test file). Not VERIFIED.