# P5-004B Corrective — Independent Re-Review

Reviewer: Claude Code (independent reviewer role, `.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-22
Branch: `phase5-7/parallel-2026-09-21`, HEAD `6b0480f`
Scope: corrective commit `6b0480f` for `reviews/P5-004B-P5-006-independent-review.md`
findings P4B-1, P4B-2, P4B-3 (required) and P4B-4, P4B-5 (optional). Cycle 2 of at
most 3 for P5-004B.

No application code, test, migration, task status, or governance file was modified.
This artifact is the only file written. No task is marked DONE. P5-008 was not
started. All mutation and probe work ran in a `git archive HEAD` copy under the
session scratchpad, never in the repository.

Independence: fresh review context; I did not implement the corrective. Source,
diff and tests were read directly and every result below was produced by a command
I ran. The implementer's corrective pre-review
(`reviews/pre-review/P5-004B-corrective-pre-review.md`) was treated as a claim. Its
header names OpenCode as the pre-reviewer, while the task file names Claude Code as
implementation owner; the ownership record is inconsistent (INFO-1). I could not
resolve who implemented it, so independence here rests on a fresh context and my own
executed evidence.

## 1. Verdict

| Task | Verdict | Blocking findings |
|---|---|---|
| P5-004B — pre-UI orchestration hardening (corrective) | **VERIFIED** | None. No BLOCKER or HIGH. Three LOW findings and two INFO |

VERIFIED does not mean DONE. The Human Product Owner closes it. VERIFIED does not
authorize P5-008: the operational pre-flight items in section 5 are still open.

One judgment call, stated openly: the LOW finding R-1 is an optional change that
shipped without a regression test. By the severity rule it does not prevent
VERIFIED, so I have not withheld it. The repo's test-enforcement rule ("test every
code change") says it should have a test. The HPO may reasonably ask for that test
(about 25 lines) before closing. It is a small change and does not need another
review cycle.

## 2. Required findings from the prior review

| ID | Prior finding | Status | Evidence |
|---|---|---|---|
| P4B-1 | `dispatched_at` stamp failure after a successful push leaked a raw exception (HTTP 500) | **Closed** for the exception. See R-2 for a residual UI mislabel | Stamp is in its own `try/catch (Throwable)` after the push (`TranslationDispatcher.php:56-72`) and is logged. Mutation MK1: re-throwing in the catch turns 1 test red (1 errored of 176) |
| P4B-2 | No test for the `dispatched_at` token fence | **Closed** | Mutation MK2: removing `->where('attempt_token', ...)` from the stamp is killed by `does not stamp dispatched_at for a superseded attempt token (P4B-2)` (`dispatched_at` becomes non-null). Previously all tests stayed green |
| P4B-3 | No test for the "unrelated DB failures are not swallowed" guard | **Closed** | Mutation MK3: `if (! causedByConcurrencyError(...))` replaced with `if (false)` is killed by the P4B-3 test (`TranslationException` instead of `QueryException`). Previously all tests stayed green |

The three new tests assert the right things. P4B-1 checks that the request returns
the still-queued attempt and exactly one job was pushed. P4B-2 dispatches with a
stale token against a row holding a newer one. P4B-3 uses a `creating` hook that
throws a `QueryException` with a non-concurrency message and asserts it propagates
un-wrapped and nothing is pushed. The `creating` listener and
`DB::beforeExecuting` hook are per-application-instance, so they do not leak between
tests; the suite passes as a whole.

## 3. Optional changes

| ID | Change | Assessment |
|---|---|---|
| P4B-4 | `ProcessTranslation::claim()` moves `refresh()` inside the token-fenced failure contract | **Code correct, no test** (R-1) |
| P4B-5 | `TranslationOrchestrator` logs when convergence is exhausted | Correct. Log-only and unasserted (INFO-2) |

Probe PROBE-A (scratch): failing the post-claim `refresh()` select leaves the row
`failed` with `PROCESSING_FAILED`, the provider is never called, and `handle()` does
not throw. So the pre-review's P4B-4 claim is true of the code. It is only unproven
by any committed test.

## 4. Findings

| ID | Sev | Finding |
|---|---|---|
| R-1 | LOW | **The P4B-4 change has no regression test.** Mutation MK4 (re-throwing from the new `catch` in `claim()`, which removes the failure contract) leaves all 176 translation tests green. The pre-review marks P4B-4 "CLOSED" and lists mutation checks only for P4B-1 to P4B-3. Task AC 1 says every new fence must have a test that fails when it is removed; the repo rule is to test every code change. PROBE-A in the scratch copy is a working starting point. Also, after a failed refresh `claim()` returns `false`, so `handle()` then logs the misleading reason "attempt superseded or already claimed". A `fail()` that itself throws still escapes `handle()`, as before (previously noted under P4B-4). |
| R-2 | LOW | **A swallowed stamp failure leaves the row displayed as "could not be queued".** The row stays `queued` with `dispatched_at = null`, so `isAwaitingDispatch()` is true. PROBE-B (scratch): with the stamp write failing, `store()` returns 302, exactly one job is pushed, and the workspace page contains `data-translation-awaiting-dispatch` ("This translation was created but could not be queued. Try again to start it.") for a job that is queued and will run. That state does not poll, so the banner stays until the user reloads or clicks Try again (which causes one harmless duplicate message; the claim is token-fenced). The raw 500 is gone, which is what the prior review's fix prescribed, but the misleading text the prior review described is not. Requires a DB write to fail right after the row write and the push succeeded, so it is rare. It self-corrects once a worker claims the row (status becomes `translating`). Not a blocker. |
| R-3 | LOW | **`TranslationHardeningTest.php` is not self-contained.** It calls `translationSource()` and `writerSource()`, which are defined in `ProcessTranslationJobTest.php` and `TranslationPersistenceTest.php`. Running the file alone gives 19 errors "Call to undefined function translationSource()". Directory and full-suite runs pass. Pre-existing: the earlier tests in the file at line 33 have the same dependency, and the new tests inherit it. It matters because a targeted run of this file, as the task's "run the narrowest set" guidance suggests, fails misleadingly. |
| INFO-1 | INFO | Ownership record inconsistent: the task file names Claude Code as implementation owner; the corrective pre-review header names OpenCode. |
| INFO-2 | INFO | The two new log lines include `getMessage()` of a `QueryException`, which contains SQL and bindings. For these statements the bindings are ids, target code, status, attempt token and timestamps; no transcript text or credentials. The attempt token is a fence identifier, not a secret. Acceptable. MK5 (removing the P4B-5 log) survives, which is expected for a log-only change. |

## 5. Still open before P5-008 (unchanged by this corrective)

The corrective was scoped to P4B-1 to P4B-5, and the pre-review says so. From
`reviews/P5-004B-P5-006-independent-review.md` section 8, these remain open and
were not addressed:

- X1: `busy_timeout` is set only in the working-tree `config/database.php`, which
  still shows as modified in `git status`. Under the committed config, `retry()` and
  the dispatch stamp can surface raw lock errors.
- Job `$timeout` (below `retry_after`), a token-fenced `failed()` handler, and a
  documented `queue:work --queue=translation --timeout=...`.
- Scheduled recovery of stale attempts and a story for dispatched-but-lost queued
  rows.
- HPO decision on the `PERSISTENCE_FAILED` classification, logged in
  `DECISION_QUEUE.md`.

## 6. Evidence reproduced by the reviewer

**Working tree** (HEAD `6b0480f` plus the dirty baseline; the three changed source
files and the test file are identical to HEAD):
- `php artisan test --compact tests/Feature/Translation tests/Unit/Translation`:
  176 passed, 685 assertions.
- Three two-process concurrency tests (`TranslationClaimConcurrencyTest`,
  `TranslationRequestConcurrencyTest`, `TranslationRetryConcurrencyTest`), 3
  consecutive runs: 3/3 passed each run, 37 assertions.
- `vendor/bin/pint --test` on the four changed PHP files: passed.
- PHPStan: 0 errors.

**Clean `git archive HEAD`** (vendor and `public/build` copied in, working-tree
`config/database.php` copied in):
- Same translation suites: 176 passed, 685 assertions.

**Mutation testing** (scratch copy, 5 mutations; each file restored and confirmed
identical to the repository afterwards):

| Mutation | Result |
|---|---|
| MK1 stamp swallow removed (P4B-1) | Killed (1 error) |
| MK2 stamp token fence removed (P4B-2) | Killed (P4B-2 test) |
| MK3 concurrency guard disabled (P4B-3) | Killed (P4B-3 test) |
| MK4 claim-refresh failure contract removed (P4B-4) | **Survived** (R-1) |
| MK5 convergence-exhausted log removed (P4B-5) | Survived (log-only, INFO-2) |

**Scratch probes** (2 tests, scratch copy only, not committed): PROBE-A confirms the
P4B-4 code path works; PROBE-B confirms R-2.

**Not run:** the full application suite (the repo asks that the user run it), the
real provider or model (P5-008), a browser or Playwright run, MySQL or PostgreSQL.
The only files this corrective touches outside translation code are the pre-review
and task documents, so I judged the translation suites sufficient for a light
re-review, but that is a judgment and not a reproduced result.

## 7. Recommended next step

1. HPO may close P5-004B as DONE (VERIFIED). Optionally ask the implementer first
   for the R-1 test and, if wanted, R-2 (treat a not-yet-stamped queued row as
   queued in the UI, or poll in that state).
2. P5-006 was VERIFIED in the prior review and can be closed with P5-004B, keeping
   the pre-UI-hardening-first order.
3. Do not start P5-008 until the section 5 items have an owner. They can be one
   small task or explicit P5-008 entry conditions.
