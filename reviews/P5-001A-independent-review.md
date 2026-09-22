# P5-001A — Independent Review: Translation DTO Input Guards

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-21
Scope: `tasks/P5-001A-translation-dto-input-guards.md` only (commit `5ceebcc`).
This review does not modify implementation code or tests, does not mark the task
DONE, and does not authorize any later task.

**Verdict: VERIFIED** — no BLOCKER / HIGH / MEDIUM / LOW findings; one INFO.
VERIFIED is not DONE; HPO closure is required.

## 1. Contract Checked

`tasks/P5-001A-translation-dto-input-guards.md` (status at review start
`IMPLEMENTED_PENDING_REVIEW`). Origin: `reviews/P5-001-independent-review.md` §5
LOW-1 (non-finite timestamps) and LOW-2 (duplicate invocation indices).
Dependency P5-001 = VERIFIED (satisfied; not yet DONE).

## 2. Implementation Inspected (read in full)

`git show 5ceebcc`: `app/Translation/TranslationSegmentData.php`,
`app/Translation/TranslationInvocation.php`,
`tests/Unit/Translation/TranslationResultTest.php`,
`tests/Unit/Translation/TranslationInvocationTest.php`, the task file, and the
pre-review. Live Git evidence: `git diff HEAD --stat` over `app/Translation`,
`tests/Unit/Translation`, and `tests/Feature/Translation` is empty (working tree
equals HEAD for the reviewed paths). The commit touches only the two DTOs, two
test files, and governance artifacts; no schema, route, worker, or Phase 3/4 file.

## 3. Acceptance Criteria

| AC | Result | Reviewer evidence |
|---|---|---|
| 1. Non-finite timestamps throw `InvalidArgumentException` | PASS | `is_finite()` guards on start and end, placed before the sign/order checks so `NAN` (which fails every comparison) cannot slip past `< 0` / `<` checks. Test covers `NAN` start, `INF` end, `-INF` start with exact messages. |
| 2. Duplicate invocation indices throw | PASS | Constructor loop with a `$seen` set; `create()` routes through the constructor. Test asserts the exact message. |
| 3. Empty invocation list accepted | PASS | Test `allows an empty source segment list` via `create()`; the pre-existing `create()` empty-list test was not weakened. |
| 4. Existing tests unaffected; Pint; PHPStan | PASS | see §4 |

Behavior preserved for valid input: the new guards only reject values that were
previously unvalidated. `TranslationResult` already rejected duplicate indices
on the result side, so the two DTOs are now symmetric.

## 4. Commands Independently Reproduced by the Reviewer

- `vendor/bin/pest tests/Unit/Translation tests/Feature/Translation` →
  57 passed / 165 assertions (working tree, includes P5-002A and P5-003).
- `vendor/bin/pint --test` (read-only) on `app/Translation`,
  `tests/Unit/Translation`, `tests/Feature/Translation` → passed.
- `phpstan analyse app/Translation --memory-limit=1G` → 0 errors.

**Implementer-reported, NOT reproduced:** full PHP suite. Per repo rules the full
suite is run by the user.

## 5. Findings

### BLOCKER / HIGH / MEDIUM / LOW

None.

### INFO-1 — Pre-review evidence figures are not specific to this task

`reviews/pre-review/P5-001A-pre-review.md` reports "44 passed, 129 assertions"
and "477 tests" for this task. Those are identical to the P5-002A pre-review and
match the combined post-P5-002A state (this task adds 3 tests; P5-002A adds 8;
33 + 3 + 8 = 44). The two commits are 7 seconds apart. The claims are true of
the working tree but are not evidence for this task in isolation. No action
needed; noted so the figure is not cited as P5-001A-only evidence.

## 6. Architecture / Security / Regression

Pure value-object validation; no I/O, persistence, or secrets. Consistent with
ADR-022 D5-01 (alignment is a hard invariant). JSON cannot carry `NAN`/`INF`, so
the guard mainly protects programmatic construction and the P5-003 response
validator (which builds `TranslationSegmentData` and now inherits the guard).
Regression risk: none identified.

## 7. Required Changes

None.

## 8. Reviewer Conclusion

**VERIFIED.** All four acceptance criteria are satisfied; reviewed tests pass on
reviewer reproduction; Pint and PHPStan are clean. Not DONE until closed by the
Human Product Owner. VERIFIED does not authorize production deployment.
