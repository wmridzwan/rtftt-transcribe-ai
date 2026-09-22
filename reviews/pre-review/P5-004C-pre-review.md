# P5-004C — Internal Adversarial Pre-Review

Task: P5-004C — Phase 5 Operational Pre-Flight
Date: 2026-09-22
Origin: `reviews/P5-004B-P5-006-independent-review.md` §4 residuals 1–3, §5
X1/X2, §8 pre-P5-008 items, P4B-4/P4B-6
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification. P5-008 not started; no
real-model gate claimed.

## Changes

- **SQLite concurrency (X1):** `config/database.php` commits
  `'busy_timeout' => env('DB_BUSY_TIMEOUT', 5000)`. Runtime no longer depends on
  an uncommitted local config. Regression: `PRAGMA busy_timeout > 0` in
  `TranslationOperationalPreflightTest`.
- **Job timeout + failed() (X2/residual 2):** `ProcessTranslation::$timeout`
  from `translation.job_timeout_seconds` (330 s, between provider 300 s and
  retry_after 420 s). Token-fenced `failed()` handler routes a killed/timed-out
  attempt through `fail()`; a stale token cannot mark a newer attempt failed.
- **Worker runbook (X2):** `worker/TRANSLATION-OPERATIONS.md` documents the
  canonical command `php artisan queue:work --queue=translation --timeout=330
  --tries=1`, the queue name, and the provider/job/retry_after reconciliation;
  it states the generic default worker is not sufficient.
- **Scheduled recovery (residual 3):** `translation:recover-stale-attempts`
  scheduled every minute, `withoutOverlapping()`, token-fenced; scheduler
  evidence via `php artisan schedule:list`.
- **Persistence taxonomy (residual 1):** transient persistence/concurrency
  failures map to retryable `PROCESSING_FAILED`; deterministic
  integrity/constraint failures stay non-retryable `PERSISTENCE_FAILED`.
  Recorded as `DECISION-P5-PERSISTENCE-FAILURE-001` in `DECISION_QUEUE.md`.
- **Test debt (P4B-4):** post-claim `refresh()` failure path covered.
- **Provenance:** additive note in `tasks/P5-004B-pre-ui-hardening.md`; no
  historical record rewritten.

## Timeout reconciliation

| Value | Config | Default |
|---|---|---|
| Provider timeout | `translation.timeout_seconds` | 300 s |
| Job timeout | `translation.job_timeout_seconds` | 330 s |
| Required retry_after | `translation.retry_after_seconds` | 420 s |
| Stale threshold | `translation.attempt_stale_seconds` | provider + 60 = 360 s |

Invariant provider < job < retry_after. `TranslationQueueConfig::assertConsistent()`
is a boot guard for non-test runtimes; `consistencyViolation()` holds the pure
logic and is covered directly.

## Adversarial checks

- **Fence preservation:** `failed()` uses the same token-fenced `fail()`; a
  stale-token `failed()` leaves a newer attempt untouched (test).
- **No false fatal:** the boot guard is skipped under the test runner, so CLI
  child processes (Phase 3 race workers) are unaffected — this was discovered as
  a regression during implementation and fixed.
- **Taxonomy:** lock → retryable; unique constraint → non-retryable (tests).
- **No real-model claim:** provider is faked in tests; P5-008 remains open.

## Evidence

- Translation suite: **188 passed, 716 assertions**.
- Full suite: **621 tests, 620 passed, 1 skipped (pre-existing 2FA),
  3 warnings, 0 failures**.
- Concurrency races (Phase 3 retry, translation claim/retry/request): passing.
- Pint clean; PHPStan 0.
- Worker tests: 42 passed.
- `php artisan schedule:list` → `translation:recover-stale-attempts` every minute.

## Residual findings

- The 3rd full-suite warning is non-blocking (pre-existing baseline was 2).
- P4B-6 (hand-built race schema), `@playwright/test` lockfile declaration, and
  the real-model gate remain out of scope; the real-model gate is P5-008.

## Verdict

PRE_REVIEW_PASS. P5-004C = `IMPLEMENTED_PENDING_REVIEW`; independent review
required. Not VERIFIED.