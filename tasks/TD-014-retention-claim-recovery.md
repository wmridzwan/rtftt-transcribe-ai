# TD-014 — Retention Staging-Claim Crash-Recovery Gap

## Status

BACKLOG (contract authored in the pre-Linux remediation batch; NOT READY, NOT AUTHORIZED FOR IMPLEMENTATION — requires explicit HPO READY promotion and execution authorization)

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: UNASSIGNED (independent review required per the State-to-Action Contract)

## Authorized Phase

Phase 7 (pre-P7-012 follow-up for G-09 confidence; does not block P7-011 DONE)

## Objective

Close the `RetentionPurge` staging-claim crash-recovery gap: a purge process killed (OOM/SIGKILL/host restart) after acquiring a staging claim (`held_by` upload → cleanup) but before release/completion strands the row at `held_by = 'cleanup'` permanently, invisible to later purge runs. Implement a bounded reclaim (or an HPO-decided explicit operator procedure) and prove it — do not silently close TD-014.

## Context

- `docs/TECHNICAL_DEBT_REGISTER.md` TD-014 entry (OPEN, LOW, pre-P7-012 follow-up)
- `reviews/P7-011-INDEPENDENT-REVIEW-CYCLE2.md` §E.1/§I.3 (origin observation)
- P2-004A2 claim semantics: `staging_claims` with `held_by`/`cleanup_claimed_at`, 15-minute stale-claim reclaim in `CleanupStaging` (`app/Console/Commands/CleanupStaging.php:77-85`) — the convention to mirror
- P7-011 retention cleanup: `app/Retention/RetentionPurge.php:177-263` (staging path), failure-path release (`held_by` back to upload) covers caught failures only, not uncaught termination
- `tasks/P7-011-retention-cleanup.md` §6.10 (clocks), §8.4 (tombstone boundary)

## Scope

Implement only:

- stale `held_by = 'cleanup'` reclaim for `RetentionPurge`-acquired staging claims (bounded age convention mirroring the 15-minute stale-claim rule, or an HPO-decided alternative recorded in DECISION_QUEUE.md)
- reclaim safety: never reclaim a claim held by a live purge run (age-gated + process-liveness discipline consistent with existing claim fencing)
- regression tests: stranded-row reclamation, live-run non-reclamation, audit-ledger accuracy for reclaimed rows
- docs touch-up: retention policy + TD-014 register entry updated to CLOSED with evidence links

## Out of Scope

Do not implement:

- changes to P2-004A2 CAS semantics or the `CleanupStaging` reclaim window
- tombstone/purge eligibility changes (P7-011 §8.4 boundary intact)
- backup/restore, capacity, or P7-012 scope
- TD-008 or any other debt item

Future ideas are not authorization.

## Dependencies

Requires:

- P7-011 DONE (satisfied 2026-09-26, `DECISION-P7-011-CLOSURE-001`) — consumes its claim/audit interfaces without redefining them
- Blocks: nothing structurally; gates G-09 confidence at P7-012 (P7-012 must record TD-014 CLOSED or an explicit HPO carry-forward)

## Acceptance Criteria

The task is complete when:

- [ ] the stuck `held_by = 'cleanup'` failure mode is reproduced in a test (kill-after-claim simulation) and then reclaimed on a later run
- [ ] live purge runs are never reclaimed mid-flight (age + fencing proof, concurrent-run test)
- [ ] reclaimed rows appear accurately in the `retention_purge_audits` ledger (no silent rows, no false success)
- [ ] caught-failure release behavior (existing) is unregressed — full Retention suite green
- [ ] Pint clean; PHPStan at the repository-required level
- [ ] independent review returns VERIFIED with no BLOCKER/HIGH/MEDIUM
- [ ] TD-014 register entry updated to CLOSED with evidence links

## Implementation Notes

To be completed by the implementation owner.

### Files Changed

- None yet.

### Important Decisions

- None yet (reclaim window + procedure shape decided at READY/implementation under HPO if they exceed this contract).

### Known Limitations

- None yet.

## Verification

Implementation owner must record the commands executed and results.

Example commands:

php artisan test --compact --filter=Retention
vendor/bin/pint --dirty --format agent
composer types:check

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

The implementation owner must not mark their own work VERIFIED. READY promotion and DONE closure are HPO acts.
