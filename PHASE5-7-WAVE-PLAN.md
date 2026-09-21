# Phase 5–7 — Coordinated Wave Plan

Date: 2026-09-20
Status: PLANNING ONLY — NOT AUTHORIZED FOR IMPLEMENTATION
Authority: `PHASE5-PLANNING.md`, `PHASE6-PLANNING.md`, `PHASE7-PLANNING.md`,
`PHASE5-7-DEPENDENCY-GRAPH.md`, `.ai/guidelines/orchestration-policy.md`

This document derives execution waves from the dependency graph. It does not
authorize any task. Waves are proposals for the Human Product Owner; a wave is
authorized only by an explicit HPO authorization decision.

## Wave Semantics

A wave means:

- tasks in the wave **may** be authorized together by the HPO;
- tasks **may** be implemented in one orchestration run;
- each task remains an **independent governance unit** (BACKLOG → READY →
  IN_PROGRESS → REVIEW → VERIFIED → DONE);
- independent review remains **task-specific** (D4-07 per-task model) unless a
  contract explicitly defines a final integration gate (P5-008, P6-009,
  P7-012);
- closure remains **task-specific**; `VERIFIED` is not `DONE` until HPO closure.

Waves are not numbered arbitrarily; they are derived from DONE dependency gates
in `PHASE5-7-DEPENDENCY-GRAPH.md`. Within a wave, tasks may run in parallel only
if their dependencies are already satisfied.

## Cross-Phase Authorization Model

- Phase 5 planning may exist now. Phase 5 implementation remains NOT AUTHORIZED
  until an explicit HPO decision.
- Phase 6 planning may exist now. Phase 6 implementation remains NOT AUTHORIZED
  until Phase 5 closure and explicit HPO authorization, unless a specific
  independent task is demonstrated to have no Phase 5 dependency and the HPO
  separately approves it.
- Phase 7 planning may exist now. Phase 7 implementation remains NOT AUTHORIZED
  until Phase 6 closure and explicit HPO authorization, unless the HPO
  explicitly authorizes an earlier hardening item.
- Planning does not equal implementation authorization.

## Phase 5 Waves

### WAVE P5-1 — Translation foundation
- Tasks: **P5-001** (Translation Domain Contract)
- Parallelizable: none (single gate task)
- Dependencies: D5-01..D5-09 (and DC-01) resolved; HPO Phase 5 authorization
- Browser required: no
- Review strategy: per-task independent review; contract task, no UI
- Gate to next wave: P5-001 DONE

### WAVE P5-2 — Translation persistence
- Tasks: **P5-002** (Translation Persistence + Atomic Completion)
- Parallelizable: none
- Dependencies: P5-001 DONE
- Browser required: no
- Review strategy: per-task independent review; focused tests + full regression
- Gate to next wave: P5-002 DONE

### WAVE P5-3 — Orchestration and export
- Tasks: **P5-003** (Translation Queue Orchestration + Provider Boundary),
  **P5-007** (Translated Export)
- Parallelizable: P5-003 and P5-007 are independent (both depend only on
  P5-002 DONE)
- Dependencies: P5-002 DONE
- Browser required: no (P5-007 export is HTTP/file evidence; browser only if the
  export action is exercised in-browser — optional)
- Review strategy: per-task independent review
- Gate to next wave: P5-003 DONE and P5-007 DONE

### WAVE P5-4 — Provider and hardening
- Tasks: **P5-004** (Self-Hosted Translation Provider),
  **P5-005** (Translation Failure / Retry / Recovery Hardening)
- Parallelizable: yes (both depend on P5-003 DONE; they touch different
  surfaces, but P5-005's retry path should be validated against the real
  provider where feasible)
- Dependencies: P5-003 DONE
- Browser required: no
- Review strategy: per-task independent review; P5-005 requires genuine
  concurrency evidence (ADR-013/016 precedent)
- Gate to next wave: P5-004 DONE and P5-005 DONE

### WAVE P5-5 — Translation UI
- Tasks: **P5-006** (Translation UI)
- Parallelizable: none
- Dependencies: P5-002 DONE, P5-005 DONE, P5-007 DONE (export action surfaced
  in UI)
- Browser required: **yes** (real browser verification; DC-01)
- Review strategy: per-task independent review + browser evidence artifact
- Gate to next wave: P5-006 DONE

### WAVE P5-6 — Phase 5 integration gate
- Tasks: **P5-008** (Phase 5 Integration Verification)
- Parallelizable: none
- Dependencies: P5-004, P5-005, P5-006, P5-007 DONE
- Browser required: **yes**
- Review strategy: final integration gate with one independent review of the
  gate artifact (D4-07-style; the gate is its own governance unit)
- Gate to Phase 5 closure: P5-008 DONE + independent VERIFIED + HPO closure

## Phase 6 Waves

Phase 6 waves assume Phase 5 is CLOSED (except a separately HPO-approved
no-Phase-5-dependency task such as P6-006).

### WAVE P6-1 — Phase 6 contract
- Tasks: **P6-001** (Phase 6 UX + Editing Contract)
- Parallelizable: none
- Dependencies: Phase 5 CLOSED; D6-01..D6-09 resolved; HPO Phase 6 authorization
- Browser required: no
- Review strategy: per-task independent review
- Gate: P6-001 DONE

### WAVE P6-2 — Edit persistence layer
- Tasks: **P6-002** (Edit Persistence + Revision Layer)
- Parallelizable: none
- Dependencies: P6-001 DONE
- Browser required: no
- Review strategy: per-task independent review; full regression
- Gate: P6-002 DONE

### WAVE P6-3 — Editing and comparison
- Tasks: **P6-003** (Segment Text Editing UI + Undo/Redo),
  **P6-007** (Source/Translation Comparison UX)
- Parallelizable: yes (P6-003 depends P6-002; P6-007 depends P5 closure and
  P6-002 read layer)
- Dependencies: P6-002 DONE
- Browser required: **yes** (both)
- Review strategy: per-task independent review + browser evidence
- Gate: P6-003 DONE and P6-007 DONE

### WAVE P6-4 — Timing and navigation
- Tasks: **P6-004** (Timing Editing + Validation),
  **P6-006** (Advanced Navigation + Search/Filter)
- Parallelizable: yes (P6-004 depends P6-002; P6-006 is independent of editing
  and may be authorized early if Phase 5 has no dependency)
- Dependencies: P6-002 DONE (P6-006: Phase 4 primitives only)
- Browser required: **yes** (P6-004), no (P6-006 backend/filter tests)
- Review strategy: per-task independent review
- Gate: P6-004 DONE and P6-006 DONE

### WAVE P6-5 — Split/merge + translation invalidation
- Tasks: **P6-005** (Segment Split / Merge + Translation Invalidation)
- Parallelizable: none
- Dependencies: P6-004 DONE; Phase 5 semantics DONE
- Browser required: **yes**
- Review strategy: per-task independent review; cross-phase alignment evidence
- Gate: P6-005 DONE

### WAVE P6-6 — Revision history surface
- Tasks: **P6-008** (Revision History / Audit Surface) — only if D6-02 selects
  history
- Parallelizable: none
- Dependencies: P6-002 DONE
- Browser required: **yes**
- Review strategy: per-task independent review
- Gate: P6-008 DONE (or cancelled if D6-02 excludes history)

### WAVE P6-7 — Phase 6 integration gate
- Tasks: **P6-009** (Phase 6 Integration Verification)
- Parallelizable: none
- Dependencies: P6-003..P6-008 DONE
- Browser required: **yes** (browser-heavy)
- Review strategy: final integration gate; one independent review of the gate
- Gate to Phase 6 closure: P6-009 DONE + independent VERIFIED + HPO closure

## Phase 7 Waves

Phase 7 waves assume Phase 6 is CLOSED and D7 decisions resolved.

### WAVE P7-1 — Configuration and browser baseline
- Tasks: **P7-001** (Production Configuration + Env Validation),
  **P7-010** (Browser Support Matrix + Flake Elimination)
- Parallelizable: yes
- Dependencies: Phase 6 CLOSED
- Browser required: **yes** (P7-010)
- Review strategy: per-task independent review
- Gate: P7-001 DONE and P7-010 DONE

### WAVE P7-2 — Data, queue, storage
- Tasks: **P7-002** (Production Data Store), **P7-003** (Queue / Worker
  Supervision + Recovery), **P7-004** (Storage Strategy + Streaming Hardening)
- Parallelizable: yes (independent infrastructure decisions)
- Dependencies: P7-001 DONE; D7-01/D7-02/D7-03
- Browser required: no
- Review strategy: per-task independent review; real Redis / storage evidence
- Gate: P7-002/003/004 DONE

### WAVE P7-3 — Observability, security, cleanup
- Tasks: **P7-005** (Observability), **P7-006** (Security Hardening),
  **P7-011** (Retention, Derived-Artifact + Orphan Cleanup)
- Parallelizable: yes
- Dependencies: P7-001 DONE (P7-011 additionally P7-004 DONE)
- Browser required: no
- Review strategy: per-task independent review
- Gate: P7-005/006/011 DONE

### WAVE P7-4 — Recovery and deployment
- Tasks: **P7-007** (Backup / Restore + DR), **P7-008** (Deployment, Migration
  Safety, Rollback)
- Parallelizable: yes (coordinate on the deployment target)
- Dependencies: P7-002/003/004 DONE
- Browser required: no
- Review strategy: per-task independent review; restore drill evidence
- Gate: P7-007/008 DONE

### WAVE P7-5 — Performance / load validation
- Tasks: **P7-009** (Performance / Load / Large-File Validation)
- Parallelizable: none
- Dependencies: P7-002/003/004 DONE
- Browser required: partial (render/export flows)
- Review strategy: per-task independent review with retained evidence
- Gate: P7-009 DONE

### WAVE P7-6 — Production readiness gate
- Tasks: **P7-012** (Production Readiness Gate)
- Parallelizable: none
- Dependencies: all P7 tasks DONE
- Browser required: **yes** (end-to-end production flows)
- Review strategy: final readiness gate; one independent review
- Gate to Phase 7 closure: P7-012 DONE + independent VERIFIED + HPO closure

## Phase Gates

### Phase 5 gate — before Phase 5 may close
1. All Phase 5 tasks (P5-001..P5-008) = DONE (HPO closure).
2. P5-008 integration verification = independent VERIFIED, including real
   translation provider/model (per D5-09) and browser evidence.
3. No unresolved BLOCKER/HIGH/MEDIUM finding.
4. Source transcript immutability and ownership isolation verified.
5. Translated export verified for the agreed format set (D5-08).
6. Phase 3/4 regression suite green; residual debt recorded.
7. HPO closure decision recorded (DECISION-PHASE5-CLOSURE-001 or equivalent).

### Phase 6 gate — before Phase 6 may close
1. All Phase 6 tasks = DONE.
2. P6-009 browser-heavy integration verification = independent VERIFIED.
3. Edit/undo/redo/reload persistence, timing validation, split/merge alignment,
   translation invalidation, and exports-after-edit verified.
4. No unresolved BLOCKER/HIGH/MEDIUM finding.
5. Machine-transcript provenance preserved and original recoverable.
6. HPO closure decision recorded.

### Phase 7 gate — before Phase 7 may close
1. All Phase 7 tasks = DONE.
2. P7-012 production-readiness gate = independent VERIFIED.
3. Clean deployment, migrations, queues/workers, upload, transcription,
   translation, playback, exports, browser flows verified in the production
   configuration.
4. Security, backup/restore, failure recovery, observability, load/performance,
   and rollback evidence retained.
5. Production data-store/queue/storage decisions implemented or explicitly
   deferred with HPO acceptance.
6. No unresolved BLOCKER/HIGH/MEDIUM finding.
7. HPO closure decision recorded.

## Wave Summary Table

| Phase | Wave | Tasks | Parallel | Browser |
|---|---|---|---|---|
| 5 | P5-1 | P5-001 | — | no |
| 5 | P5-2 | P5-002 | — | no |
| 5 | P5-3 | P5-003, P5-007 | yes | no |
| 5 | P5-4 | P5-004, P5-005 | yes | no |
| 5 | P5-5 | P5-006 | — | yes |
| 5 | P5-6 | P5-008 | — | yes |
| 6 | P6-1 | P6-001 | — | no |
| 6 | P6-2 | P6-002 | — | no |
| 6 | P6-3 | P6-003, P6-007 | yes | yes |
| 6 | P6-4 | P6-004, P6-006 | yes | yes/no |
| 6 | P6-5 | P6-005 | — | yes |
| 6 | P6-6 | P6-008 | — | yes |
| 6 | P6-7 | P6-009 | — | yes |
| 7 | P7-1 | P7-001, P7-010 | yes | yes |
| 7 | P7-2 | P7-002, P7-003, P7-004 | yes | no |
| 7 | P7-3 | P7-005, P7-006, P7-011 | yes | no |
| 7 | P7-4 | P7-007, P7-008 | yes | no |
| 7 | P7-5 | P7-009 | — | partial |
| 7 | P7-6 | P7-012 | — | yes |

## Explicit Non-Actions

- No wave is authorized. No task is promoted to READY.
- Phase 5, Phase 6, and Phase 7 implementation remain NOT AUTHORIZED.
- This document makes no owner decision.