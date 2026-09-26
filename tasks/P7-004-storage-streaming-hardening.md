# P7-004 — Storage Strategy + Streaming Hardening

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-004-CLOSURE-001`) on the independent VERIFIED verdict
below (no environment-blocked AC).

VERIFIED — independent review (`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`
§D, §M) 2026-09-26: AC1–AC9 independently reproduced PASS, including the
zero-byte streaming defect fix (§F) and the full range-edge/topology/
compatibility suites. No environment-blocked AC. No BLOCKER/HIGH/MEDIUM
finding against this task (the review's TD-008 and `CURRENT_STATE.md`
notes are cross-cutting/not attributable to this task's diff, §L). Not
DONE; DONE requires HPO closure.

History preserved: REVIEW (builder report `reviews/P7-004-BUILDER-REPORT.md`)
→ VERIFIED (`reviews/PHASE7-WAVE3A-INDEPENDENT-REVIEW.md`, 2026-09-26).

History preserved: BACKLOG — CONTRACT_AUTHORED → reconciled → READY (HPO
`DECISION-PHASE7-WAVE3A-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-004-BUILDER-REPORT.md`;
zero-byte defect fixed explicitly, TD-011 evidenced). Not VERIFIED, not
DONE. Independent review returns VERIFIED or CHANGES_REQUESTED (max 3
cycles, then BLOCKED).

IN_PROGRESS — implementation begun 2026-09-26 under
`DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`.

READY — promoted by the HPO on 2026-09-26
(`DECISION-PHASE7-WAVE3A-READY-PROMOTION-001`) after reconciliation
against final Wave 2 DONE interfaces (P7-001 DONE
`DECISION-P7-001-CLOSURE-001`; P7-006 DONE `DECISION-P7-006-CLOSURE-001`
with F4 narrative-accepted/F5 reconciled; P7-007 DONE
`DECISION-P7-007-CLOSURE-001` with F6 correction + test follow-up open;
Wave 2 CLOSED `DECISION-PHASE7-WAVE2-CLOSURE-001`).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26, Wave 3
preparation while Wave 2 was under independent review; planning only,
no implementation) → reconciled (no stale assumption found: D7-03/A
binding, object storage absent from the tree; P7-001
`deployment.min_free_bytes` mechanism, P7-006 `gateMalwareScan` +
quarantine path, P7-007 media-manifest semantics all verified against
the DONE tree; TD-011 ownership intact; no storage migration beyond
local scope) → READY. Not authorized for implementation;
READY != EXECUTION AUTHORIZATION. No code written under this contract.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-004
- Canonical title: Storage Strategy + Streaming Hardening
- Phase: 7 — Production Hardening
- Proposed state: BACKLOG (CONTRACT_AUTHORED; awaiting Wave 2 closure +
  HPO READY promotion)

## 2. Objective

Harden the adopted local private-storage posture (D7-03/A) for
production: validated single-node topology, permissions, durability,
capacity/health behavior, range-streaming correctness, and direct tests
closing TD-011 — while keeping revision/export paths and the Wave 2
ingestion/verification surfaces intact.

## 3. Why It Exists

P4-002's range-stream endpoint was built on a seekable local abstraction
with partly code-reviewed (not directly tested) behavior (TD-011), and
production capacity/durability/permissions were never certified. D7-03
settles the backend (local private); this task proves it production-ready
and feeds G-05. It is also the binding prerequisite for P7-011
(P7-011 must not start before P7-004 VERIFIED).

## 4. Binding D7 Decisions / ADRs

- D7-03 = OPTION A (local private storage; object storage deferred, NOT
  rejected) — `DECISION-PHASE7-OWNER-DECISIONS-001` / ADR-026.
- D7-06 (retention interaction: purge mechanics belong to P7-011; this
  task defines the storage truth purge operates on).
- Phase 6 frozen semantics: revision/translation artifacts must be
  accounted for in the storage topology (scope contract §E).

## 5. Dependencies

- Hard prerequisites (Wave 2): P7-001 DONE (storage-capacity posture
  interface), P7-006 DONE (owns `MediaIngestionService::gateMalwareScan`
  and upload-abuse path — this task must not fork them), P7-007
  foundation DONE (media-tree manifest semantics this task must stay
  compatible with). Classification: `REQUIRES_WAVE2_DONE`
  (P7-001, P7-006, P7-007).
- Already DONE, consumed by reference: P7-008 (deployment topology; storage
  paths mount inside it), P7-005 (logging conventions), P7-010 (no browser
  change here).
- Downstream: P7-011 (requires P7-004 VERIFIED — binding), P7-009 final
  run (requires P7-004 DONE), P7-012 gate G-05.

## 6. In Scope

1. Production local private-storage topology doc (paths, mounts,
   permissions, ownership, umask) validated against the P7-008 topology.
2. Artifact-path behavior contract (opaque UUID identities preserved;
   quarantine exclusion preserved per P7-006/P7-007).
3. Streaming/range semantics: 206/Accept-Ranges/Content-Type correctness,
   no full-object PHP memory load, seekability on the production mount.
4. TD-011 direct tests against the local backend: bounded-stream
   regression assertions, range-edge branches, zero-byte edge disposition
   (prove unreachable or handle explicitly).
5. Durability expectations (fsync discipline where applicable, documented
   single-node limits), capacity/health behavior (free-space floor
   coordination with P7-001's `deployment.min_free_bytes`, degraded-mode
   behavior).
6. Revision/export path compatibility (revision-aware export suite green;
   prepared-audio retention re-validated).
7. P7-009 handoff: storage limits/assumptions the capacity run consumes.

## 7. Explicit Non-Scope

- S3/object-storage migration (deferred per D7-03, not rejected — NO
  object-storage code, config, or dependency in this task).
- Data migration between stores (no migration under D7-03=A; scope
  contract §F).
- Retention purge mechanics (P7-011); backup mechanism (P7-007);
  malware-scan behavior changes (P7-006); env certification (P7-001).

## 8. Architecture / Domain Contract

- One backend: local private disk (configured disk; public-disk boundary
  rejection from P2-002A preserved).
- Streaming stays seekable-file based; range GETs never load the full
  object into PHP memory (established P4-002 invariant, re-proven here).
- Storage truth (what exists, where, under which identity) is defined
  once here; P7-007's manifest and P7-011's purge both consume it without
  redefining it.

## 9. Detailed Implementation Requirements

1. Topology + permissions validation procedure (scripted checks, retained
   output) for the production mount.
2. Range/streaming contract tests: 206 matrix, suffix/open ranges,
   unsatisfiable-range handling, Content-Type/Accept-Ranges pins,
   memory-bounded delivery proof.
3. TD-011 tests: bounded-stream assertions, range-edge branches,
   zero-byte disposition test.
4. Capacity/health: free-space floor wiring through P7-001's mechanism
   (additive deltas; P7-001 owns the guard), degraded behavior doc.
5. Quarantine + opaque-identity compatibility tests (P7-006/P7-007
   surfaces unbroken).
6. Revision/export regression green (Phase 6 export AC set).
7. Runbook deltas by reference (P7-008 owns the file).

## 10. Failure / Recovery Semantics

- Unwritable/full/degraded storage fails closed with structured,
  actionable errors (no silent partial writes; no orphaned rows —
  P2-002B compensation preserved).
- Health-check failure surfaces through `deployment:verify` + diagnostics
  (additive sub-checks; pre-existing checks unbroken).

## 11. Security / Privacy Requirements

- Private-disk boundary: no path leakage (established invariant,
  re-tested); permission tightening must not break worker/queue access.
- Quarantine隔离 preserved: infected objects never enter the servable tree.
- No third-party egress (D7-04 posture intact).

## 12. Observability / Operations Requirements

- Storage health + capacity via P7-005 channel/naming; no format altered.
- `deployment:verify` storage sub-checks (additive, P7-008 conventions).
- Diagnostics storage section (additive).

## 13. Acceptance Criteria

- AC1: Topology/permissions validation green on the target-shaped mount.
- AC2: Range matrix green (206/suffix/unsatisfiable/type/range headers).
- AC3: TD-011 tests green against the local backend (bounded, edges,
  zero-byte disposition recorded).
- AC4: P7-006 ingestion/scan + P7-007 manifest compatibility green
  (no fork, no behavior change).
- AC5: Revision/export regression green.
- AC6: Capacity-floor + degraded behavior evidenced.
- AC7: No object-storage content anywhere in the diff.
- AC8: Standard gate (full suite/Pint/PHPStan 0; no Wave 2 file
  semantically altered beyond contracted deltas).
- AC9: No G-05 claim beyond evidence (gate consumption belongs to P7-012).

## 14. Test / Verification Requirements

- New `tests/Feature/Storage/` suites (topology, range matrix, TD-011,
  compatibility, capacity).
- Retained evidence: validation outputs, range-proof logs.

## 15. Technical Debt Mapping

- TD-011 (LOW): verification owner = this task; closes at P7-012 G-05
  consumption (not at task DONE — status change follows HPO gate).
- TD-007/Option D: untouched (purge mechanics are P7-011).
- TD-002 capacity share: storage-capacity portion evidenced here; G-01
  proof stays with P7-009.

## 16. Risks / Regression Concerns

- `MediaIngestionService` shared surface with P7-006 (additive tests
  only; no scan-path behavior change).
- Media-manifest semantic drift vs P7-007 (contract: manifest semantics
  owned by P7-007; this task tests compatibility, never redefines).
- P7-001 guard fork risk (deltas through the extension point only).

## 17. Completion Evidence

- Builder report with AC table, topology validation outputs, range-proof
  logs, file list; retained `verification/p7-004/` bundle.

## 18. Reviewer Checklist

- [ ] No object-storage code/config/dependency introduced.
- [ ] No P7-006 scan-path behavior change; no P7-007 manifest redefinition.
- [ ] TD-011 branches directly tested (not code-reviewed).
- [ ] No P7-001/P7-008 file fork (deltas by reference/extension).
- [ ] No G-05 gate claim beyond evidence.
- [ ] Standard gate green.

## 19. State Transition Rule

BACKLOG (CONTRACT_AUTHORED) → READY only by explicit HPO promotion
after Wave 2 DONE (P7-001 + P7-006 + P7-007 VERIFIED→DONE) plus
readiness confirmation. READY → IN_PROGRESS only under a separate
explicit HPO Wave 3 execution authorization. REVIEW →
VERIFIED/CHANGES_REQUESTED by independent review (max 3 cycles, then
BLOCKED); only the HPO closes VERIFIED → DONE. P7-011 remains barred
until this task is VERIFIED (binding §5 rule).
