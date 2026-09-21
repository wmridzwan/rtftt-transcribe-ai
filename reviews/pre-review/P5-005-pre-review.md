# P5-005 — Internal Adversarial Pre-Review

Task: P5-005 — Translation Failure / Retry / Recovery
Date: 2026-09-21
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Scope Inspected

- `app/Actions/TranslationRetry.php`
- `app/Actions/StaleTranslationAttemptRecovery.php`
- `app/Console/Commands/RecoverStaleTranslationAttempts.php`
- `app/Console/Commands/TranslationRetryRaceWorker.php` (test harness)
- `config/translation.php` (`attempt_stale_seconds`)
- `tests/Feature/Translation/TranslationRetryTest.php`,
  `StaleTranslationAttemptRecoveryTest.php`, `TranslationRetryConcurrencyTest.php`

## Acceptance Criteria Check

| AC | Result | Evidence |
|---|---|---|
| 1. Retry is manual-only; no scheduler/backoff | PASS | no scheduling code; explicit action only |
| 2. Concurrent retries yield exactly one new active attempt | PASS | guarded CAS + genuine two-process race (exactly one dispatch; row queued) |
| 3. Stale `translating` recovers to terminal recoverable state without inference | PASS | recovery CAS `translating→failed` with `ProviderTimeout`; no job dispatch |
| 4. Old writers cannot overwrite newer authoritative state | PASS | CAS predicates; completed never overwritten |
| 5. Completed translations immutable | PASS | completed excluded by eligibility and CAS predicates |
| 6. Tests (incl. genuine concurrency), Pint, PHPStan | PASS | see Evidence |

## Adversarial Checks

- **CAS boundary:** retry is a guarded `UPDATE ... WHERE status='failed'`; the
  loser of the race converges on the active row. The genuine two-process test
  fakes the queue and counts dispatched jobs — exactly one winner.
- **Taxonomy authority:** `isEligible` uses `TranslationFailure::isRetryable()`;
  worker metadata is never consulted.
- **Stale authority:** recovery's guarded update only matches `translating`; a
  completed/other state is untouched.
- **No automatic inference:** recovery moves state only; the job is never
  dispatched by recovery.
- **Completed protection:** `retry()` rejects completed before any write.
- **No mock-as-real claim:** concurrency uses real independent OS processes
  invoking the production action via reflection-free container resolution; the
  queue is faked only to prevent inference, and this is declared.

## Findings / Notes

- INFO-1: the translation row is reused across retries (one row per target), so
  "one new active attempt" means the single row becomes active exactly once; the
  race test proves exactly one process performs the transition/dispatch.
- INFO-2: stale threshold derives from `translation.timeout_seconds + 60` unless
  `translation.attempt_stale_seconds` is set (mirrors Phase 3).
- INFO-3: `translation:recover-stale-attempts` is the operational entry point
  (P7-003 will supervise it in production).

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  84 passed, 237 assertions.
- `TranslationRetryConcurrencyTest` → 1 passed, 9 assertions (two independent
  processes; exactly one retry CAS winner).
- Full suite → 517 tests, 516 passed, 1 skipped (pre-existing 2FA), 2 warnings,
  0 failures.
- Pint clean; PHPStan 0 errors.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.