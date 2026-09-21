# P5-002B — Internal Adversarial Pre-Review

Task: P5-002B — Translation Writer Attempt-State Hardening
Date: 2026-09-21
Origin: consolidated review §2 (P5-002A M-1, M-2, L-1, L-2)
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Findings Addressed

| Finding | Status |
|---|---|
| M-1 `translationId` not validated when a completed same-target row exists | FIXED — writer resolves strictly by id + transcription + target; a mismatch is rejected. The partial unique index makes the exact M-1 shape (completed same-target + non-matching id) structurally impossible. |
| M-2 provider-echoed alignment persisted | FIXED — timestamps and source language copied from source rows; test proves a wrong provider echo is ignored. |
| L-1 legal source state "not failed" | FIXED — completion requires `translating`, enforced via `TranslationLifecycle`. |
| L-2 rollback test did not reach segment persistence | FIXED — new test forces failure on the 2nd `TranslationSegment` write; the row stays `translating` with 0 segments. |
| L-3 `assertAligned` outside transaction | Retained: alignment validation is read-only and runs before the transaction; no state change occurs on rejection. |

## Acceptance Criteria Check

| AC | Result | Evidence |
|---|---|---|
| 1. Non-matching id rejected | PASS | wrong-target id test |
| 2. failed/queued cannot complete | PASS | two state tests |
| 3. completed protected/idempotent | PASS | idempotency + no-overwrite tests |
| 4. Source-authoritative alignment | PASS | wrong provider echo ignored |
| 5. Misaligned result rejected | PASS | count/index/timestamp tests |
| 6. Segment-level rollback | PASS | forced 2nd-segment failure test |
| 7. Tests/Pint/PHPStan | PASS | see Evidence |

## Adversarial Checks

- **Signature:** `persist(Transcription, TranslationResult, int $translationId)`
  — id is mandatory; the job supplies its claimed row id.
- **Lifecycle:** `TranslationLifecycle::assertValidTransition(translating,
  completed)` is asserted before the write.
- **Source immutability:** only reads source rows; source before/after compared.
- **No mock-as-real claim:** not applicable.

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  103 passed, 266 assertions.
- Full suite → 536 tests, 535 passed, 1 skipped, 2 warnings, 0 failures.
- Pint clean; PHPStan 0.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.