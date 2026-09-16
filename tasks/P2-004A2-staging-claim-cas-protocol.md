# P2-004A2 — Staging Claim CAS Protocol (SQLite-Safe)

## Status

DONE

## Ownership

Implementation Owner: OpenCode, per AGENTS.md and ADR-016.
Reviewer: Claude Code, per AGENTS.md.

## Authorized Phase

Phase 2, under ADR-016 (amends ADR-013). This task does not lift the Option D
deferral by itself — Option D remains in force until this task is
independently VERIFIED and closed as DONE. It does not authorize P2-004B+,
P2-005+, P2-007, or Phase 3.

## Objective

Replace the unproven `lockForUpdate()`-based claim protocol in
`app/Console/Commands/CleanupStaging.php` and `app/Actions/MediaIngestionService.php`
with the SQLite-safe compare-and-set protocol specified in
`reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md`, and prove it race-safe
using genuine independent processes.

## Context

Read before starting:

- `reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md` — the exact
  mechanism this task implements. Follow it precisely; do not redesign it.
  If you believe it is wrong, stop and report why instead of substituting a
  different mechanism.
- `reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md` — the
  original independent-review findings this design responds to.
- `tasks/P2-004A-staging-cleanup-command.md` and
  `tasks/P2-004A1-upload-attempt-lease-contract.md` — historical BLOCKED
  records; do not reopen or edit them except to add a one-line pointer to
  this task.
- `DECISIONS.md` ADR-016 and ADR-013.
- `app/Console/Commands/CleanupStaging.php`,
  `app/Actions/MediaIngestionService.php`, `app/Models/StagingClaim.php`,
  `database/migrations/2026_09_13_130000_create_staging_claims_table.php`.

## Scope

Implement only:

- A new migration adding `held_by` (string, default `'upload'`) and
  `cleanup_claimed_at` (nullable timestamp) to `staging_claims`.
- Update `StagingClaim` model casts/fillable accordingly.
- Update `MediaIngestionService`'s staging-claim creation/renewal to use the
  guarded `insertOrIgnore` / conditional `UPDATE` statements specified in the
  proposal (Step 1), including the retryable-failure response when
  `insertOrIgnore` returns 0 and the existing row is `held_by = 'cleanup'`.
- Update `CleanupStaging` to use the guarded conditional `UPDATE` claim
  transition (Step 2), move file deletion outside the transaction (Step 3),
  and implement the crash-recovery re-claim window (15 minutes) on rows
  stuck in `held_by = 'cleanup'`.
- New feature tests proving the race with genuine independent processes (see
  Testing below), plus unit/feature coverage for: normal same-attempt
  retry renewal, cleanup winning against an expired attempt, cleanup losing
  against an active attempt, ingestion losing against an in-progress cleanup
  claim, and crash-recovery re-claim after timeout.

## Out of Scope

Do not implement:

- Any change to the P2-002B/P2-003 upload-attempt identity, retry, or
  compensation contract beyond the claim-protocol statements above.
- A cleanup scheduler, queue, worker, Redis, or Horizon integration.
- Any change to `P2-004A`/`P2-004A1` task files beyond adding a one-line
  pointer to this task.
- FFprobe/FFmpeg, transcription, or any Phase 3+ behavior.
- Lifting the Option D deferral in `CURRENT_STATE.md`/`plan.md` — that is a
  Human Product Owner action after this task is independently VERIFIED.

Future ideas are not authorization.

## Dependencies

Requires:

- ADR-016 (already recorded).

## Acceptance Criteria

The task is complete when:

- [ ] No claim-state transition relies on `lockForUpdate()` or any lock
      SQLite silently drops; every transition is a single guarded
      `UPDATE`/`INSERT ... OR IGNORE` statement.
- [ ] File deletion in `CleanupStaging` happens outside any database
      transaction.
- [ ] Ingestion returns a controlled retryable failure (not a silent
      write into a directory pending deletion) when it loses the race to an
      in-progress cleanup claim.
- [ ] Cleanup defers (not an error) when it loses the race to an active
      upload claim.
- [ ] Crash-recovery re-claim after the 15-minute `held_by='cleanup'`
      timeout is implemented and tested.
- [ ] The race is proven with genuine independent OS processes (each with
      its own SQLite connection to the same database file), not a
      single-process or single-connection simulation. Document exactly how
      the test achieves genuine concurrency.
- [ ] Existing P2-002B/P2-003 focused and full regression suites pass
      unchanged in behavior.
- [ ] Required formatting (Pint) and static analysis (PHPStan) pass.
- [ ] No unrelated functionality is changed.
- [ ] `tasks/P2-004A-staging-cleanup-command.md` and
      `tasks/P2-004A1-upload-attempt-lease-contract.md` each get a one-line
      pointer added to this task (they remain BLOCKED/historical otherwise).

## Testing

Recommended approach (from the proposal): use Symfony's `Process` component
to spawn two real separate PHP CLI processes, each opening its own SQLite
connection to the same database file, coordinated by a filesystem rendezvous
(each process writes a "ready" sentinel file and polls for a "go" sentinel)
so both attempt their conditional statement at the same wall-clock moment. A
dedicated test-harness Artisan command, guarded to exist only in the testing
environment, is the cleanest way to give each spawned process a script to
run. A sequential/single-process simulation alone does not satisfy the
acceptance criteria.

## Implementation Notes

### Files Changed

- `database/migrations/2026_09_15_112331_add_held_by_and_cleanup_claimed_at_to_staging_claims_table.php` — new migration adding `held_by` (string, default 'upload') and `cleanup_claimed_at` (nullable timestamp)
- `app/Models/StagingClaim.php` — new fillable fields, casts, `heldByUpload()`, `heldByCleanup()`, `isCleanupTimedOut()` methods
- `database/factories/StagingClaimFactory.php` — new `held_by`, `cleanup_claimed_at` defaults, `heldByUpload()`, `heldByCleanup()`, `expired()` states
- `app/Actions/MediaIngestionService.php` — `stage()` uses `insertOrIgnore` for initial claim, guarded conditional UPDATE for retry renewal (with affected-row count check — retryable failure if 0), controlled retryable failure when `held_by = 'cleanup'`; `findActiveClaim()` filtered by `held_by = 'upload'`
- `app/Console/Commands/CleanupStaging.php` — guarded conditional UPDATE claim transition, crash-recovery re-claim composes reclaim + delete as one atomic decision (not two independent guarded statements), file deletion outside DB transaction, FOREIGN KEY violation handled via try-catch, `FilesystemAdapter` type hint
- `app/Console/Commands/StagingRaceWorker.php` — test harness command (testing env only) with filesystem rendezvous, explicit `readyFile` argument, renewal UPDATE affected-row count check (lost_race if 0)
- `tests/Feature/StagingClaimCasProtocolTest.php` — 8 tests: 2 genuine OS-process race tests (Symfony Process + shared file-based SQLite DB + env var injection) + 6 unit tests for all claim scenarios
- `tests/Feature/CleanupStagingCommandTest.php` — 14 tests covering all acceptance criteria; crash-recovery test proves final file deletion + claim removal

### Important Decisions

- Race tests use a shared file-based SQLite database (created per-test in `beforeEach`, cleaned up in `afterEach`) because in-memory SQLite databases are per-connection and child processes would each get their own empty DB
- The `DB_DATABASE` env var is passed to child processes via `Process::setEnv()` so they connect to the shared file DB
- The crash-recovery re-claim and deletion are one composed decision: when a stale `held_by='cleanup'` row is reclaimed, the code proceeds directly to re-verify + delete, skipping the Step 2 claim which cannot match `held_by='cleanup'`
- The word "Reclaimed" always appears in the command summary line (e.g. "Reclaimed: 0 crash-recovery re-claims") even when count is zero — assertions check the per-directory message pattern or the summary count

### Known Limitations

- None remaining. All 230 tests pass (229 passed, 1 pre-existing skip). Both genuine OS-process race tests pass on Windows.

## Verification

Commands executed:

```
vendor/bin/pest tests/Feature/CleanupStagingCommandTest.php
vendor/bin/pest tests/Feature/StagingClaimCasProtocolTest.php
vendor/bin/pest tests/Feature/IngestionCompensationContractTest.php
vendor/bin/pest
vendor/bin/pint --dirty --format agent
composer types:check
```

Results:

- CleanupStagingCommandTest: 14/14 passed (32 assertions)
- StagingClaimCasProtocolTest: 8/8 passed (26 assertions) — both genuine OS-process race tests pass
- IngestionCompensationContractTest: 11/11 passed (36 assertions)
- Full suite: 229/230 passed (688 assertions) — 1 pre-existing skip, 0 failures
- Pint: passed
- PHPStan: passed (0 errors)

## Review

Review File:

reviews/P2-004A2-independent-review.md

Review Status:

VERIFIED (round 2, 2026-09-15). All three round-1 findings independently
confirmed resolved: BLOCKER-1 (crash-recovery reclaim now composed with the
delete path — reproduced directly: a claim stuck in `held_by='cleanup'` for
20 minutes was fully deleted after one `media:cleanup-staging` run),
BLOCKER-2 (renewal `UPDATE`'s affected-row count now checked in both
`MediaIngestionService` and the `StagingRaceWorker` test harness), and
HIGH-1 (both genuine independent-OS-process race tests independently
re-run three times, 8/8 passed each time, no flakiness — the round-1
harness's in-memory-per-connection SQLite bug was fixed with a shared
file-based database and injected `DB_DATABASE`/`DB_CONNECTION` env vars).
Full regression suite, Pint, and PHPStan independently re-run clean
(229/230 passed, 1 pre-existing skip, 0 failures). All acceptance criteria
satisfied. See `reviews/P2-004A2-independent-review.md` for full findings,
including two non-blocking LOW notes.

Closed as DONE by Human Product Owner on 2026-09-15. This closure does not
authorize closing P2-004A/P2-004A1 or lifting Option D (ADR-013) — those
are separate Human Product Owner actions.

## Completion

Required flow:

READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

Closed as DONE by Human Product Owner on 2026-09-15 after independent
VERIFIED verdict (round 2). This closure does not authorize closing
P2-004A/P2-004A1 or lifting Option D — those are separate Human Product
Owner actions.
