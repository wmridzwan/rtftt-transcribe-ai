# P5-004 — Cycle 2 Internal Adversarial Pre-Review

Task: P5-004 — Translation Queue / Lifecycle Orchestration (corrective cycle 2)
Date: 2026-09-21
Origin: consolidated review §2 (P5-004 M-1, M-2, L-1, L-2; cross-task X-1, X-2)
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Changes

- **Attempt identity/fencing (X-1):** additive `translations.attempt_token`
  column. Each queued attempt receives a fresh token. `ProcessTranslation`
  claim, failure handler, and `TranslationResultWriter` completion are all
  fenced by token; `StaleTranslationAttemptRecovery` (P5-005) is fenced too.
- **Lifecycle enforcement (M-1):** `ProcessTranslation` checks
  `TranslationLifecycle::canTransition(...)` before claiming; the writer asserts
  `translating → completed`.
- **Queue routing (M-2):** `config/translation.php` gains `queue` and
  `queue_connection`; `TranslationDispatcher` dispatches on the configured queue
  and connection, mirroring Phase 3.
- **Single identity model (X-2):** `TranslationOrchestrator::request()` delegates
  a failed target to `TranslationRetry` (no silent second row); non-retryable
  failures are rejected.
- **Converging retry (X-2):** `TranslationRetry` mints a new token under a
  guarded `failed → queued` CAS and converges on any existing active attempt for
  the same target (no raw unique-index exception).

## Findings Addressed

| Finding | Status |
|---|---|
| X-1 late writer overwrites newer state | FIXED (token fencing across claim/fail/writer/recovery) |
| X-2 divergent re-run paths | FIXED (request delegates to retry; non-retryable rejected) |
| M-1 `TranslationLifecycle` unused | FIXED (job claim + writer use it) |
| M-2 job not on a dedicated queue | FIXED (queue/connection config + dispatcher) |
| L-1 timeout/failed() alignment | Not changed (no `$timeout`/`failed()`); stale recovery remains the path |
| L-2 zero-segment dispatch | Not changed (out of the corrective scope requested) |

## Adversarial Checks

- **Fence proof:** X-1 regressions in `ProcessTranslationJobTest` — after
  recovery, an old-token job does not overwrite; after recovery+retry, an
  old-token job does not touch the new queued attempt.
- **No raw exception:** `TranslationRetryTest` "converges on an active attempt
  instead of leaking a unique-index exception".
- **Source immutability:** unchanged.
- **No mock-as-real claim:** provider still faked; real gate is P5-008.

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  109 passed, 280 assertions.
- Full suite → 542 tests, 541 passed, 1 skipped, 2 warnings, 0 failures.
- Pint clean; PHPStan 0.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.