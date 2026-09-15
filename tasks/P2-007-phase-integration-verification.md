# P2-007 — Phase Integration Verification

## Status

BACKLOG

This task does not exist as an authorized, runnable task yet. It is drafted
here so its scope is concrete before the Human Product Owner promotes it to
READY. Promoting it to READY does not require P2-004A/P2-004A1 to be
resolved first (see Dependencies).

## Ownership

Implementation Owner: UNASSIGNED (OpenCode, per AGENTS.md, once READY)
Reviewer: Claude Code, per AGENTS.md

## Authorized Phase

Phase 2. This task verifies the Phase 2 gate; it does not extend Phase 2
scope and does not authorize Phase 3.

## Objective

Prove, end-to-end and with reproducible evidence, that the Phase 2 gate is
met with the current accepted scope:

> User can safely upload supported media.

This is a verification task, not a feature task. It exercises the already
DONE contracts (P2-001, P2-001A, P2-002, P2-002A/B/C, P2-003, P2-005) together
as one integrated path, and records the result as the durable evidence that
closes out Phase 2 integration — separately from the individual task
VERIFIED/DONE records that already exist for each of them.

## Context

- `plan.md`, `CURRENT_STATE.md` — Phase 2 is ACCEPTED under ADR-014 with
  P2-004A/P2-004A1 BLOCKED and deferred under ADR-013 (Option D).
- `config/media.php` — accepted media matrix and the exact 500 MiB
  (524,288,000-byte) per-file limit.
- `tasks/P2-003-implement-real-upload-ingestion-workflow.md` — the verified
  upload/ingestion workflow this task exercises end-to-end.
- `reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md` — why
  automated cleanup is out of scope for this gate.

## Scope

Verify only, across the already-accepted Phase 2 contracts:

- A supported-format file (one example each of the accepted matrix in
  `config/media.php`: MP3, WAV, M4A, AAC, FLAC, OGG, MP4, MOV, WEBM) uploads
  successfully through the real UI (`resources/views/media/upload.blade.php`)
  and lands on Media Detail.
- An unsupported format, an oversized file (over 500 MiB), and a corrupt/
  truncated file each fail with the expected user-facing validation error and
  no partial `MediaFile` row or orphaned durable (non-staging) artifact.
- A same-attempt retry (client resubmits the same `upload_attempt_id` after a
  lost response) resolves idempotently per the P2-002B/P2-003 contract.
- Checksum, MIME, and extension validation are exercised together on at
  least one deliberately mismatched file (correct extension, wrong content).
- The private-disk boundary holds: uploaded files are not reachable via the
  public disk/URL.
- Where FFprobe is available, metadata probing (P2-005) runs and its result
  is visible on Media Detail; where it is not available, the existing
  graceful-skip behavior is confirmed rather than a hard failure.
- The full regression suite, Pint, and PHPStan pass with no new findings
  attributable to this task (it should not need to change application code;
  if it does, that change is itself in scope for review).
- The result is written up as a durable verification report (this task's
  Implementation Notes plus a `reviews/` artifact), explicitly stating
  whether the phase gate is met.

## Out of Scope

Do not implement:

- Any fix to P2-004A/P2-004A1 (tracked separately; see
  DECISION-P2-CONCURRENCY-002 in `DECISION_QUEUE.md`).
- Any new feature, UI change, or contract change. If verification uncovers a
  real defect, record it as a finding and open a separate follow-up task —
  do not fix it inline under this task.
- Any Phase 3 (FFmpeg processing beyond the existing minimal FFprobe metadata
  read), Phase 4/5 (transcription/worker), or P2-007-adjacent scope creep.
- Load/performance/concurrency testing beyond the single-request scenarios
  above (that is P2-004A/P2-004A1's concern, not this task's).

Future ideas are not authorization.

## Dependencies

Requires:

- P2-001, P2-001A, P2-002, P2-002A, P2-002B, P2-002C, P2-003, P2-005 (all
  DONE already).

Does not require:

- P2-004A or P2-004A1. This task must explicitly verify and state that the
  Phase 2 gate holds under the accepted ADR-013/ADR-014 interim state (no
  automated cleanup), not silently assume it.

## Acceptance Criteria

The task is complete when:

- [ ] Each supported format in the accepted matrix uploads and reaches Media
      Detail successfully, with evidence (test output and/or a recorded
      manual pass) for each.
- [ ] Each rejection case (unsupported format, oversized, corrupt/truncated,
      mismatched extension/content) fails safely with no partial state.
- [ ] Same-attempt retry idempotency is demonstrated, not just asserted.
- [ ] Private-disk isolation is demonstrated (not just configured).
- [ ] FFprobe-present and FFprobe-absent behavior are both demonstrated.
- [ ] A durable verification report exists under `reviews/` stating plainly
      whether "user can safely upload supported media" is met, and listing
      any findings that were out of scope to fix here.
- [ ] Full regression suite, Pint, and PHPStan pass.
- [ ] No unrelated functionality is changed.

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

BACKLOG -> READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

The Human Product Owner must promote this task from BACKLOG to READY before
any implementation owner may begin work. The implementation owner must not
mark their own work VERIFIED.
