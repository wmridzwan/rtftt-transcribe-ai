# P5-004B — Internal Adversarial Pre-Review

Task: P5-004B — Pre-UI Translation Hardening
Date: 2026-09-21
Reviewer: Claude Code as implementation owner (internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification. The implementer also
authored the preceding independent review, so an independent re-review by a
different reviewer is required.

## Changes

- Additive migration `translations.dispatched_at`; `Translation::isAwaitingDispatch()`.
- `TranslationDispatcher`: dispatch failures become a typed
  `TranslationException`; success stamps `dispatched_at`; `ensureDispatched()`
  re-dispatches a queued row that was never dispatched (same attempt token).
- `TranslationOrchestrator::request()`: convergence loop with lock-aware backoff
  (`UniqueConstraintViolationException` and SQLite "database is locked" both
  converge on the winner's row); re-dispatches an undispatched queued row.
- `TranslationRetry::retry()`: reloads the persisted row; retryable codes are part
  of the CAS predicate; converges on queued/translating/pending/completed siblings;
  a unique-index violation converges instead of leaking.
- `ProcessTranslation`: all post-claim work (source load, invocation build,
  provider, writer) runs under one token-fenced failure contract.
- `StaleTranslationAttemptRecovery::recoverAttempt()` (fenced per attempt; refuses a
  null token).
- `worker/requirements.txt`: exact pins `transformers==4.57.6`, `torch==2.9.1`,
  `sentencepiece==0.2.1` (dry-run resolution only; no real-model gate).

## Findings addressed

| Item | Status |
|---|---|
| Fence regression tests (late `fail()`, recovery token change, writer ids, drift) | ADDED |
| Concurrent first request raw exception | FIXED (+ deterministic and two-process tests) |
| Stranded queued row | FIXED (`dispatched_at` + `ensureDispatched`) |
| Post-claim setup failures | FIXED (one failure contract) |
| Stale-model retry of a non-retryable failure | FIXED (CAS predicate + reload) |
| Worker dependency governance | PINNED exactly; guard test added |

## Adversarial checks

- **Mutation evidence** (each production fence removed in place, suite run, file
  restored): fail() token fence → 2 tests fail; recovery CAS token → 1; writer token
  check → 2; claim token → 1; retry CAS retryable predicate → 1; writer timestamps
  copied from provider → 1; request re-dispatch removed → 1. Before this task the
  first, second and sixth mutations left the suite green.
- **Genuine concurrency:** `TranslationRequestConcurrencyTest` runs two OS processes
  against one SQLite file with the real partial unique index. It first FAILED with
  `SQLSTATE[HY000] 5 database is locked` (the loser's lost write-lock upgrade), which
  is why the convergence loop also treats concurrency errors as convergence. Five
  consecutive runs of both two-process tests passed.
- **Duplicate messages:** the loser of a race may re-dispatch the same attempt before
  the winner stamps `dispatched_at`; harmless because the claim is token-fenced. The
  race tests assert one row, one attempt token, and 1–2 messages.
- **Attempt-identity invariant** is unchanged: no fence was weakened.
- **Not addressed (out of scope, unchanged):** job `$timeout`/`failed()`,
  zero-segment dispatch, transient lock errors in the writer classify as the
  non-retryable `PERSISTENCE_FAILED`.

## Evidence

- `php artisan test --compact` (full): 567 tests, 566 passed, 1 skipped, 2 warnings.
- Translation tests: 134 passed, 401 assertions.
- Pint (explicit paths) clean; PHPStan 0 errors.
- Worker `test_translation.py`: 6 passed.
- `pip install --dry-run transformers==4.57.6 torch==2.9.1 sentencepiece==0.2.1`
  resolves on CPython 3.13/Windows. The real-model gate has NOT been run.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.
