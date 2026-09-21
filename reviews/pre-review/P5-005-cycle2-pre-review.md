# P5-005 — Cycle 2 Internal Adversarial Pre-Review

Task: P5-005 — Translation Failure / Retry / Recovery (corrective cycle 2)
Date: 2026-09-21
Origin: consolidated review §2 (P5-005 M-1, L-1; cross-task X-1, X-2)
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Changes

- **Fenced stale recovery (X-1 / L-1):** `StaleTranslationAttemptRecovery`
  selects only `translating` rows with an `attempt_token` and CASes on
  `status = translating AND attempt_token = <captured>`, so a retry between the
  SELECT and UPDATE mints a new token and the recovery cannot fail the newer
  attempt.
- **Converging retry (X-2):** `TranslationRetry` finds any active attempt for the
  same source+target and returns it rather than leaking a unique-index
  exception (covered in `TranslationRetryTest` and the two-process race).
- **Regression tests for X-1/X-2:** `ProcessTranslationJobTest` (old-token job
  after recovery; old-token job after recovery+retry),
  `TranslationOrchestrationTest` (request after retryable/non-retryable failure),
  `TranslationRetryTest` (active-attempt convergence).

## Findings Addressed

| Finding | Status |
|---|---|
| X-1 (AC4) old writers overwrite newer state | FIXED (token-fenced recovery + job fail/claim; regressions added) |
| X-2 (AC2) divergent re-run / unique-index leak | FIXED (single retry path; convergence; regressions added) |
| M-1 tests did not exercise AC4 / request-retry seam | FIXED (X-1/X-2 regressions) |
| L-1 recovery CAS not fenced by started_at | FIXED via attempt-token fence (stronger than started_at) |

## Adversarial Checks

- **Fence:** recovery cannot touch a row whose token changed; job claim/fail
  require the token; writer requires the token.
- **Recoverable failure preserved:** after recovery, `failure_code =
  PROVIDER_TIMEOUT` remains and `isEligible()` is true (regression).
- **No raw exception:** convergence path returns the active row.
- **Concurrency:** the two-process retry race still proves exactly one CAS
  winner (now token-minting).

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  109 passed, 280 assertions (includes both concurrency races).
- Full suite → 542 tests, 541 passed, 1 skipped, 2 warnings, 0 failures.
- Pint clean; PHPStan 0.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.