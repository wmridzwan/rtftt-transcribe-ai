# P5-002A — Internal Adversarial Pre-Review

Task: P5-002A — Translation Writer Correctness and Test Hardening (Follow-Up)
Date: 2026-09-21
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Origin

`reviews/P5-002-independent-review.md` MEDIUM-1, MEDIUM-2, MEDIUM-3, LOW-1.

## Changes

- `TranslationResultWriter::resolveByAttemptId()` scopes the `translationId`
  lookup by `transcription_id` **and** `target_language`; rejects mismatches
  with `TranslationException(InvalidRequest)`; rejects completing a `failed`
  row; returns a completed same-target row idempotently.
- `TranslationResultWriter::assertAligned()` enforces that result segment
  count, indices, and inherited timestamps equal the source transcription's
  segment set.
- Unique-index collisions are caught and re-raised as
  `TranslationException(PersistenceFailed)`.
- Tests added: alignment rejection (count, foreign indices, timestamps),
  `translationId` path (match, wrong target, failed row), mid-write rollback,
  and simulated unique-index collision.

## Acceptance Criteria Check

| AC | Result | Evidence |
|---|---|---|
| 1. Wrong-target translationId throws, no write | PASS | `rejects a translation_id whose target differs…` |
| 2. Failed row cannot complete directly | PASS | `rejects completing a failed translation directly` |
| 3. Completed same-target protected/idempotent | PASS | `never overwrites a completed translation` |
| 4. Misaligned result rejected | PASS | three alignment tests |
| 5. Collision wrapped | PASS | `wraps a unique-index collision…` |
| 6. Rollback/translationId/race tests exist | PASS | new tests |
| 7. Regression, Pint, PHPStan | PASS | see Evidence |

## Adversarial Checks

- **Rollback:** forced failure during `Translation::creating` leaves 0
  translations and 0 translation segments. Confirms AC3 of the original task
  with real evidence (closes MEDIUM-3's evidence gap).
- **Alignment:** count mismatch, foreign index, and timestamp mismatch all
  throw before any write; source immutability test still passes.
- **Lifecycle:** `failed→completed` is now rejected, consistent with
  `TranslationLifecycle`.
- **Collision:** the simulated race is a deterministic single-process
  simulation (not a multi-process race); true serialization is P5-004's CAS
  claim. Declared as simulation, not claimed as real concurrency evidence.
- No accepted behavior changed for valid inputs; idempotency and completed
  protection preserved.

## Findings

- INFO-1: the collision test inserts a competing row inside the writer's
  transaction, so rollback removes it; the test asserts a wrapped failure and
  zero persisted rows. This validates the error boundary, not row convergence.
- INFO-2: `source_language` remains transcript-level informational (LOW-2 from
  the review; deferred to P5-006/P5-007). Not changed here.

## Evidence

- `php vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  44 passed, 129 assertions.
- Full suite → 477 tests, 476 passed, 1 skipped (pre-existing 2FA), 2 warnings,
  0 failures.
- Pint clean; PHPStan 0 errors.

## Verdict

PRE_REVIEW_PASS. Eligible for `IMPLEMENTED_PENDING_REVIEW`; independent review
still required.