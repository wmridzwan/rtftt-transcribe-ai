# P5-004 — Internal Adversarial Pre-Review

Task: P5-004 — Translation Queue / Lifecycle Orchestration
Date: 2026-09-21
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Scope Inspected

- `app/Jobs/ProcessTranslation.php`
- `app/Actions/TranslationOrchestrator.php`
- `app/Console/Commands/TranslationClaimRaceWorker.php` (test harness)
- `tests/Support/RecordingTranslationProvider.php`
- `tests/Feature/Translation/ProcessTranslationJobTest.php`,
  `TranslationOrchestrationTest.php`, `TranslationClaimConcurrencyTest.php`

## Acceptance Criteria Check

| AC | Result | Evidence |
|---|---|---|
| 1. Queue payload has no media path/binary; only small identifiers | PASS | job constructor holds two ints; payload test |
| 2. Duplicate delivery produces no duplicate inference/rows | PASS | sequential double-delivery test (provider called once); CAS claim; genuine two-process race test (exactly one claim) |
| 3. Terminal translations skipped | PASS | failed and completed skip tests |
| 4. Source transcript/segments never modified | PASS | before/after source comparison; writer guarantees |
| 5. Failure recorded via taxonomy, leaves retryable per lifecycle | PASS | `ProviderTimeout` → `failed` with `failure_code`; manual retry is P5-005 |
| 6. Tests, Pint, PHPStan | PASS | see Evidence |

## Adversarial Checks

- **CAS claim:** single guarded `UPDATE ... WHERE status IN (pending,queued)`; a
  duplicate delivery observes zero affected rows and no-ops. Proven under two
  genuine independent OS processes with separate SQLite connections and a
  filesystem barrier — exactly one claimant; persisted status `translating` with
  `started_at` set. This mirrors the accepted ADR-013 / P3-006 pattern and
  invokes the real private `claim()` via reflection, not a re-implementation.
- **Alignment safety:** the job delegates persistence to the P5-002A writer,
  which rejects misaligned results; a misaligned provider result fails the
  translation with no persisted segments (test).
- **Source immutability (D5-05):** job reads source segments only; no write to
  `Transcription`/`TranscriptionSegment`.
- **Idempotent orchestration (D5-03):** repeated same-target requests return the
  same row and dispatch once; completed rows are returned without dispatch;
  distinct targets coexist.
- **Lifecycle:** claim `queued|pending → translating`; success via writer →
  `completed`; failure → `failed` with taxonomy code.
- **No mock-as-real claim:** provider tests use a recording fake; this is
  orchestration evidence, not the real-provider gate (P5-008 / D5-09).

## Findings / Notes

- INFO-1: manual retry, stale-attempt recovery, and stale-authority guards are
  P5-005 scope; this task records terminal failure only.
- INFO-2: `ProcessTranslation::$tries = 1` (no transport retry), consistent with
  the manual-domain-retry-only policy.
- INFO-3: the orchestrator enforces completed-source only; authorization is
  enforced by the future controller (P5-006).
- INFO-4: the failure message is logged, not persisted (no `error_message`
  column); the durable `failure_code` plus the deterministic taxonomy message
  satisfies UI needs without schema churn.

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  70 passed, 199 assertions.
- Concurrency: `TranslationClaimConcurrencyTest` → 1 passed, 9 assertions
  (two independent processes; exactly one claim).
- Full suite → 504 tests, 503 passed, 1 skipped (pre-existing 2FA), 2 warnings,
  0 failures.
- Pint clean; PHPStan 0 errors.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.