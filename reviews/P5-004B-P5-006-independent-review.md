# P5-004B and P5-006 — Independent Review

Reviewer: Claude Code (independent reviewer role, `.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-22
Branch: `phase5-7/parallel-2026-09-21`, HEAD `dab7506`
Scope: `P5-004B` (pre-UI translation orchestration hardening) and `P5-006`
(Translation Workspace UI), plus six named residual assessments.

No application code, test, migration, task status, or governance file was modified
by this review. This artifact is the only file written. No task is marked DONE.
P5-008 was not started. All mutation and probe work ran in a `git archive` copy
under the session scratchpad, never in the repository.

Independence: reviewed as a fresh context from source, tests, migrations, routes,
commits, browser evidence, and my own executed commands. The implementer's
pre-reviews (`reviews/pre-review/P5-004B-pre-review.md`,
`reviews/pre-review/P5-006-pre-review.md`) were treated as claims, not evidence.
The implementer also authored the preceding independent review, so this fresh
review was required by the task files.

## 1. Verdicts

| Task | Verdict | Blocking findings |
|---|---|---|
| P5-004B — pre-UI orchestration hardening | **CHANGES_REQUESTED** | No BLOCKER or HIGH. MEDIUM: P4B-1, P4B-2, P4B-3 |
| P5-006 — Translation Workspace UI | **VERIFIED** | None. All seven acceptance criteria satisfied |

Severity legend: BLOCKER / HIGH / MEDIUM / LOW / INFO. BLOCKER or HIGH prevents
VERIFIED.

Basis for P5-004B CHANGES_REQUESTED: the severity rule alone would permit VERIFIED.
VERIFIED is withheld because two explicit reviewer-checklist items fail: (a)
regression tests must fail when each critical fence is removed, and (b) the case
where dispatch succeeds but `dispatched_at` persistence fails must be handled. The
corrective is small (about 40 lines plus tests).

## 2. P5-004B

### Confirmed correct

- Concurrent first `request()` calls converge without a raw unique or lock
  exception, in both the deterministic test and the genuine two-process test
  (`TranslationRequestConcurrencyTest`, `TranslationRetryConcurrencyTest`,
  `TranslationClaimConcurrencyTest`; 3 additional consecutive runs all passed).
- A queued-but-undispatched row is re-dispatched with the same attempt token and no
  second row. An already-dispatched row is not re-dispatched.
- Retry reloads the persisted row, enforces retryability in the compare-and-set, and
  converges on queued, translating, pending or completed sibling rows. A stale
  in-memory model cannot requeue a currently non-retryable failure.
- Claim, writer, `fail()`, stale recovery and retry all preserve the
  attempt-identity invariant, and tests kill each fence's removal (see section 6).
- Duplicate messages produced by concurrent re-dispatch are harmless: the claim is
  a token-fenced compare-and-set, so a superseded token never executes.

### Findings

| ID | Sev | Finding |
|---|---|---|
| P4B-1 | MEDIUM | **`dispatched_at` stamp failure after a successful push leaks a raw exception and mislabels the row.** `TranslationDispatcher::dispatch()` writes `dispatched_at` after the push, outside any try/catch. A probe that failed that write produced HTTP 500 while the job was already queued and the row was left `isAwaitingDispatch()`. The workspace then says "could not be queued" for a translation that will run. The next click causes a harmless duplicate message. No test covers this path. Fix: log and swallow a stamp failure after a successful push, and add a test. |
| P4B-2 | MEDIUM | **The `dispatched_at` token fence has no regression test.** Removing `->where('attempt_token', $attemptToken)` from the stamp (mutation M08) leaves all 108 relevant tests green. Without it, a late stamp from a superseded attempt could mark a newer attempt dispatched when it never was, stranding it. The pre-review mutation list did not include this fence. |
| P4B-3 | MEDIUM | **The "unrelated DB failures are not swallowed" guard has no regression test.** Replacing `if (! $this->concurrency->causedByConcurrencyError($exception))` with `if (false)` (M11) leaves all tests green. The guard is present in the code but unproven. |
| P4B-4 | LOW | `ProcessTranslation::claim()` calls `$translation->refresh()` after the claim update and outside the failure contract. An error there strands the row in `translating`. "All post-claim failures are fenced" is slightly overstated. A failing `fail()` has the same effect. |
| P4B-5 | LOW | When convergence retries are exhausted, `TranslationException` chains the underlying exception but nothing logs it. |
| P4B-6 | LOW | The race tests hand-build their schema instead of running the migrations, so index drift would not be caught. |
| P4B-7 | INFO | Removing the `status = failed` clause from the retry CAS (M18) survives, but is an equivalent mutation: `failure_code` already restricts the match. |
| P4B-8 | INFO | The active-target partial unique index exists only for SQLite (the canonical environment). |

## 3. P5-006

### Acceptance criteria

| AC | Result |
|---|---|
| 1. Select a target and start | PASS |
| 2. Progress and failure states accurate and accessible | PASS |
| 3. Toggle does not mutate the source | PASS |
| 4. Copy full text and per segment | PASS |
| 5. Unicode round-trips (ms, en, zh, ta) | PASS |
| 6. Ownership isolation | PASS |
| 7. Browser evidence, tests, Pint, PHPStan | PASS |

### Confirmed correct

- Authorization runs before any service call on start, retry, status, workspace and
  exports. Removing each of six authorization and retry-gating fences (M12 to M17)
  is killed by a test. The start request authorizes before validation.
- Retry reloads the persisted row and evaluates eligibility on it. A forged retry
  POST for a non-retryable failure is refused and leaves state untouched.
- Failure text is drawn from fixed `TranslationFailure::userMessage()` strings.
  Exception messages are never rendered. No secrets, endpoints or provider
  internals appear.
- Hostile HTML and mixed-script text (Arabic, Tamil, Chinese, emoji) is escaped in
  markup and in the copy payload (probe S6).
- Invalid or unsupported targets are rejected. Double submission converges on one
  row at the product surface. The workspace is reachable for a non-completed
  transcript only as an "unavailable" state with no start action.
- Browser evidence genuinely covers the required behavior, including a verified
  second user for cross-user isolation and a live non-retryable failure through the
  real job. Caveats are in P6-4 and INFO-4.

### Findings

| ID | Sev | Finding |
|---|---|---|
| P6-1 | MEDIUM | A translation stuck in `translating` or `queued` polls forever every 2 s with no "taking too long" message and no user action. Depends on residuals 2 and 3. |
| P6-2 | MEDIUM | `store()` and `retry()` catch only `TranslationException`. A raw lock error from the retry CAS returns HTTP 500 (probe S7). Same root cause as X1. |
| P6-3 | LOW | The non-retryable failure text says "contact your administrator", but no administrator tool exists to reset a failed row. |
| P6-4 | LOW | The awaiting-dispatch state has no browser test. The retryable failure in the browser run was seeded, not produced live. Copy uses the async Clipboard API with no fallback outside a secure context. |
| P6-5 | INFO | The start notice says "Translation started." even when the request converged on an already-completed row. No throttling exists on start or retry. |

## 4. Residual assessments

1. **`PERSISTENCE_FAILED` non-retryable.** Safe for data integrity, but a
   product-level dead end. Probe S3 simulated a transient lock while saving: the
   row became permanently failed, and start, retry and the workspace all offered no
   way out. It is not a P5-006 blocker: P5-006 correctly follows the frozen taxonomy
   and the HPO's "no Retry for non-retryable" rule, and changing the taxonomy is an
   HPO decision. Recommendation: map concurrency errors in the persist step to the
   retryable `ProcessingFailed`, or make `PERSISTENCE_FAILED` retryable. Record the
   decision in `DECISION_QUEUE.md`.
2. **Missing per-job `$timeout`.** Confirmed, and worse than the pre-review states.
   `ProcessTranslation` has only `$tries = 1`: no `$timeout` and no `failed()`
   handler. `retry_after` defaults to 90 s, the provider timeout is 300 s, and the
   stale threshold is 360 s. `composer run dev` runs `queue:listen` on the default
   queue only, so translation jobs never run under it. A default `queue:work`
   (60 s timeout) will kill any real translation and leave the row in
   `translating`. Fix before P5-008.
3. **No scheduled stale-attempt recovery.** Confirmed: `php artisan schedule:list`
   reports "No scheduled tasks". A queued row that was dispatched but whose message
   is lost is never recovered or re-dispatched (probe S5). Fix before P5-008.
4. **Zero-segment translation.** Probe S2 confirmed it completes as an empty
   translation after one wasted provider call. The UI shows "No translated
   segments" with Copy and Export still enabled. It is reachable because Phase 3
   permits completed transcripts with zero segments. LOW; it needs a product
   decision and can wait.
5. **`showRenameModal is not defined`.** Pre-existing: the root `x-data` and modal
   placement are identical at HEAD~5, and the error reproduced on clean HEAD at
   `/transcriptions/1`. The workspace page raised zero page or console errors. The
   evidence filter matches URLs containing `/translations`, which does not match
   `/transcriptions/...`, so it is sound. It does not contaminate the P5-006
   evidence.
6. **Clean-checkout reproducibility.** Sufficient for P5-006. HEAD alone plus
   `npm run build` passes 463 of 464 tests with 1 skipped. Without the build, 17
   workspace tests fail solely on `ViteManifestNotFoundException`. The wider
   Phase 3/4 debt is 142 tests that live only in the untracked baseline, and P5
   depends on none of them. Two gaps: `@playwright/test` is declared only in the
   dirty `package.json` and lockfile, and `busy_timeout` is set only in the dirty
   `config/database.php`.

## 5. Cross-task findings

- **X1 (MEDIUM).** SQLite lock handling depends on `busy_timeout`, which is 5000 in
  the working tree but `null` in the committed `config/database.php`. The
  two-process tests set `PRAGMA busy_timeout` themselves, which hides this. Under
  the committed config, a probe of `retry()` under a lock error returned a raw 500.
  Commit the setting with the baseline reconciliation, or handle lock errors in
  `retry()` and the dispatch stamp.
- **X2 (MEDIUM).** No runbook or wiring for the translation queue worker: queue
  name, timeout, `retry_after` and scheduled recovery. This is residuals 2 and 3
  combined.
- **X3 (INFO).** `CURRENT_STATE.md` does not record P5-004B or P5-006 as pending
  review. The task files carry the status.
- **X4 (INFO).** The browser run used a deterministic translation-worker double.
  The real HTTP provider boundary, validator and writer ran; no model did. This
  does not satisfy the P5-008 real provider/model gate, and the evidence file says
  so.

## 6. Evidence reproduced by the reviewer

**Working tree** (HEAD `dab7506` plus the dirty baseline):
- `php artisan test --compact tests/Feature/Translation tests/Unit/Translation`:
  173 passed, 673 assertions.
- `php artisan test --compact` (full): 606 tests, 605 passed, 1 skipped,
  2 warnings. Matches the pre-review.
- Three two-process concurrency tests, 3 consecutive runs: 3/3 passed each run,
  37 assertions.
- `vendor/bin/pint --test` on the 27 PHP files changed in `HEAD~4..HEAD`: passed.
- PHPStan: 0 errors.
- `worker/tests/test_translation.py`: 6 passed.

**Clean `git archive HEAD`** (vendor copied in, no dirty or untracked files):
- Translation suites: 156 of 173 passed without the frontend build. All 17 failures
  were `ViteManifestNotFoundException`.
- `TranslationWorkspaceTest` with `public/build` copied in: 39/39.
- Full suite with the build: 464 tests, 463 passed, 1 skipped.
- Playwright `verification/playwright.p5-006.config.js`: 18/18 passed in 2.0 min,
  including cross-user isolation. One page error (`showRenameModal` at
  `/transcriptions/1`); zero workspace errors.

**Mutation testing** (scratch copy, 23 mutations over the translation test files):
- Killed: M01 `fail()` token fence, M02 claim token, M03 recovery CAS token, M04
  writer token check, M05 retry CAS retryable predicate, M06 retry without reload,
  M07 `ensureDispatched` gate, M09 stamp removed, M10 unique-violation convergence,
  M12 to M17 authorization and retry gating, M19 request re-dispatch, M20 retry
  convergence, M21 and M21b post-claim failure contract, M22 writer timestamps.
- Survived: **M08** stamp token fence, **M11** concurrency-error guard, M18
  (equivalent).
- My harness first counted only assertion failures and not errored tests; the
  survivors were re-run with that corrected.

**Scratch probes** (6 tests, 29 assertions, scratch copy only, not committed):
S1 stamp failure after push, S2 zero segments, S3 transient persistence lock,
S5 lost queued message, S6 hostile text, S7 `retry()` under lock error. Each
confirmed the behavior described above.

**Not run:** the real provider or model (P5-008), MySQL or PostgreSQL, a Playwright
run in the working tree, and any browser other than Windows Chromium.

## 7. Can P5-006 be accepted and closed?

P5-006 is VERIFIED and may be closed as DONE by the Human Product Owner. Milestone
UX acceptance remains an HPO gate. Recommendation: close it together with, or
after, the P5-004B corrective re-review, which preserves the pre-UI-hardening-first
order the HPO set. No P5-006 rework is expected.

## 8. Must fix before P5-008

1. P4B-1, P4B-2, P4B-3 (the P5-004B corrective).
2. X1: commit the `busy_timeout` change, or handle lock errors in `retry()` and the
   stamp.
3. Job `$timeout` (below `retry_after`), a token-fenced `failed()` handler, and a
   documented `queue:work --queue=translation --timeout=...`.
4. Scheduled recovery of stale attempts, plus a story for dispatched-but-lost
   queued rows.
5. HPO decision on the `PERSISTENCE_FAILED` classification, logged in
   `DECISION_QUEUE.md`.

## 9. May wait until Phase 5 closure or later debt

- P4B-4, P4B-5, P4B-6, P4B-7, P4B-8.
- Zero-segment behavior (needs a product decision).
- P6-3, P6-4, P6-5, and the polling and clipboard notes.
- `showRenameModal` (Phase 4 debt).
- Committing `@playwright/test` to `package.json` and the lockfile.
- Non-Latin title slugs produce filenames like `-zh.txt`; the source exporter
  behaves the same way.

## 10. Corrective order

1. **P5-004B corrective:**
   - wrap the stamp in try/catch-log after a successful push, with a test that
     fails without it;
   - add a mutation-killing test for the stamp token fence;
   - add a test that an unrelated `QueryException` propagates;
   - optionally, in the same pass: move `refresh()` inside the failure contract
     and log exhausted convergence.
2. **Independent re-review of P5-004B.** A light review, since the corrective is
   confined to `TranslationDispatcher`, `TranslationOrchestrator` and tests. Then
   the HPO closes P5-004B and P5-006.
3. **Before P5-008 starts:** the operational pre-flight items 2 to 5 in section 8,
   as a small separate task or an explicit P5-008 entry condition so the work has
   an owner.
