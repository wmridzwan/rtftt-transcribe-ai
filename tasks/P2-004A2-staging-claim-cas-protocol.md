# P2-004A2 — Staging Claim CAS Protocol (SQLite-Safe)

## Status

READY

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

To be completed by the implementation owner.

### Files Changed

- None yet.

### Important Decisions

- None yet.

### Known Limitations

- None yet.

## Verification

Implementation owner must record the commands executed and results.

Example commands:

php artisan test --compact --filter=StagingClaim

php artisan test --compact --filter=CleanupStaging

vendor/bin/pint --dirty --format agent

Result:

PENDING

## Review

Review File:

None yet.

Review Status:

PENDING

## Completion

A task cannot move directly from IN_PROGRESS to DONE.

Required flow:

READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

The implementation owner must not mark their own work VERIFIED. VERIFIED
does not authorize closing P2-004A/P2-004A1 or lifting Option D — that is a
separate Human Product Owner action after this task is DONE.
