# Phase 5–7 — Dependency Graph (DAG)

Date: 2026-09-20
Status: PLANNING ONLY — NOT AUTHORIZED FOR IMPLEMENTATION
Authority: `PHASE5-PLANNING.md`, `PHASE6-PLANNING.md`, `PHASE7-PLANNING.md`,
`PHASE5-7-WAVE-PLAN.md`

Dependency gates below use **DONE** (HPO closure), not "implementation
complete", because the repository's canonical governance requires DONE for
cross-task gating unless an explicit batch exception says otherwise. The Phase 3
batch exception does not apply to Phases 5–7 unless separately authorized.
Task IDs are candidates from the planning packages; no task file exists.

## Phase 5 DAG

```text
P5-001 (Translation Domain Contract)
 ├─ P5-002 (Translation Persistence + Atomic Completion)
 │   ├─ P5-003 (Translation Queue Orchestration + Provider Boundary)
 │   │   ├─ P5-004 (Self-Hosted Translation Provider)
 │   │   └─ P5-005 (Failure / Retry / Recovery Hardening)
 │   └─ P5-007 (Translated Export)
 ├─ (P5-006 Translation UI)
 └─ (P5-008 Phase 5 Integration Verification)

Concrete DONE gates:
P5-001 DONE            → P5-002
P5-002 DONE            → P5-003, P5-007
P5-003 DONE            → P5-004, P5-005
P5-002 DONE + P5-005 DONE + P5-007 DONE → P5-006
P5-004 DONE + P5-005 DONE + P5-006 DONE + P5-007 DONE → P5-008
P5-008 DONE + independent VERIFIED + HPO closure → Phase 5 CLOSED
```

Notes:
- P5-003 and P5-007 are parallel after P5-002 (independent surfaces).
- P5-004 and P5-005 are parallel after P5-003 (different surfaces; P5-005
  should validate against the real provider where feasible).
- P5-006 additionally consumes Phase 4 primitives (already DONE).
- P5-008 is the single Phase 5 integration gate.

## Phase 6 DAG

```text
P6-001 (Phase 6 UX + Editing Contract)
 └─ P6-002 (Edit Persistence + Revision Layer)
     ├─ P6-003 (Segment Text Editing UI + Undo/Redo)
     ├─ P6-004 (Timing Editing + Validation)
     │   └─ P6-005 (Split / Merge + Translation Invalidation)
     ├─ P6-006 (Advanced Navigation + Search/Filter)   [no Phase 5 dependency]
     ├─ P6-007 (Source/Translation Comparison UX)      [requires P5 CLOSED]
     └─ P6-008 (Revision History / Audit Surface)      [if D6-02 selects history]

Concrete DONE gates:
Phase 5 CLOSED (Phase 6 baseline authorization) + D6-01..D6-09 → P6-001
P6-001 DONE            → P6-002
P6-002 DONE            → P6-003, P6-004, P6-006, P6-007, P6-008
P6-004 DONE + Phase 5 semantics DONE → P6-005
P6-003 DONE + P6-004 DONE + P6-005 DONE + P6-006 DONE + P6-007 DONE + P6-008 DONE → P6-009
P6-009 DONE + independent VERIFIED + HPO closure → Phase 6 CLOSED
```

Notes:
- P6-006 has no Phase 5 dependency; it may be authorized before Phase 5 closure
  only under a separate explicit HPO approval (exception path).
- P6-007 depends on Phase 5 DONE because it compares translations.
- P6-005 depends on Phase 5 alignment semantics (D5-01/D5-04 resolved and P5
  implemented) because split/merge invalidates translation alignment.
- P6-008 is conditional on D6-02; if D6-02 excludes persistent history, P6-008
  is cancelled and excluded from the P6-009 gate.

### Phase 6 DAG status — 2026-09-23 (reconciliation)

The DAG above is the frozen dependency structure; this note records completion
state only (no dependency was changed).

```text
P6-001 = DONE   (DECISION-P6-001-CLOSURE-001)
P6-002 = DONE   (DECISION-P6-002-CLOSURE-001)
P6-006 = DONE   (DECISION-P6-006-CLOSURE-001)
P6-003 = CONTRACT_AUTHORED (gate P6-002 DONE satisfied; pending HPO READY)
P6-004 = NOT STARTED   (gate P6-002 DONE now satisfied; contract required)
P6-005 = NOT STARTED   (still gated on P6-004 DONE; contract required)
P6-007 = CONTRACT_AUTHORED (gate P6-002 DONE + P5 DONE satisfied; presentation-only
                        under DECISION-P6-007-SCOPE-001; pending HPO READY)
P6-008 = NOT STARTED   (gate P6-002 DONE satisfied; D6-02 selects persistent
                        history; contract required)
P6-009 = FINAL_GATE_ONLY (gated on P6-003..P6-008 DONE)
```

`P6-002 DONE → P6-003, P6-004, P6-006, P6-007, P6-008` is now satisfied for the
eligible downstream tasks; each still requires its own canonical contract and an
explicit HPO READY promotion. No Phase 6/7 implementation was started by this
status reconciliation.

## Phase 7 DAG

```text
P7-001 (Production Configuration + Env Validation)
 ├─ P7-002 (Production Data Store + Migration)
 ├─ P7-003 (Queue / Worker Supervision + Recovery)
 ├─ P7-004 (Storage Strategy + Streaming Hardening)
 ├─ P7-005 (Observability)
 ├─ P7-006 (Security Hardening)
 └─ P7-010 (Browser Support Matrix + Flake Elimination)

P7-002 DONE + P7-003 DONE + P7-004 DONE
 ├─ P7-007 (Backup / Restore + DR)
 ├─ P7-008 (Deployment, Migration Safety, Rollback)
 └─ P7-009 (Performance / Load / Large-File Validation)

P7-004 DONE → P7-011 (Retention, Derived-Artifact + Orphan Cleanup)

Concrete DONE gates:
Phase 6 CLOSED + D7-* resolved → P7-001
P7-001 DONE            → P7-002, P7-003, P7-004, P7-005, P7-006, P7-010
P7-002/003/004 DONE    → P7-007, P7-008, P7-009
P7-004 DONE            → P7-011
All P7 tasks DONE      → P7-012
P7-012 DONE + independent VERIFIED + HPO closure → Phase 7 CLOSED
```

Notes:
- P7-001 (env validation) is the shared prerequisite for infrastructure tasks.
- P7-005/P7-006 do not require P7-002/003/004; they may run in parallel with
  them.
- P7-011 additionally requires P7-004 because cleanup depends on the chosen
  storage layout.

## Cross-Phase Dependencies

```text
Phase 5 CLOSED
  → enables full Phase 6 (P6-001, and in particular P6-005, P6-007)
  → [exception] P6-006 may run earlier only with separate HPO approval

Phase 6 CLOSED
  → enables Phase 7 (P7-001 onward)

Phase 5 DONE
  → enables P6-005 (translation invalidation) and P6-007 (comparison)

Phase 3 completed-transcription protection (ADR-018 B3-04)
  → constrains P5 (source immutability) and P6 (machine transcript immutability)

Phase 4 primitives (SegmentTimestamp, ActiveSegmentResolver, WorkspaceAvailability)
  → consumed by P5-006, P6-003, P6-004, P6-006, P6-007

Phase 3/4 deferred debt
  → mapped into Phase 7 tasks (see PHASE7-PLANNING.md §E)
```

Cross-phase **implementation** dependencies are deliberately limited: Phase 5
and Phase 7 have no implementation dependency; Phase 6 has material
implementation dependencies on Phase 5 only for translation-touching tasks
(P6-005, P6-007).

## Wave Mapping (DAG → waves)

```text
P5-001 → WAVE P5-1
P5-002 → WAVE P5-2
P5-003, P5-007 → WAVE P5-3
P5-004, P5-005 → WAVE P5-4
P5-006 → WAVE P5-5
P5-008 → WAVE P5-6

P6-001 → WAVE P6-1
P6-002 → WAVE P6-2
P6-003, P6-007 → WAVE P6-3
P6-004, P6-006 → WAVE P6-4
P6-005 → WAVE P6-5
P6-008 → WAVE P6-6
P6-009 → WAVE P6-7

P7-001, P7-010 → WAVE P7-1
P7-002, P7-003, P7-004 → WAVE P7-2
P7-005, P7-006, P7-011 → WAVE P7-3
P7-007, P7-008 → WAVE P7-4
P7-009 → WAVE P7-5
P7-012 → WAVE P7-6
```

## Gate Legend

- **requires DONE**: the predecessor's task state must be DONE (HPO-closed).
- **requires CLOSED**: the predecessor phase must be closed by HPO decision.
- **requires resolved**: the referenced owner decision(s) must be RESOLVED in
  `DECISION_QUEUE.md` (durable ADR where applicable).
- **independent VERIFIED**: the integration gate must pass independent review.

## Explicit Non-Actions

- The graphs describe proposed dependencies only; they do not authorize any
  task, wave, or phase.
- No task is promoted to READY.
- Phase 5/6/7 implementation remains NOT AUTHORIZED.