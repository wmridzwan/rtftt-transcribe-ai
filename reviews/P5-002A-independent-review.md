# P5-002A — Independent Review: Translation Writer Correctness and Test Hardening

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-21
Scope: `tasks/P5-002A-translation-writer-correctness.md` only (commit
`4565d76`). This review does not modify implementation code or tests, does not
mark the task DONE, and does not authorize any later task.

**Verdict: VERIFIED** — no BLOCKER / HIGH; one MEDIUM and three LOW, all
non-blocking under the repository rubric. I recommend the MEDIUM be fixed before
P5-004 consumes the writer (§8). VERIFIED is not DONE; HPO closure is required.

## 1. Contract Checked

`tasks/P5-002A-translation-writer-correctness.md` (status at review start
`IMPLEMENTED_PENDING_REVIEW`). Origin: `reviews/P5-002-independent-review.md` §5
MEDIUM-1, MEDIUM-2, MEDIUM-3, LOW-1. Dependency P5-002 = VERIFIED.

## 2. Implementation Inspected (read in full)

`git show 4565d76`: `app/Translation/TranslationResultWriter.php` (read in
full, current), `tests/Feature/Translation/TranslationPersistenceTest.php`, task
file, pre-review; also `TranslationLifecycle`, `TranslationStatus`, the
`translations` / `translation_segments` migrations and the partial unique index.
Live Git evidence: `git diff HEAD --stat` over `app/Translation`,
`tests/Feature/Translation`, and `tests/Unit/Translation` is empty. The commit
touches only the writer, its test, and governance artifacts. `translationId` is
still optional, so the public signature is backward compatible.

## 3. Acceptance Criteria

| AC | Result | Reviewer evidence |
|---|---|---|
| 1. Wrong-target `translationId` throws and writes nothing | PASS for the tested case; **not enforced when a completed same-target row exists** (MEDIUM-1) | `resolveByAttemptId()` scopes by `transcription_id`, compares `target_language`, throws `InvalidRequest`; test confirms the row stays `queued`. Bypassed by the completed-first branch, see below. |
| 2. `failed` row cannot be completed directly | PASS | `resolveByAttemptId()` rejects `Failed`; test `rejects completing a failed translation directly` asserts the row stays `failed`. |
| 3. Completed same-target row protected/idempotent | PASS | Completed row resolved first and returned unchanged; pre-existing tests retained. |
| 4. Result whose segment set/timestamps differ from source is rejected | PASS | `assertAligned()` checks count, index membership, and start/end within 0.0005 s before any write; three tests (count, foreign indices, timestamps). Zero-segment source with zero-segment result correctly passes. |
| 5. Unique collision does not leak a raw query exception | PASS | `UniqueConstraintViolationException` caught outside `DB::transaction` and re-raised as `TranslationException(PersistenceFailed)` with `previous` preserved. |
| 6. Tests exist for rollback, `translationId` path, alignment, collision | PASS (with INFO-1/LOW-3 caveats) | 8 new tests; the rollback test forces a throw in `Translation::creating` and asserts 0 translations / 0 segments. |
| 7. Regression, Pint, PHPStan | PASS | see §4 |

The pre-existing tests were refactored onto a shared `sourceTranscription()`
fixture (two aligned segments) because alignment is now enforced; no assertion
was removed or weakened (diff reviewed).

## 4. Commands Independently Reproduced by the Reviewer

- `vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  57 passed / 165 assertions (working tree, includes P5-003).
- `vendor/bin/pint --test` (read-only) on the reviewed paths → passed.
- `phpstan analyse app/Translation --memory-limit=1G` → 0 errors.
- Four adversarial probes (A1–A4) against the real writer from a temporary Pest
  file **outside the repository** (deleted afterwards; `git status` shows no
  probe or translation-related change from this review). Results in §5.

**Implementer-reported, NOT reproduced:** full PHP suite (477/476/1 skipped).

## 5. Findings

### BLOCKER / HIGH

None.

### MEDIUM-1 — `translationId` is not validated when a completed same-target row already exists

`resolveTarget()` looks up the completed same-(transcription, target) row first
and returns it before `resolveByAttemptId()` is reached, so the id is never
checked. Reproduced against the real writer with a completed `ms` row present:

- **A1:** `translationId = 999999` (nonexistent) → no exception; the completed
  row is returned.
- **A2:** `translationId` = a queued `en` row (wrong target) → no exception; the
  completed `ms` row is returned; the `en` row stays `queued`.
- **A4:** `translationId` = a `failed` `ms` row → no exception; the completed
  row is returned; the failed row stays `failed`.

Scope item 1 says that when `translationId` is supplied the writer must require
a matching row and "reject a non-matching id". No data is written or corrupted
(completed rows stay protected), so this is not HIGH. The concern is the
first consumer: P5-004 will pass the id of the row its job claimed. A stale or
superseded job carrying the wrong id gets a success-shaped `Translation` back
that is not its own row, and its own row is left non-terminal until stale
recovery. This narrows, but does not fully close, the residual noted in
`reviews/P5-002-independent-review.md` MEDIUM-1 ("silently returns that row as
if the write had succeeded").

Rated MEDIUM rather than HIGH because the spec bullet "return a completed
same-target row idempotently" can be read as covering it, and nothing is
mutated. If the HPO reads that bullet as taking precedence, downgrade to LOW.
Suggested fix: validate a supplied `translationId` (existence, transcription,
target) first, then return the completed row only when the id is that row or the
caller's row is itself completed; add a test for each of A1/A2/A4.

### LOW-1 — "Legal lifecycle source state" is implemented as "not failed"

The objective says the path is bound to "a legal lifecycle source state".
`TranslationLifecycle` permits only `translating → completed`. **A3:** a
`pending` row completed via `translationId` succeeds (`pending → completed`), and
the added test itself completes a `queued` row. The task's scope bullet only
requires rejecting `failed`, so the ACs are met, and the no-`translationId` path
of the already-VERIFIED P5-002 also completes `pending`/`queued` rows and
creates rows directly in `completed`. This looks like a deliberate carry-over,
but the objective wording overstates it. Recommend the HPO/orchestrator settle
whether the writer should require `translating` once P5-004 owns the claim, and
record it in P5-004's contract. No change required now.

### LOW-2 — Collision test validates the error boundary only

The test inserts the competing `completed` row inside `Translation::creating`
(same transaction), so rollback also removes the competitor. It proves the raw
exception is wrapped and no rows persist; it does not show convergence on the
winning row, and it is a single-process simulation. The pre-review states both
limits honestly (INFO-1). True serialization remains P5-004's CAS claim; confirm
it there.

### LOW-3 — `assertAligned()` runs outside the transaction and before idempotency

The source segment read happens before `DB::transaction` and before the
completed-row check. Source segments are immutable once a transcription is
completed (D5-05), so the race is theoretical. A side effect: a repeated write
with a stale/misaligned result throws instead of returning the existing
completed row. Acceptable; noted for P5-004 retry semantics.

### INFO-1

`source_language` remains transcript-level informational (P5-002 LOW-2, deferred
to P5-006/P5-007); SQLite-only unique index remains D7-01 debt (P5-002 LOW-3).
Neither was in scope.

## 6. Architecture / Security Review

Consistent with ADR-022 D5-01 (alignment hard rule, no re-segmentation),
D5-03 (target in the row key), D5-05 (source never written; the writer reads
only segments and `detected_language`), D5-06 (content only in translation
tables). No I/O, secrets, routes, or authorization surface introduced.
Exception messages are fixed and provider-safe. `forceFill` receives only
server-derived values.

## 7. Regression Risk

Low. The alignment guard is new behavior on a writer with no production caller
yet; valid aligned inputs behave as before. Any future caller passing results
that do not mirror source segments (including a valid empty result for a
non-empty source) will now be rejected, which is the intended contract.

## 8. Required Changes / Follow-ups

None required for VERIFIED. Per the orchestration policy, MEDIUM/LOW findings
become separate follow-ups that do not start automatically. Recommended:
MEDIUM-1 before P5-004 is promoted (a small P5-002B), LOW-1 recorded in P5-004's
contract, LOW-2/LOW-3 confirmed at the P5-004 review.

## 9. Reviewer Conclusion

**VERIFIED.** Six of seven acceptance criteria are fully met and AC 1 is met for
the specified scenario; the residual (MEDIUM-1) is non-blocking, reproduced, and
documented. Reviewed tests pass; Pint and PHPStan are clean. Not DONE until
closed by the Human Product Owner. VERIFIED does not authorize production
deployment.
