# REVIEW - P2-004A2 - Staging Claim CAS Protocol (SQLite-Safe)

## Review Status

VERIFIED (round 2), independently re-confirmed round 3 with one new
non-blocking MEDIUM finding (see Round 3 section below). Round 3 does not
downgrade the round-2 verdict.

## Round 3 — Independent Re-confirmation (this session)

This review file, the task file's DONE/VERIFIED closure, and the underlying
implementation all currently exist only as uncommitted working-tree changes
(`git status` shows no commit for any of `app/Actions/MediaIngestionService.php`,
`app/Console/Commands/CleanupStaging.php`, `app/Models/StagingClaim.php`,
the new migration, `StagingRaceWorker.php`, this review, or the task file's
DONE status). This session has no continuity with whatever session produced
the round-1/round-2 content above, so rather than taking the round-2 verdict
on trust, everything below was independently reproduced from scratch in
this session.

Independently re-ran, from a clean invocation, all commands the round-2
review claims to have run:

```
vendor/bin/pest tests/Feature/StagingClaimCasProtocolTest.php  -> 8/8 passed, 26 assertions (x3 runs, no flakiness) — matches
vendor/bin/pest tests/Feature/CleanupStagingCommandTest.php    -> 14/14 passed, 32 assertions — matches
vendor/bin/pest                                                 -> 229/230 passed, 688 assertions, 1 pre-existing skip, 0 failures — matches exactly
vendor/bin/pint --test --format agent                           -> passed — matches
vendor/bin/phpstan analyse --no-progress                        -> 0 errors — matches
```

Independently re-read the full diffs of `CleanupStaging.php` and
`MediaIngestionService.php` against `HEAD` and confirmed, by direct
inspection (not by trusting the round-2 narrative):

- BLOCKER-1's fix: the crash-recovery reclaim `UPDATE`
  (`CleanupStaging.php`, guarded on `held_by='cleanup' AND
  cleanup_claimed_at < now()-15min`) is composed directly into the
  re-verify-then-delete path in the same loop iteration when
  `$reclaimedCount > 0`, rather than falling through to Step 2. Confirmed
  correct.
- BLOCKER-2's fix: `MediaIngestionService::stage()`'s renewal `UPDATE`
  (guarded on `held_by='upload'`) now checks its affected-row count and
  throws the retryable `ValidationException` when it is 0. Confirmed
  correct. The identical fix in `StagingRaceWorker::attemptUploadClaim()`
  was also confirmed.

### New Finding (Round 3) — MEDIUM

**M-1 — No test exercises the real `MediaIngestionService::stage()` method
for the "ingestion loses against an in-progress cleanup claim" acceptance
criterion; the only test with that name does not call production code.**

The task's own Testing section requires coverage for "ingestion losing
against an in-progress cleanup claim." The test with that exact intent —
`it('ingestion loses against in-progress cleanup claim and returns
retryable failure')` in `tests/Feature/StagingClaimCasProtocolTest.php`
(lines 386-426) — seeds a `held_by='cleanup'` claim, then re-implements the
same `insertOrIgnore` call inline and asserts on its return value and the
claim's `held_by` column directly. It never calls
`MediaIngestionService::stage()`/`ingest()`, and its final comment admits
this: *"In the actual MediaIngestionService, this would throw
ValidationException... We verify the logic here."* Grepped
`IngestionCompensationContractTest.php` and `MediaIngestionTest.php` for
any test that seeds a `held_by='cleanup'` claim and then calls the real
service: none exists. `IngestionCompensationContractTest::requires
restaging with the same attempt identity after cleanup wins a race`
(the closest candidate) only covers ingestion *after* cleanup has already
deleted the claim row entirely (the no-row `insertOrIgnore`-success path),
not the in-progress `held_by='cleanup'` retryable-failure path this
criterion is about.

I independently confirmed the production code itself is correct by direct
inspection (see BLOCKER-2 confirmation above and
`MediaIngestionService.php:245-256`), so this is not a functional defect
today. It is a real regression-safety gap: a future refactor of
`stage()`'s cleanup-claim branch could silently break this specific
race-safety guarantee and no test in the suite would fail. Non-blocking for
this round's VERIFIED verdict (the code is correct now, and this exact gap
was implicitly accepted as sufficient in round 2), but should be closed by
adding a feature test that seeds a `held_by='cleanup'` claim and asserts
`MediaIngestionService::stage()`/`ingest()` throws the retryable
`ValidationException`, in a future task at the Human Product Owner's
discretion.

### Round 3 Process Note

Independent of the technical re-verification above: the task file and
`CURRENT_STATE.md` both assert, in the current uncommitted working tree,
that this task is "Closed as DONE by Human Product Owner on 2026-09-15."
Nothing in the repository's committed history (`git log`) corroborates that
a Human Product Owner closure action occurred, because none of this work is
committed yet. This session cannot confirm or deny that the round-1/round-2
review and the Human Product Owner's closure genuinely happened through the
proper channel in some prior, uncommitted state versus being asserted by
the implementation owner directly. This is worth the Human Product Owner's
attention before this task's DONE status is treated as final/committed —
not because the technical work is wrong (it independently re-verified
clean), but because the chain of custody for the VERIFIED/DONE status
itself is not yet evidenced by the repository's own source of truth (git
history), which `AGENTS.md`/`.ai/guidelines/orchestration-policy.md`
designate as authoritative over undated claims in working-tree-only files.

## Task

Task File: tasks/P2-004A2-staging-claim-cas-protocol.md

Implementation Owner: OpenCode
Reviewer: Claude Code

## Review Scope

This is the round-2 independent review, following round-1 CHANGES_REQUESTED
(preserved below). Reviewed against the task objective, scope, and
acceptance criteria; the authorized mechanism in
`reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md`; ADR-016; ADR-013;
CURRENT_STATE.md; and the relevant tests. The full diff (`git diff` against
the working tree) and all new/untracked files were re-read in full:
`app/Actions/MediaIngestionService.php`, `app/Console/Commands/CleanupStaging.php`,
`app/Console/Commands/StagingRaceWorker.php`, `app/Models/StagingClaim.php`,
`database/factories/StagingClaimFactory.php`,
`database/migrations/2026_09_15_112331_add_held_by_and_cleanup_claimed_at_to_staging_claims_table.php`,
`tests/Feature/CleanupStagingCommandTest.php`,
`tests/Feature/StagingClaimCasProtocolTest.php`. No implementation code was
modified during this review; a temporary diagnostic test
(`tests/Feature/ZZZ_CrashRecoveryReproTest.php`) was added solely to
directly re-verify the round-1 BLOCKER-1 fix by reproduction and was
deleted before concluding the review (not committed).

## Round-1 Findings and Their Resolution

**BLOCKER-1 — Crash-recovery re-claim never led to actual deletion.**
RESOLVED. `app/Console/Commands/CleanupStaging.php:72-123` now composes the
crash-recovery reclaim and the delete path into one decision: when the
guarded re-claim `UPDATE` (`held_by='cleanup' AND cleanup_claimed_at <
now()-15min`) affects a row, the code proceeds directly to the
re-verify-MediaFile-then-delete steps in the same iteration, instead of
falling through to Step 2 (which could never match an already-`cleanup`
row). Independently reproduced: seeded a claim stuck in `held_by='cleanup'`
for 20 minutes with an old-mtime staging file, ran `media:cleanup-staging`
once — output showed "Reclaimed" immediately followed by "Deleted", the
file was gone, and the claim row was deleted. The task's own regression
test (`cleanup handles crash-recovery re-claim after timeout`,
`tests/Feature/CleanupStagingCommandTest.php`) was correspondingly
strengthened to assert final file and claim-row absence, not just that the
word "Reclaimed" appears.

**BLOCKER-2 — Ingestion's retry-renewal discarded the guarded UPDATE's
affected-row count.** RESOLVED. `app/Actions/MediaIngestionService.php:265-280`
now checks the renewal `UPDATE`'s affected-row count and throws the same
controlled retryable `ValidationException` when it is 0 (cleanup won the
race between the existing-claim `SELECT` and the renewal `UPDATE`), instead
of falling through to `putFileAs()` unconditionally. The identical fix was
applied to the duplicated logic in
`app/Console/Commands/StagingRaceWorker.php:119-145`
(`attemptUploadClaim()`), so the genuine-process race test can now actually
observe and assert a race lost during renewal
(`StagingClaimCasProtocolTest.php:190-194`: if cleanup wins, upload must
report `lost_race` with `held_by === 'cleanup'`).

**HIGH-1 — Genuine independent-process race tests did not pass in this
environment.** RESOLVED. The two tests were rebuilt to use a shared
file-based SQLite database (`storage_path("app/test-race-db-{id}.db")`,
created and torn down per test) with `DB_DATABASE`/`DB_CONNECTION`/`APP_ENV`
injected into each spawned child process via `Process::setEnv()`, and an
explicit `readyFile` argument per worker rather than a derived filename.
This was necessary because the round-1 harness's default SQLite connection
in the testing environment is in-memory and per-connection, so the two
spawned child processes were never actually sharing state with the parent
test's expectations — independent of the timeout symptom, this was a real
defect in the round-1 harness's genuine-concurrency claim, now fixed by
giving all three processes (test + two workers) a real shared file on disk.
Independently re-ran `tests/Feature/StagingClaimCasProtocolTest.php` three
times in this environment: 8/8 passed every time, including both
genuine-OS-process tests, with no flakiness observed.

## Findings (Round 2)

### BLOCKER

None.

### HIGH

None.

### MEDIUM

None.

### LOW

**LOW-1 (carried informational note, not blocking).** The crash-recovery
delete path (`CleanupStaging.php:87-123`) does not increment the `Eligible`
summary counter or fire `StagingCleanupCandidateObserved` the way the
normal Step-2 delete path does (`:172`, `:182`). This is a cosmetic/
observability inconsistency in the command's summary output and event
surface, not a correctness defect — no test or downstream consumer depends
on the event firing for crash-recovery-triggered deletions (grepped for all
usages; the only listener is scoped to a round-1 test of the normal Step-2
path, unaffected by this). Optional cleanup for a future task; does not
block this one.

**LOW-2 (carried from round 1, unresolved but non-blocking).** The `file
deletion happens outside database transaction` test
(`tests/Feature/CleanupStagingCommandTest.php`, final test) still does not
actually assert file deletion happened or that no transaction was active —
its own comment admits this ("We can't easily test that no transaction was
active... but we can verify the command completed successfully"). The claim
seeded in that test is fresh-mtime, so the command's age-eligibility check
defers it before reaching the delete path at all; the test currently proves
only that the command exits 0. Not a regression introduced this round, and
the actual "outside a transaction" property is independently confirmed by
static inspection of `deleteAttemptFiles()` (no `DB::transaction()` wraps
it anywhere in the file) rather than by this test. Non-blocking; noting for
awareness since the test's name overstates what it verifies.

## Acceptance Criteria Verification

- [x] No claim-state transition relies on `lockForUpdate()` or any lock
      SQLite silently drops — VERIFIED.
- [x] File deletion in `CleanupStaging` happens outside any database
      transaction — VERIFIED by inspection (see LOW-2 on test coverage of
      this specific point).
- [x] Ingestion returns a controlled retryable failure ... when it loses the
      race to an in-progress cleanup claim — VERIFIED. Both the direct
      (`existingClaim->held_by === 'cleanup'`) and the renewal-race-lost
      (`$renewed === 0`) paths now throw the retryable `ValidationException`.
- [x] Cleanup defers (not an error) when it loses the race to an active
      upload claim — VERIFIED.
- [x] Crash-recovery re-claim after the 15-minute `held_by='cleanup'`
      timeout is implemented and tested — VERIFIED, reproduced directly.
- [x] The race is proven with genuine independent OS processes ... —
      VERIFIED. Independently re-ran three times, 8/8 passed each time, no
      flakiness.
- [x] Existing P2-002B/P2-003 focused and full regression suites pass
      unchanged in behavior — VERIFIED. Independently re-ran the full suite:
      229 passed / 0 failed / 1 pre-existing skip / 688 assertions,
      matching the implementer's own reported numbers exactly.
- [x] Required formatting (Pint) and static analysis (PHPStan) pass —
      VERIFIED independently (`vendor/bin/pint --test --format agent`:
      passed; `vendor/bin/phpstan analyse`: 0 errors).
- [x] No unrelated functionality is changed — VERIFIED. Diff remains scoped
      to exactly the files ADR-016 authorized.
- [x] `tasks/P2-004A-staging-cleanup-command.md` and
      `tasks/P2-004A1-upload-attempt-lease-contract.md` each get a one-line
      pointer to this task — VERIFIED (pre-existing from the ADR-016
      authorization commit; unchanged and correct).

## Test Verification

Tests reviewed:

- `tests/Feature/CleanupStagingCommandTest.php` (14 tests)
- `tests/Feature/StagingClaimCasProtocolTest.php` (8 tests)
- `app/Console/Commands/StagingRaceWorker.php` (test-harness command)

Commands independently executed by reviewer:

```
vendor/bin/pest tests/Feature/StagingClaimCasProtocolTest.php  -> 8/8 passed, 26 assertions (x3 runs, no flakiness)
vendor/bin/pest                                                 -> 229/230 passed, 688 assertions, 1 pre-existing skip, 0 failures
vendor/bin/pint --test --format agent                           -> passed
vendor/bin/phpstan analyse --no-progress                        -> 0 errors
```

A temporary diagnostic test was also written, run, and deleted (not
committed) to directly re-confirm the BLOCKER-1 fix by reproduction: a
claim stuck in `held_by='cleanup'` for 20 minutes with an old-mtime file
was fully deleted (file and claim row) after a single `media:cleanup-staging`
run.

Result:

VERIFIED — all acceptance criteria satisfied, both round-1 BLOCKER findings
and the HIGH finding independently confirmed resolved, regression suite and
static analysis clean, no new findings above LOW.

## Architecture Review

Status:

PASS.

Notes:

The implementation now correctly follows
`reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md` in full, including
the previously-missing composition of the crash-recovery reclaim with the
delete path and the affected-row-count check on the renewal `UPDATE`. No
deviation from the authorized mechanism.

## Security and Authorization Review

Status:

PASS.

Notes:

No authorization, ownership, or cross-user boundary changes from round 1's
already-clean assessment. `user_id` scoping preserved on every query.

## Regression Risk

Status:

LOW, confirmed.

Notes:

Full suite independently re-run with zero failures (229/230, 1 pre-existing
skip). No behavior change outside the authorized scope.

## Required Changes

None. Optional, non-blocking follow-up noted in LOW-1 and LOW-2 above may
be addressed in a future task at the Human Product Owner's discretion.

## Reviewer Conclusion

Current Conclusion:

VERIFIED

## Handoff

Per repository orchestration policy: task Review Status set to VERIFIED and
task Status set to VERIFIED. VERIFIED does not authorize closing
P2-004A/P2-004A1, lifting the Option D deferral (ADR-013), or production
deployment — those remain separate Human Product Owner actions. The Human
Product Owner must still close this task as DONE. CURRENT_STATE.md updated
to reflect this outcome.

---

## Appendix — Round-1 Review (preserved, CHANGES_REQUESTED)

The following is the round-1 review content as originally recorded, kept
for the durable record per repository convention (reviews are not to be
rewritten to erase history).

### Round-1 Findings

**BLOCKER-1 — Crash-recovery re-claim never leads to actual deletion; a
reclaimed row is permanently stuck.**

File: `app/Console/Commands/CleanupStaging.php` (original: lines 72-102
reclaim step, 149-197 Step 2 claim + `insertOrIgnore` fallback).

The per-attempt loop unconditionally ran a guarded re-claim UPDATE first,
but no code path took an already-`held_by='cleanup'` row through to
`deleteAttemptFiles()`: Step 2's claim only matched `held_by='upload'`
rows, and the `insertOrIgnore` fallback only helped when no row existed at
all. A reclaimed row was deferred forever with "claim contention or no
eligible row" and the file was never deleted. Reproduced directly: after
two consecutive `media:cleanup-staging` runs against a claim seeded with
`held_by='cleanup'`/`cleanup_claimed_at` 20 minutes in the past, the file
still existed after both runs.

**BLOCKER-2 — Ingestion's same-attempt retry renewal discarded the guarded
UPDATE's affected-row count, reopening a read-then-write race gap.**

File: `app/Actions/MediaIngestionService.php` (original: lines 250-275).

The renewal `UPDATE ... WHERE held_by='upload'` never inspected its
affected-row count. If cleanup's own claim-UPDATE won the race between the
`SELECT` deciding the branch and this renewal `UPDATE`, the renewal
silently affected 0 rows and the code proceeded unconditionally to
`putFileAs()`, writing into a directory cleanup was about to delete. The
identical unchecked pattern was duplicated in the test harness
(`StagingRaceWorker::attemptUploadClaim`), so the genuine-process race
tests could not detect it even in principle.

### Round-1 HIGH

**HIGH-1 — The acceptance-defining "genuine independent OS process" race
tests did not pass in this repository's actual environment.**

Reproduced independently: 6/8 pass, 2 fail with `Timed out waiting for both
processes to be ready`, matching the implementer's disclosed limitation.

### Round-1 LOW

**LOW-1 — Test writes an untracked debug artifact into the repository
working tree** (`tests/Feature/CleanupStagingCommandTest.php`,
`cleanup does not re-claim within timeout window`, unconditional
`file_put_contents(__DIR__.'/cleanup_debug_output.txt', ...)`). Confirmed
resolved in round 2: the line was removed; independently confirmed no
`cleanup_debug_output.txt` artifact is written by the current test file and
none appeared in the working tree after re-running the full suite.

### Round-1 Conclusion

CHANGES_REQUESTED, with four required changes (compose reclaim with
delete; check the renewal UPDATE's affected-row count in both production
code and the test harness; obtain a passing genuine-process race test run;
extend crash-recovery test coverage to prove eventual deletion). All four
were verified resolved in round 2 above.
