# P7-002 — Production Data Store Finalization + Migration

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-002-CLOSURE-001`) on the independent VERIFIED verdict
below, under environmental exception
`DECISION-P7-002-PG-ENV-DISPOSITION-001` (Option A; AC1/AC2/AC5/AC6
pg-halves NOT PASS, carried forward pre-P7-012, consumed by the P7-007
drill and P7-009 final run).

VERIFIED — independent review (`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`
§C, §M) 2026-09-26: AC1–AC4/AC7–AC9 independently reproduced PASS on
sqlite/machinery; AC1/AC2/AC5/AC6 recorded BLOCKED-ENVIRONMENT for their
PostgreSQL halves (genuine target-host gap — no PostgreSQL server,
binaries, or Docker exist in this environment; no code defect — mirrors
`DECISION-P7-001-AC8-DISPOSITION-001`/`DECISION-P7-008-AC2-DISPOSITION-001`).
No BLOCKER/HIGH finding; one MEDIUM/INFO-leaning cross-cutting TD-008
frequency note and one LOW `CURRENT_STATE.md` lag note, neither
attributable to this task's diff (`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`
§L). Not DONE; DONE requires HPO closure, which should also issue an
environmental disposition for the AC1/AC2/AC5/AC6 pg-halves analogous to
the P7-001/P7-008 precedent before this task closes (§E, §O of the review).

History preserved: REVIEW (builder report `reviews/P7-002-BUILDER-REPORT.md`)
→ VERIFIED with AC1/AC2/AC5/AC6 pg-halves flagged BLOCKED-ENVIRONMENT
(`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`, 2026-09-26).

History preserved: BACKLOG — CONTRACT_AUTHORED → reconciled → READY (HPO
`DECISION-PHASE7-WAVE3A-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-002-BUILDER-REPORT.md`;
live-pg checks recorded BLOCKED-ENVIRONMENT with mechanism +
procedure, per contract). Not VERIFIED, not DONE. Independent review
returns VERIFIED or CHANGES_REQUESTED (max 3 cycles, then BLOCKED).

IN_PROGRESS — implementation begun 2026-09-26 under
`DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`.

READY — promoted by the HPO on 2026-09-26
(`DECISION-PHASE7-WAVE3A-READY-PROMOTION-001`) after reconciliation
against final Wave 2 DONE interfaces (P7-001 DONE
`DECISION-P7-001-CLOSURE-001` under `DECISION-P7-001-AC8-DISPOSITION-001`;
P7-007 DONE `DECISION-P7-007-CLOSURE-001`; Wave 2 CLOSED
`DECISION-PHASE7-WAVE2-CLOSURE-001`).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26, Wave 3
preparation while Wave 2 was under independent review; planning only,
no implementation) → reconciled (no stale assumption found: P7-001
registry/`violations()`/record-target-evidence interfaces,
P7-007 `backup:run --driver=` dormant-pgsql + `backup:pre-migrate`
goldens, P7-008 inventory/runbook hooks all verified against the DONE
tree; D7-01/B binding; drill stays downstream) → READY.
Not authorized for implementation; READY != EXECUTION AUTHORIZATION.
No code written under this contract.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-002
- Canonical title: Production Data Store Finalization + Migration
- Phase: 7 — Production Hardening
- Proposed state: BACKLOG (CONTRACT_AUTHORED; awaiting Wave 2 closure +
  HPO READY promotion)

## 2. Objective

Deliver the D7-01/B production datastore: migrate the Phase 1–6 system
from the SQLite development posture to self-hosted PostgreSQL with
migration-safety evidence, data preservation, rollback boundaries, and
dual-driver verification — without breaking the frozen Phase 6
revision/history invariants or the revision-aware export behavior.

## 3. Why It Exists

All development ran on SQLite (`PHASE7-PLANNING.md` §A). SQLite
concurrency/write-lock limits are observed (ADR-013/Option D); the
production concurrency story, pg-native backup tooling (D7-07), and the
G-02 gate all require the D7-01 decision implemented. P7-007's executed
restore drill and P7-009's final capacity run both wait on this task.

## 4. Binding D7 Decisions / ADRs

- D7-01 = OPTION B (self-hosted PostgreSQL) — `DECISION-PHASE7-OWNER-DECISIONS-001` / ADR-026.
- D7-07 = OPTION A (daily + drill) — the pg-native backup mechanism activates here.
- ADR-013/ADR-014/ADR-016 (Option D): unchanged; this migration does not
  lift, reinterpret, or close the staging-cleanup deferral (owned by P7-011 + HPO-07).
- ADR-017/018/019/024/025, Phase 6 frozen semantics
  (`PHASE6-EDITING-DOMAIN-CONTRACT.md` §0), Phase 5 translation identity:
  consumed unchanged.

## 5. Dependencies

- Hard prerequisites (Wave 2): P7-001 DONE (env registry mechanism that
  carries the Postgres-target variables advisory→required flip) and
  P7-007-foundation DONE (backup-before-migrate mechanism + hooks).
  Classification: `REQUIRES_WAVE2_DONE` (P7-001, P7-007).
- Already DONE, consumed by reference: P7-008 (migration-safety/rollback
  tooling, `deploy/migrations-inventory.json`, runbook hooks "Backup
  before migrate"), P7-003 (queue posture; no queue change here),
  P7-005 (logging/diagnostics conventions).
- Soft: P7-006 (scan-verdict columns migrate as ordinary nullable columns).
- Downstream: P7-007 final drill (requires P7-002 DONE), P7-009 final run
  (requires P7-002 DONE), P7-012 gate G-02.
- P7-011: independent of this task's internals (retention works on either
  driver); sequencing with P7-011 is a Wave 3 schedule matter, not a
  correctness dependency.

## 6. In Scope

1. Postgres-target environment variables carried as P7-001-registered
   keys; the advisory→required flip owned here as a contract change
   recorded against P7-001's registry (no fork of the registry).
2. New additive migrations only (never edit historical migrations);
   pg-compatibility audit of every existing migration (SQLite-isms:
   type coercion, partial indexes, raw statements).
3. Dual-driver verification: full suite green on SQLite AND on
   PostgreSQL (production-version target).
4. Data-migration procedure with ordering (schema → reference data →
   media-linked rows → revision graph → translations), integrity
   reconciliation (row counts, checksums, revision-graph validation),
   and failure/partial-migration handling (resume or rollback, never
   half-migrated production).
5. Rollback plan executed through P7-008's rollback procedure
   (P7-008 owns mechanics; P7-002 owns data mechanics + decision point).
6. `backup:pre-migrate` hook exercised before the migration (P7-007 hook).
7. P7-007 pg-native mechanism activation (hand off the dormant path;
   P7-007 owns the mechanism, this task proves the datastore it runs on).
8. Regression: full Phase 6 editing/revision/export suite on PostgreSQL
   (revision-aware export AC1–AC11 equivalent), translation persistence,
   queue claim fencing under the production driver.

## 7. Explicit Non-Scope

- Redefining Phase 6 revision/history semantics, translation identity,
  or queue retry policy.
- Object storage (deferred per D7-03, not rejected); Horizon (excluded
  per D7-02); multi-tenant proof (excluded per D7-08).
- The executed restore drill itself (P7-007 Wave 3 follow-on scope).
- P7-009 capacity measurements (consumes this task's output).
- Retention purge (P7-011); deployment topology changes (P7-008).

## 8. Architecture / Domain Contract

- Single production driver: `pgsql` (self-hosted). SQLite remains the
  development/test driver; dual-driver CI keeps both honest.
- Migration content lives in new timestamped migrations + a documented
  data-migration procedure (not an artisan one-liner); the procedure is
  idempotent-aware (safe re-probe, explicit resume/rollback states).
- Revision-graph integrity is a first-class migration check:
  `transcript_revisions` / `transcript_revision_segments` /
  `transcriptions.active_revision_id` / `(transcription_id, version)`
  uniqueness / `parent_revision_id` ancestry must reconcile exactly.
- The P7-001 registry remains the single env-key owner; this task
  contributes key-shape deltas through its extension point.

## 9. Detailed Implementation Requirements

1. Audit all migrations for pg compatibility; record the audit.
2. Add `pgsql` connection config (env-driven, secret-safe, P7-001
   registered shapes); default connection unchanged until cutover.
3. New migrations only where schema needs pg-specific support.
4. Data-migration procedure doc + rehearsal on a staging copy.
5. Dual-driver full-suite runs (SQLite + pgsql) with retained evidence.
6. Cutover checklist + rollback decision point wired to P7-008's
   procedure and P7-007's pre-migrate hook.
7. Activate the P7-007 dormant pg path (prove the refusal is gone by
   mechanism handoff, not by deleting the guard test — the dormant
   test is superseded by live pg backup evidence, recorded explicitly).
8. Append new migrations to `deploy/migrations-inventory.json`
   (P7-008 test semantics honored).

## 10. Failure / Recovery Semantics

- Partial migration is the primary risk: every phase checkpoints;
  failure → loud refusal + guided resume or full rollback, never
  silent continuation on a half-migrated store.
- Pre-migrate backup refusal blocks migration (fail-closed, golden
  `PRE-MIGRATE BACKUP REFUSED` path preserved).
- Stale-authority discipline: no writer may target the old SQLite file
  after cutover (config + runbook guard).

## 11. Security / Privacy Requirements

- DB credentials via environment only; never committed, never logged
  (P7-001 secrets-hygiene tests extended, not forked).
- Staging-copy data treated as production data (same access discipline).
- No third-party egress for user media or DB contents (D7-04 posture).

## 12. Observability / Operations Requirements

- Migration phases log through the P7-005 structured channel with
  correlation fields; no P7-005 format altered.
- `deployment:verify` gains a datastore sub-check within P7-008
  conventions (additive; P7-008 checks unbroken).
- Diagnostics command gains a datastore section within P7-005 naming.

## 13. Acceptance Criteria

- AC1: Dual-driver full suite green (SQLite + production-version pgsql),
  retained evidence both.
- AC2: Migration rehearsal on staging copy: row-count + checksum +
  revision-graph reconciliation PASS.
- AC3: `backup:pre-migrate` OK gate exercised before migration.
- AC4: Rollback rehearsal PASS (cutover → rollback → integrity re-check).
- AC5: Phase 6 revision/export regression green on pgsql
  (revision-aware export AC1–AC11 equivalent).
- AC6: Two-process claim-fencing suites green on pgsql.
- AC7: P7-001 registry flip recorded (advisory→required) with no fork;
  pre-P7-002 advisory behavior preserved until cutover.
- AC8: Standard gate (full suite/Pint/PHPStan 0; no Wave 2 file
  semantically altered beyond contracted deltas).
- AC9: No G-02 claim beyond evidence (gate consumption belongs to P7-012).

## 14. Test / Verification Requirements

- New `tests/Feature/Database/` suites: migration audit, reconciliation,
  resume/rollback, registry-flip, secrets hygiene.
- Dual-driver CI configuration (SQLite default; pgsql job against the
  production target version).
- Retained evidence: rehearsal logs, reconciliation reports, rollback log.

## 15. Technical Debt Mapping

- TD-003/TD-004: consumed (queue/Redis posture unchanged by driver swap;
  re-certify claim fencing on pgsql).
- TD-007/Option D: explicitly untouched (no staging-cleanup change).
- TD-011: untouched (storage streaming; P7-004).
- G-02 evidence originates here; G-08 drill and G-10/G-12 runs consume it.

## 16. Risks / Regression Concerns

- SQLite-ism drift (type coercion, `INSERT OR IGNORE`, partial-index
  semantics) — mitigated by the audit + dual-driver runs.
- Cutover-window writes — mitigated by maintenance discipline in the
  runbook + stale-authority guard.
- P7-001 registry fork risk — mitigated by contributing through the
  extension point and reviewing against VERIFIED P7-001.
- Runbook/inventory shared-file coordination with P7-008-owned files
  (P7-008 owns the files; this task contributes deltas by reference).

## 17. Completion Evidence

- Builder report with AC table, dual-driver logs, rehearsal +
  reconciliation + rollback artifacts, file list.
- Retained `verification/p7-002/` evidence bundle.

## 18. Reviewer Checklist

- [ ] No historical migration edited; inventory appended correctly.
- [ ] No P6 semantics redefined; revision-graph reconciliation exact.
- [ ] No P7-001 registry fork; flip recorded as contract change.
- [ ] Pre-migrate hook exercised; rollback rehearsed.
- [ ] No G-02/G-08 gate claim beyond evidence.
- [ ] Standard gate (suite/Pint/PHPStan) green.

## 19. State Transition Rule

BACKLOG (CONTRACT_AUTHORED) → READY only by explicit HPO promotion
after Wave 2 DONE (P7-001 + P7-007 VERIFIED→DONE) plus readiness
confirmation. READY → IN_PROGRESS only under a separate explicit HPO
Wave 3 execution authorization. REVIEW → VERIFIED/CHANGES_REQUESTED by
independent review (max 3 cycles, then BLOCKED); only the HPO closes
VERIFIED → DONE.
