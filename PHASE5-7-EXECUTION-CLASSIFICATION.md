# Phase 5–7 — Execution Classification (Controlled Parallel Model)

Date: 2026-09-21
Status: PLANNING / SCHEDULING ARTIFACT (does not itself authorize a task)
Authority: Phase 5–7 Controlled Parallel Execution Authorization (2026-09-21);
`PHASE5-PLANNING.md`; `PHASE6-PLANNING.md`; `PHASE7-PLANNING.md`;
`PHASE5-7-DEPENDENCY-GRAPH.md`; `.ai/guidelines/orchestration-policy.md`

This document satisfies the controlled-parallel model requirement to classify
every candidate Phase 6/7 task before implementation begins. Classification
describes the **earliest legal start point**; it does not authorize a task. A
task still requires its own contract and an HPO promotion to READY.

> **Status update (2026-09-23):** Phase 5 is CLOSED
> (`DECISION-PHASE5-CLOSURE-001`). The post-Phase-5 eligibility reconstruction
> and the recommended next batch are recorded in
> `PHASE6-7-ELIGIBILITY-MATRIX.md`; that artifact supersedes the "current
> eligibility" framing below while preserving this document's dependency
> rationale. Phase 6/7 remain NOT GENERALLY AUTHORIZED.

Classifications:

- `EARLY_START_ELIGIBLE` — may be promoted to READY independent of Phase 5
  closure, subject to its own contract and HPO promotion.
- `EARLY_START_CONDITIONAL` — may start early only after a named prerequisite
  contract exists and only for the bounded scope described.
- `MUST_WAIT_FOR_PHASE5` — cannot start until Phase 5 is CLOSED.
- `MUST_WAIT_FOR_PHASE6` — cannot start until Phase 6 is CLOSED.
- `FINAL_GATE_ONLY` — runs only at the terminal integration gate of its phase.

Every entry lists: dependency rationale; contracts consumed; contracts
deliberately not decided; earliest legal start; reconciliation required;
browser requirement; real-service/provider requirement.

---

## Phase 5 Tasks (critical path; Phase 5 = AUTHORIZED FOR IMPLEMENTATION)

Task dependencies for P5 are taken from the authored task contracts in
`tasks/P5-001..P5-008`; numbering is not proof of dependency.

| Task | Class | Depends (DONE) | Browser | Real service |
|---|---|---|---|---|
| P5-001 | EARLY_START_ELIGIBLE | D5-01..D5-09 frozen | no | no |
| P5-002 | EARLY_START_ELIGIBLE | P5-001 | no | no |
| P5-003 | EARLY_START_ELIGIBLE | P5-002 | no | no |
| P5-004 | EARLY_START_ELIGIBLE | P5-003 | no | yes (self-hosted translation model) |
| P5-005 | EARLY_START_ELIGIBLE | P5-003 | no | provider used for failure validation |
| P5-006 | EARLY_START_ELIGIBLE | P5-002, P5-005, P5-007 | yes (DC-01/ADR-021) | no |
| P5-007 | EARLY_START_ELIGIBLE | P5-002 | optional | no |
| P5-008 | FINAL_GATE_ONLY | P5-004, P5-005, P5-006, P5-007 | yes | yes (real provider/model) |

---

## Phase 6 Candidate Tasks

### P6-001 — Phase 6 UX + Editing Contract
- Classification: `MUST_WAIT_FOR_PHASE5`
- Dependency rationale: the contract must fix translation invalidation /
  revision-ownership semantics, which are Phase 5 deliverables.
- Contracts consumed: P1–P4 contracts; frozen D5 decisions.
- Contracts deliberately not decided: none (it decides them).
- Earliest legal start: Phase 5 CLOSED.
- Reconciliation required: P6-002/P6-004 may implement only the
  translation-independent subset once a bounded contract is separately
  authorized.
- Browser requirement: no.
- Real service: no.

### P6-002 — Edit Persistence + Revision Layer
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: generic immutable-machine-transcript + revision layer;
  does not decide translation ownership/invalidation.
- Contracts consumed: P3 persistence/immutability contracts; P4 read model.
- Contracts deliberately not decided: translation invalidation; split/merge
  remapping; revision↔translation linkage.
- Earliest legal start: HPO promotion to READY (may precede Phase 5 closure).
- Reconciliation required: after Phase 5 CLOSED, reconcile revision semantics
  with translation ownership.
- Browser requirement: no.
- Real service: no.

### P6-003 — Segment Text Editing UI + Undo/Redo
- Classification: `EARLY_START_CONDITIONAL`
- Prerequisite: a bounded editing contract (P6-001 subset) that does not decide
  translation behavior.
- Dependency rationale: generic edit + undo/redo against revision semantics.
- Contracts consumed: P6-002 revision layer; P4 primitives.
- Contracts deliberately not decided: translation-after-edit behavior.
- Earliest legal start: after bounded editing contract + P6-002 DONE.
- Reconciliation required: revisit when translation/revision semantics are
  reconciled after Phase 5.
- Browser requirement: yes.
- Real service: no.

### P6-004 — Timing Editing + Validation
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: timing invariants are independent of translation.
- Contracts consumed: P3 `decimal(12,3)` schema; P4 `ActiveSegmentResolver`.
- Contracts deliberately not decided: translation retiming behavior.
- Earliest legal start: after its contract + P6-002 revision layer (if edits
  persist through the revision layer).
- Reconciliation required: confirm no translation retiming assumption.
- Browser requirement: yes.
- Real service: no.

### P6-005 — Segment Split / Merge + Translation Invalidation
- Classification: `MUST_WAIT_FOR_PHASE5`
- Dependency rationale: directly defines translation invalidation and
  translation-unit remapping.
- Contracts consumed: P5 translation lifecycle/ownership; P6 revision layer.
- Contracts deliberately not decided: none (it decides them with P5).
- Earliest legal start: Phase 5 CLOSED.
- Reconciliation required: verify against persisted P5 translation shape.
- Browser requirement: yes.
- Real service: no.

### P6-006 — Advanced Navigation + Search/Filter
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: explicitly independent of Phase 5 (no translation
  interaction).
- Contracts consumed: P4 search/workspace primitives; P3 per-segment language.
- Contracts deliberately not decided: translation-aware filtering.
- Earliest legal start: HPO promotion to READY (may precede Phase 5 closure).
- Reconciliation required: extend filter to translations later if desired.
- Browser requirement: no (feature tests); optional browser.
- Real service: no.

### P6-007 — Source/Translation Comparison UX
- Classification: `MUST_WAIT_FOR_PHASE5`
- Dependency rationale: depends on persisted translation shape, staleness,
  ownership, and workspace model.
- Contracts consumed: P5 translation persistence/UX; P6 revision workspace.
- Contracts deliberately not decided: none prematurely.
- Earliest legal start: Phase 5 CLOSED.
- Reconciliation required: verify against P5 UI/state.
- Browser requirement: yes.
- Real service: no.

### P6-008 — Revision History / Audit Surface
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: generic transcript revision history; no translation
  ownership.
- Contracts consumed: P6-002 revision layer.
- Contracts deliberately not decided: translation revision history.
- Earliest legal start: after P6-002 DONE (conditional on D6-02 selecting
  history).
- Reconciliation required: integrate translation revision history later.
- Browser requirement: yes.
- Real service: no.

### P6-009 — Phase 6 Integration Verification
- Classification: `FINAL_GATE_ONLY`
- Dependency rationale: full combined P5/P6 behavior.
- Contracts consumed: all P5/P6.
- Earliest legal start: Phase 5 CLOSED and P6 tasks DONE.
- Reconciliation required: must evaluate translation/edit/stale behavior.
- Browser requirement: yes (browser-heavy).
- Real service: no (uses persisted P5 data).

---

## Phase 7 Candidate Tasks

### P7-001 — Production Configuration + Env Validation
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: environment validation / fail-fast boot does not
  depend on P5/P6 schemas.
- Contracts consumed: P1–P4 config surfaces.
- Contracts deliberately not decided: datastore/storage choices.
- Earliest legal start: HPO promotion to READY.
- Reconciliation required: update validation for new P5/P6 config keys.
- Browser requirement: no.
- Real service: no.

### P7-002 — Production Data Store Decision + Migration
- Classification: `MUST_WAIT_FOR_PHASE6`
- Dependency rationale: D7-01 affects schema/locking/migration and must know
  final P5/P6 tables.
- Contracts consumed: all P5/P6 persistence contracts.
- Earliest legal start: Phase 6 CLOSED (or explicit earlier HPO authorization).
- Reconciliation required: include P5/P6 tables in migration/backup.
- Browser requirement: no.
- Real service: database engine.

### P7-003 — Queue / Worker Supervision + Recovery
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: existing P3/P4 queue/worker behavior can be certified
  independently.
- Contracts consumed: P3 queue/retry contracts.
- Contracts deliberately not decided: queue architecture redesign.
- Earliest legal start: HPO promotion to READY.
- Reconciliation required: add translation queue once P5-003 is DONE.
- Browser requirement: no.
- Real service: live Redis + worker.

### P7-004 — Storage Strategy + Streaming Hardening
- Classification: `MUST_WAIT_FOR_PHASE6`
- Dependency rationale: D7-03 storage architecture affects retention/derived
  artifacts introduced by P5/P6.
- Contracts consumed: P2/P4 storage/streaming contracts.
- Earliest legal start: Phase 6 CLOSED (or explicit earlier HPO authorization).
- Reconciliation required: include translation/derived artifacts.
- Browser requirement: optional.
- Real service: object storage if selected.

### P7-005 — Observability Foundation
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: structured logging/correlation/health/metrics do not
  require final P5/P6 schemas.
- Contracts consumed: ADR-017 minimum correlation fields; P3 logging contract.
- Contracts deliberately not decided: dashboards depending on P5/P6 tables.
- Earliest legal start: HPO promotion to READY.
- Reconciliation required: add translation job correlation fields later.
- Browser requirement: no.
- Real service: optional.

### P7-006 — Security Hardening Baseline
- Classification: `EARLY_START_CONDITIONAL`
- Prerequisite: baseline only (secrets, headers, token/log leakage, endpoint
  hardening, filesystem permissions). Final authorization matrix waits.
- Dependency rationale: baseline hardening is independent; tenancy/authorization
  redesign is reserved (DC-02).
- Contracts consumed: P1–P4 policies.
- Contracts deliberately not decided: tenancy/actor-vs-owner.
- Earliest legal start: HPO promotion to READY.
- Reconciliation required: include P5/P6 access semantics before the final
  matrix.
- Browser requirement: no.
- Real service: no.

### P7-007 — Backup / Restore + Disaster Recovery
- Classification: `EARLY_START_CONDITIONAL`
- Prerequisite: tooling foundation may start early; final restore proof must
  include P5/P6 data.
- Dependency rationale: backup tooling is largely schema-agnostic.
- Contracts consumed: P2 storage contract.
- Contracts deliberately not decided: retention policy (D7-06).
- Earliest legal start: HPO promotion to READY.
- Reconciliation required: full restore drill after P6 CLOSED.
- Browser requirement: no.
- Real service: database/storage.

### P7-008 — Deployment, Migration Safety, Rollback
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: deployment automation foundation is schema-agnostic.
- Contracts consumed: none beyond environment.
- Contracts deliberately not decided: datastore/storage architecture.
- Earliest legal start: HPO promotion to READY.
- Reconciliation required: add P5/P6 migrations and worker startup.
- Browser requirement: no.
- Real service: deployment target.

### P7-009 — Performance / Load / Large-File Validation
- Classification: `EARLY_START_CONDITIONAL`
- Prerequisite: harness foundation may start early; final capacity targets wait
  (D7-08) and require P5/P6 workloads.
- Dependency rationale: harness tooling is independent; final numbers are not.
- Contracts consumed: P3 transcription contracts.
- Contracts deliberately not decided: final capacity targets.
- Earliest legal start: HPO promotion to READY (harness only).
- Reconciliation required: include translation concurrency in final run.
- Browser requirement: partial.
- Real service: FFmpeg + faster-whisper (+ translation).

### P7-010 — Browser Support Matrix + Flake Elimination
- Classification: `EARLY_START_ELIGIBLE`
- Dependency rationale: reusable browser-test infrastructure is independent;
  final supported matrix waits (D7-05).
- Contracts consumed: ADR-020; DC-01/ADR-021.
- Contracts deliberately not decided: final supported-browser matrix.
- Earliest legal start: after ADR-021 adopted.
- Reconciliation required: extend matrix to P6 editing flows.
- Browser requirement: yes.
- Real service: browser binaries.
- Note: the pre-existing `showRenameModal` fix and V4-08 flake stabilization
  are P1–P4 debt and require explicit allowlisting under §13.H before execution.

### P7-011 — Retention, Derived-Artifact + Orphan Cleanup
- Classification: `MUST_WAIT_FOR_PHASE6`
- Dependency rationale: final retention must know P5/P6 derived artifacts and
  revision data.
- Contracts consumed: P2 staging contract; P5/P6 artifacts.
- Earliest legal start: Phase 6 CLOSED (or explicit earlier HPO authorization).
- Reconciliation required: include translation/revision artifacts.
- Browser requirement: no.
- Real service: storage.

### P7-012 — Production Readiness Gate
- Classification: `FINAL_GATE_ONLY`
- Dependency rationale: must run after Phase 6 CLOSED and all P7 tasks DONE.
- Contracts consumed: all.
- Earliest legal start: Phase 6 CLOSED and all other P7 DONE.
- Reconciliation required: full production data model.
- Browser requirement: yes.
- Real service: full stack.

---

## Early-Start Pool (current)

Per the classification above, the following tasks are eligible (or conditionally
eligible) for early promotion independent of Phase 5 closure:

- Phase 6: P6-002 (eligible), P6-004 (eligible, contract-gated), P6-006
  (eligible), P6-008 (conditional on P6-002 and D6-02), P6-003 (conditional on a
  bounded editing contract).
- Phase 7: P7-001, P7-003, P7-005, P7-008, P7-010 (eligible); P7-006, P7-007,
  P7-009 (conditional on bounded scope).

Not currently eligible:
- Must wait for Phase 5: P6-001, P6-005, P6-007.
- Must wait for Phase 6: P7-002, P7-004, P7-011.
- Final gates: P6-009, P7-012.

## Scheduling Priority

```text
1. eligible Phase 5 critical-path work
2. eligible Phase 5 leaf work
3. eligible Phase 6 early-start work
4. eligible Phase 7 early-hardening work
5. allowlisted legacy debt
```

No task is authorized by this document. Each still requires its own contract and
an HPO READY promotion.

## Explicit Non-Actions

- This classification does not promote any task to READY.
- Phase 6 remains NOT GENERALLY AUTHORIZED; Phase 7 remains NOT GENERALLY
  AUTHORIZED.
- No task may rely on unfinished Phase 5 implementation or speculative Phase 6/7
  semantics (contract freeze rules).