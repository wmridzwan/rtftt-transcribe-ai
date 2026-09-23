# Phase 6 / Phase 7 — Eligibility Reconstruction & Next Batch (post-Phase 5)

Date: 2026-09-23
Status: PLANNING / SCHEDULING RECONCILIATION (does not itself authorize a task)
Authority: `PHASE5-7-EXECUTION-CLASSIFICATION.md`; `PHASE5-7-DEPENDENCY-GRAPH.md`;
`PHASE5-7-WAVE-PLAN.md`; `PHASE6-PLANNING.md`; `PHASE7-PLANNING.md`;
`PHASE5-7-DECISION-REGISTER.md`; ADR-023; `DECISION-PHASE5-CLOSURE-001`;
`BLOCKERS.md`; `CURRENT_STATE.md`

This artifact reconstructs Phase 6 and Phase 7 eligibility after Phase 5 was
declared CLOSED (2026-09-23, `DECISION-PHASE5-CLOSURE-001`). It supersedes the
"current eligibility" framing in `PHASE5-7-EXECUTION-CLASSIFICATION.md` (dated
2026-09-21, when Phase 5 was open) while preserving that document's dependency
rationale. It does not authorize any task, wave, or phase.

## A. Repository state used

- Phase 5 = CLOSED; all P5 tasks DONE; P5-008 independently VERIFIED.
- Phase 6 = AUTHORIZED for contract authoring + implementation
  (`DECISION-PHASE6-AUTHORIZATION-001`); no blanket READY.
- Phase 7 = NOT GENERALLY AUTHORIZED; P7-005 alone authorized early
  (`DECISION-P7-005-AUTHORIZATION-001`).
- **D6-01..D6-09 and DC-01 are ADOPTED** (`DECISION-PHASE6-OWNER-DECISIONS-001`;
  ADR-025). D7-01..D7-08 and DC-02 remain OPEN.
- Task contracts (state as of the §K reconciliation, 2026-09-23):
  `tasks/P6-001-...md` (IMPLEMENTED_PENDING_REVIEW),
  `tasks/P6-002-...md` (CONTRACT_AUTHORED_PENDING_P6-001_CLOSURE),
  `tasks/P6-006-...md` (DONE), `tasks/P7-005-...md` (DONE).
- Phase 6 cannot close before Phase 5 (satisfied); Phase 7 cannot close before
  Phase 6; P7-012 must not run before Phase 6 is CLOSED.

## B. Classification vocabulary

- `READY_NOW` — contract exists and HPO has promoted the task to READY.
- `EARLY_START_ELIGIBLE` — no Phase 5 dependency; may be promoted early under
  ADR-023, subject to its own contract and an HPO READY promotion.
- `CONDITIONAL` — may start only after a named prerequisite contract/decision
  exists, for the bounded scope described.
- `MUST_WAIT` — cannot start until a named phase/decision gate is satisfied.
- `FINAL_GATE_ONLY` — runs only at its phase's terminal integration gate.

## C. Phase 6 eligibility matrix (post-Phase 5)

| Task | Class | Blocking prerequisite | Notes |
|---|---|---|---|
| P6-001 Phase 6 UX + Editing Contract | CONDITIONAL | D6-01..D6-09 resolved + HPO Phase 6 authorization + contract | Phase 5 gate now satisfied; decides editing/revision/translation-invalidation semantics |
| P6-002 Edit Persistence + Revision Layer | EARLY_START_ELIGIBLE | own bounded contract + HPO READY promotion | Generic immutable-transcript + revision layer; does not decide translation invalidation; reconcile with P6-001 semantics once fixed |
| P6-003 Segment Text Editing UI + Undo/Redo | CONDITIONAL | bounded editing contract (P6-001 subset) + P6-002 DONE | Browser required |
| P6-004 Timing Editing + Validation | EARLY_START_ELIGIBLE | own contract + P6-002 (if edits persist via revision layer) | Browser required; timing invariants independent of translation |
| P6-005 Split / Merge + Translation Invalidation | CONDITIONAL | P6-004 DONE + Phase 5 semantics (now DONE) + D6-04 + contract | No longer `MUST_WAIT_FOR_PHASE5`; still waits on P6-004/D6-04 |
| P6-006 Advanced Navigation + Search/Filter | EARLY_START_ELIGIBLE | own contract + HPO READY promotion | **Strongest early-start candidate** — consumes only Phase 4 primitives; no Phase 5 dependency |
| P6-007 Source/Translation Comparison UX | CONDITIONAL | P5 persisted translation shape (available) + P6-002 read layer + contract | Browser required; Phase 5 gate now satisfied |
| P6-008 Revision History / Audit Surface | CONDITIONAL | P6-002 DONE + D6-02 selecting persistent history | Cancelled and excluded from the P6-009 gate if D6-02 excludes history |
| P6-009 Phase 6 Integration Verification | FINAL_GATE_ONLY | P6-003..P6-008 DONE | Browser-heavy; runs at the Phase 6 terminal gate only |

## D. Phase 7 eligibility matrix (post-Phase 5)

| Task | Class | Blocking prerequisite | Notes |
|---|---|---|---|
| P7-001 Production Configuration + Env Validation | CONDITIONAL | HPO early authorization + D7-* for full scope | Env validation/fail-fast is independent; bounded scope may start early; must reconcile P5/P6 config keys |
| P7-002 Production Data Store Decision + Migration | MUST_WAIT | Phase 6 CLOSED + D7-01 | Needs final P5/P6 tables |
| P7-003 Queue / Worker Supervision + Recovery | EARLY_START_ELIGIBLE | own contract + HPO READY promotion | P5-003 DONE so translation queue reconciliation is available; real Redis required |
| P7-004 Storage Strategy + Streaming Hardening | MUST_WAIT | Phase 6 CLOSED + D7-03 | Must include translation/revision artifacts |
| P7-005 Observability Foundation | EARLY_START_ELIGIBLE | own contract + HPO READY promotion | Independent of final P5/P6 schemas; add translation correlation later |
| P7-006 Security Hardening Baseline | CONDITIONAL | HPO early authorization (baseline scope only) | Tenancy/authorization redesign reserved (DC-02) |
| P7-007 Backup / Restore + Disaster Recovery | CONDITIONAL | tooling foundation early; final restore drill after Phase 6 CLOSED | RPO/RTO = D7-07 |
| P7-008 Deployment, Migration Safety, Rollback | EARLY_START_ELIGIBLE | own contract + HPO READY promotion | Schema-agnostic foundation; reconcile P5/P6 migrations later |
| P7-009 Performance / Load / Large-File Validation | CONDITIONAL | harness foundation early; final capacity targets = D7-08 after Phase 6 | Include translation concurrency in final run |
| P7-010 Browser Support Matrix + Flake Elimination | EARLY_START_ELIGIBLE | own contract + HPO READY promotion + §13.H allowlisting for legacy debt | Carries P1–P4 debt (`showRenameModal`, V4-08 flake); browser binaries |
| P7-011 Retention, Derived-Artifact + Orphan Cleanup | MUST_WAIT | Phase 6 CLOSED + D7-06 (and P7-004) | Must include translation/revision artifacts |
| P7-012 Production Readiness Gate | FINAL_GATE_ONLY | Phase 6 CLOSED + all P7 DONE | Must not run before Phase 6 CLOSED |

## E. Summary

- `READY_NOW`: **none** — no P6/P7 contract exists and none is HPO-promoted.
- `EARLY_START_ELIGIBLE` (Phase 6): P6-002, P6-004, P6-006.
- `EARLY_START_ELIGIBLE` (Phase 7): P7-003, P7-005, P7-008, P7-010.
- `CONDITIONAL`: P6-001, P6-003, P6-005, P6-007, P6-008; P7-001, P7-006, P7-007,
  P7-009.
- `MUST_WAIT`: P7-002, P7-004, P7-011.
- `FINAL_GATE_ONLY`: P6-009, P7-012.

## F. Recommended next authorized batch

Because no P6/P7 contract exists and all D6/D7/DC decisions are OPEN, the next
safe batch is a **decision + contract-authoring batch**, not implementation:

1. **HPO decision batch (Phase 6 baseline):** resolve D6-01..D6-09 and DC-01, and
   authorize Phase 6 for contract authoring (P6-001 first).
2. **Optional first early-start implementation candidate:** authorize P6-006
   (Advanced Navigation + Search/Filter) — the only Phase 6 candidate with no
   Phase 5 dependency — for a bounded contract + implementation once D6-07 is
   resolved. It consumes only Phase 4 primitives.
3. **Optional Phase 7 early-hardening candidate:** authorize a bounded P7-005
   (Observability Foundation) contract, which is independent of final P5/P6
   schemas. P7-006/P7-007/P7-009 may follow only with their bounded prerequisites
   named.

Do **not** start P6-001/P6-002/P6-003/P6-004/P6-005/P6-007/P6-008 or any P7 task
without the prerequisite decision/contract and an explicit HPO READY promotion.

## G. HPO decisions still required before execution resumes

- Resolve D6-01..D6-09 (editing model, undo/redo/version semantics, timestamp
  editing, split/merge + translation invalidation, revision history, comparison,
  navigation/search scope, speaker/annotations/bookmarks, waveform) and DC-01
  (browser verification governance across P6/P7).
- Decide whether to authorize Phase 6 contract authoring, and whether to
  separately authorize the P6-006 early-start exception.
- Resolve D7-* only when Phase 7 is taken up; D7-01/D7-03/D7-06/D7-08 gate the
  `MUST_WAIT` Phase 7 tasks.
- Confirm whether Phase 7 early-hardening (P7-003/P7-005/P7-008/P7-010) is
  authorized in parallel now or deferred until Phase 6 closure.

## H. Explicit Non-Actions

- This artifact does not promote any task to READY, authorize any batch, or
  authorize Phase 6/7 implementation.
- No application, schema, test, migration, worker, or configuration change is
  authorized.
- Phase 6/7 remain NOT GENERALLY AUTHORIZED; the ADR-023 allowlists are the only
  early-start paths and each still requires its own contract and HPO promotion.

## I. Executed batch — 2026-09-23 (post-authorization)

Authorized by `DECISION-PHASE6-AUTHORIZATION-001`,
`DECISION-PHASE6-OWNER-DECISIONS-001`, `DECISION-P6-006-AUTHORIZATION-001`, and
`DECISION-P7-005-AUTHORIZATION-001`.

- `tasks/P6-001-phase6-editing-domain-contract.md` — **READY** (contract authored;
  dependencies reconciled). Implementation is the next Phase 6 mainline step.
- `tasks/P6-006-advanced-navigation-search-filter.md` —
  **IMPLEMENTED_PENDING_REVIEW** (independence confirmed; implementation + 6
  feature tests; real-Chromium browser verification 2/2).
- `tasks/P7-005-observability-foundation.md` — **IMPLEMENTED_PENDING_REVIEW**
  (structured logging, request correlation, job observability, diagnostics,
  runbook; 11 focused tests).
- No D6-08/D6-09 feature was implemented; no other P7 task was started.

## J. Next batch recommendation

1. **Phase 6 mainline:** implement P6-001 (domain/contract foundation), then
   reconcile P6-002 against it (`P6-001 DONE → P6-002`).
2. Maintain P6-006/P7-005 at `IMPLEMENTED_PENDING_REVIEW` until a fresh
   independent review returns VERIFIED and the HPO closes them.
3. Do not start P6-003/P6-004/P6-005/P6-007/P6-008 or any other P7 task without a
   contract and explicit HPO READY promotion; D6-02's history decision and D7-*
   remain to be resolved where they gate a task.

## K. Reconciliation — 2026-09-23 (post corrective re-review)

Supersedes the "current eligibility" task-state lines above; historical
sections are preserved.

- **P6-006 = DONE** (`DECISION-P6-006-CLOSURE-001`), closed on the fresh
  independent VERIFIED corrective re-review
  (`reviews/P6-006-P7-005-corrective-independent-re-review.md`).
- **P7-005 = DONE** (`DECISION-P7-005-CLOSURE-001`), closed on the same VERIFIED
  re-review. No other Phase 7 task is authorized.
- **Residual LOW/INFO debt carried forward non-blocking**
  (`DECISION-PHASE6-7-DEBT-CARRYFORWARD-001`): order-dependent full-suite
  flakiness; ADR-017 `device` field; Phase 4 playback timing flake; pre-existing
  `showRenameModal` console error. Tasks are not reopened for these.
- **P6-001 = IMPLEMENTED_PENDING_REVIEW.** Canonical editing contract
  (`PHASE6-EDITING-DOMAIN-CONTRACT.md`) authored; domain primitives implemented
  in `app/Editing/` with 30 unit tests. Awaiting fresh independent review. No
  self-verification.
- **P6-002 contract authored** (`tasks/P6-002-revision-persistence-version-history.md`),
  status `CONTRACT_AUTHORED_PENDING_P6-001_CLOSURE`. **Not READY; not
  implemented.** Its own READY promotion requires P6-001 VERIFIED + an explicit
  HPO promotion.
- Executed batch: governance closure (P6-006/P7-005), P6-001 implementation,
  P6-002 contract authoring. No P6-003/P6-004/P6-005/P6-007/P6-008/P6-009 and no
  further P7 work was started.

Recommended next batch: fresh independent review of P6-001; if VERIFIED, HPO
closes P6-001 DONE and promotes P6-002 to READY for implementation.