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

## L. Reconciliation — 2026-09-23 (P6-001 closure + P6-002 READY/implementation)

Supersedes the "current eligibility" task-state lines above; historical sections
are preserved.

- **P6-001 = DONE** (`DECISION-P6-001-CLOSURE-001`), closed on the fresh
  independent corrective re-review
  (`reviews/P6-001-corrective-independent-re-review.md`) which returned
  **VERIFIED** with no remaining BLOCKER/HIGH/MEDIUM. Its final domain semantics
  are recorded as frozen inputs (`PHASE6-EDITING-DOMAIN-CONTRACT.md` §0).
- **P6-002 = READY → IMPLEMENTED_PENDING_REVIEW**
  (`DECISION-P6-002-READY-001`). The HPO promoted P6-002 to READY after P6-001
  closure; implementation (migrations, models, `EloquentRevisionRepository`,
  `MachineSourceMaterializer`, authorization-fenced `RevisionService`, feature
  tests incl. a genuine two-process append race) is complete and awaiting a fresh
  independent review. Not VERIFIED; not DONE.
- No blanket READY: every other Phase 6 task still requires its own contract and
  an explicit HPO READY promotion. **P6-003/P6-004/P6-005/P6-007/P6-008/P6-009
  were not started.**
- Phase 7 remains NOT GENERALLY AUTHORIZED; no Phase 7 work was started.
- Recommended next batch: fresh independent review of P6-002; if VERIFIED, HPO
  closes P6-002 DONE. Do not start P6-003/P6-004/P6-005/P6-007/P6-008/P6-009 or
  any Phase 7 task without their prerequisites and an explicit HPO promotion.

## M. Reconciliation — 2026-09-23 (P6-002 DONE + remaining Phase 6 candidates)

Supersedes the "current eligibility" task-state lines above; all historical
sections are preserved unchanged.

### M.1 P6-002 closure

- **P6-002 = DONE** (`DECISION-P6-002-CLOSURE-001`), closed on the fresh
  independent corrective re-review verdict **VERIFIED** (no
  BLOCKER/HIGH/MEDIUM/LOW/INFO remaining). The original MEDIUM finding (undo did
  not enforce strict ancestry) is resolved; `RevisionService::undo()` activates
  only a strict ancestor of the current active revision and rejects
  self/sibling/cousin/descendant/abandoned-branch/unrelated/cross-transcription/
  unknown targets; active-pointer CAS still rejects stale writes; rejected calls
  leave persistence unchanged; redo semantics, ownership/isolation, version
  allocation, transactional rollback, machine-source immutability, schema
  constraints, and race handling remain intact; Eloquent and in-memory behavior
  remain aligned. Historical artifacts preserved:
  `reviews/P6-002-independent-review.md`,
  `reviews/pre-review/P6-002-pre-review.md`,
  `reviews/pre-review/P6-002-corrective-pre-review.md`. Record-completeness note:
  the reviewer-owned `reviews/P6-002-corrective-independent-re-review.md` was not
  present in the working tree at reconciliation time and must be retained/added.
- **P6-001/P6-002 foundation = FROZEN downstream input.** Remaining Phase 6 tasks
  consume, and must not redefine: immutable machine source + append-only editable
  revisions + one active pointer + durable graph/history; branching after undo;
  the revision persistence schema (`transcript_revisions`,
  `transcript_revision_segments`, `transcriptions.active_revision_id`);
  transcription-scoped monotonic version with unique `(transcription_id, version)`;
  durable `parent_revision_id` ancestry; active-pointer CAS; transactional conflict
  translation; strict-ancestor-only undo; deterministic unique-child redo; durable
  old branches; overlap-legal / zero-length-legal-but-never-active timing;
  active-revision identity/position navigation with immutable machine timing; and
  translation-invalidation precedence
  `SegmentStructureChanged > TimingChanged > SourceTextChanged`.

### M.2 Remaining Phase 6 candidate classification

Classification vocabulary for this reconciliation: `READY_CANDIDATE`,
`CONTRACT_REQUIRED`, `OWNER_DECISION_REQUIRED`, `DEPENDENCY_BLOCKED`,
`FINAL_GATE_ONLY`.

| Task | Class | Contract state | Owner decision | Browser | Translation invalidation / revision semantics | Parallel safety |
|---|---|---|---|---|---|---|
| P6-003 Segment Text Editing UI + Undo/Redo | `CONTRACT_REQUIRED` | No `tasks/P6-003-*`; must be authored | None missing (D6-01/D6-02/D6-05 adopted) | Required | Consumes revision semantics (writes via `RevisionService`); textual edits are invalidating per policy but does not persist staleness | Parallel with P6-007 (wave P6-3); coordinate with P6-004 (shared edit workspace) |
| P6-004 Timing Editing + Validation | `CONTRACT_REQUIRED` | No `tasks/P6-004-*`; must be authored | None missing (D6-03 adopted; timing invariants frozen) | Required | Consumes revision semantics; timing edits are invalidating per policy but does not persist staleness | Domain-independent; shares edit workspace with P6-003 — sequence or coordinate |
| P6-005 Split/Merge + Translation Invalidation | `DEPENDENCY_BLOCKED` (on P6-004 DONE); contract also required | No `tasks/P6-005-*`; must be authored and explicit | D6-04 adopted; if the staleness-marker schema or identity/language-carry rules exceed D6-04, an owner decision is required | Required | Primary owner of translation-invalidation persistence; high-risk structural editing | Not parallel (single wave P6-5) |
| P6-007 Source/Translation Comparison UX | `CONTRACT_REQUIRED` | No `tasks/P6-007-*`; must be authored | Candidate: decide whether comparison must surface translation staleness (creates a soft P6-005 dependency) | Required | Presentation semantics over persisted source (active revision) + translation; does not persist invalidation | Parallel with P6-003 (wave P6-3) |
| P6-008 Revision History / Audit Surface | `CONTRACT_REQUIRED` | No `tasks/P6-008-*`; must be authored | Candidate: pin explicit-selection semantics (arbitrary same-transcription activation vs bounded); must be authorization/ownership/CAS/history fenced | Required | Reads the durable revision graph; must not bypass authorization, ownership, active-pointer CAS, or history semantics; must not redefine undo/redo | Depends only on P6-002 (DONE); independent of P6-003/004/005/007 — parallelizable once contracted, subject to shared workspace-file coordination |
| P6-009 Phase 6 Integration Verification | `FINAL_GATE_ONLY` | Authored only at the terminal gate | None | Required (browser-heavy) | Verifies revision + invalidation semantics; redefines none | Not parallel (runs at the Phase 6 terminal gate only) |

Dependency status summary: P6-002 is now DONE, so P6-003/P6-004/P6-007/P6-008
have their only binding implementation dependency satisfied and are gated solely
by contract authoring (+ HPO READY promotion). P6-005 remains gated on P6-004
DONE. P6-009 remains gated on P6-003..P6-008 DONE.

### M.3 Recommended next batch

1. **Contract-authoring batch (no implementation):** author canonical contracts
   for P6-003 and P6-007 (wave P6-3), then P6-004 (wave P6-4) and P6-008 (wave
   P6-6). P6-008's contract must define explicit historical selection as an
   authorization-fenced, CAS-fenced service operation that reuses the verified
   repository primitive without bypassing ownership/history semantics.
2. **First bounded implementation wave:** P6-003 + P6-007 (wave P6-3;
   parallelizable, both browser-verified) after their contracts exist and the HPO
   promotes them to READY.
3. **Then** P6-004 (wave P6-4), then P6-005 (wave P6-5, after P6-004 DONE), then
   P6-008 (wave P6-6), then the P6-009 terminal gate.

### M.4 Explicit owner decisions still required

- **P6-007:** whether comparison must surface translation staleness (soft P6-005
  dependency) or remain presentation-only.
- **P6-008:** explicit historical selection semantics (arbitrary same-transcription
  activation vs bounded) and confirmation that it is authorization/ownership/CAS
  fenced and does not redefine undo/redo.
- **P6-005:** only if its contract needs anything beyond D6-04 (e.g. the
  `translations.stale_at`/`staleness_reason` shape or split/merge identity/language
  carry rules) — otherwise D6-04 already delegates these to the contract.
- **Record completeness:** retain/add
  `reviews/P6-002-corrective-independent-re-review.md` as the durable evidence for
  the accepted P6-002 VERIFIED verdict.

### M.5 Parallel execution

- Safe in parallel once contracted: P6-003 + P6-007 (wave P6-3).
- P6-008 is independent of P6-003/P6-004/P6-005/P6-007 and may run alongside them
  once contracted, subject to shared transcript-workspace file coordination.
- P6-003 and P6-004 share the edit workspace and revision service; sequence them or
  assign explicit file ownership rather than running them truly in parallel.
- P6-005 and P6-009 are not parallel candidates.

### M.6 Explicit non-actions (this reconciliation)

- No new Phase 6 or Phase 7 implementation was started; P6-003/P6-004/P6-005/
  P6-007/P6-008/P6-009 were not started and no task was promoted to READY.
- Phase 7 remains NOT GENERALLY AUTHORIZED; P7-005 remains DONE; no other Phase 7
  task is authorized or started.
- This reconciliation is governance/state recording only; it authorizes no
  application, schema, test, migration, worker, or configuration change.

## N. Reconciliation — 2026-09-23 (P6-003/P6-007 contracts authored)

Supersedes the "current eligibility" task-state lines above; all historical
sections are preserved unchanged. This is a bounded governance +
contract-authoring batch: no implementation occurred.

### N.1 Contracts authored

- `tasks/P6-003-text-editing-undo-redo.md` — canonical P6-003 contract
  (segment text editing + undo/redo), consuming the frozen P6-001/P6-002
  foundation.
- `tasks/P6-007-source-translation-comparison.md` — canonical P6-007 contract
  (presentation-only source/revision/translation comparison), consuming
  `DECISION-P6-007-SCOPE-001`.

Both are `CONTRACT_AUTHORED — PENDING HPO READY PROMOTION`; neither is READY and
neither is implemented.

### N.2 P6-007 owner decision recorded

`DECISION-P6-007-SCOPE-001` (HPO 2026-09-23): P6-007 is **presentation-only** for
its required Phase 6 scope. It may display machine source, active revision,
persisted Phase 5 translation content, and comparison relationships; it must not
own or persist translation invalidation; staleness display is optional and must
degrade gracefully without inference; if a later P6-005 marker exists, P6-007 may
consume it through an explicit contract without owning it. This preserves P6-007
independence from P6-005. Recorded in `DECISION_QUEUE.md` and `DECISIONS.md`.

### N.3 Dependency status and READY eligibility

| Task | Contract | Dependencies | READY-eligible now? | Browser | Collision risk |
|---|---|---|---|---|---|
| P6-003 Text Editing + Undo/Redo | Authored | P6-001 DONE, P6-002 DONE, Phase 4 primitives DONE, P6-006 DONE; DC-01 | **No** — requires explicit HPO READY promotion | Required | Shares `resources/views/transcriptions/show.blade.php` with P6-007 |
| P6-007 Source/Translation Comparison | Authored | Phase 5 CLOSED, P6-002 DONE, P6-001 frozen, P6-006 DONE; `DECISION-P6-007-SCOPE-001`; DC-01; P6-005 **not** a dependency | **No** — requires explicit HPO READY promotion | Required | Shares `resources/views/transcriptions/show.blade.php` with P6-003 |

Both tasks have their binding implementation dependencies satisfied. Neither is
promoted by this batch: `DECISION-PHASE6-AUTHORIZATION-001` requires an explicit
HPO READY promotion per task after its contract exists and dependencies are
reconciled. **This batch stops at the READY boundary.**

### N.4 Browser / integration requirements

- P6-003: real-browser evidence required (successful edit, cancel, stale
  conflict, undo, redo, branch-after-undo, reload durability, ownership denial,
  source immutability) plus feature tests.
- P6-007: real-browser evidence required (comparison toggle/view, source vs
  active revision, source/revision vs translation where available,
  no-translation state, authorization/isolation, no mutation) plus feature tests
  and a no-mutation guarantee.
- Both are P6-009 integration-gate inputs.

### N.5 Shared workspace-file collision risk

- Concrete collision: `resources/views/transcriptions/show.blade.php` is the
  single transcript workspace file (contains `transcriptPlayback`,
  `transcriptSearch`, row markup, and the reserved/owned `data-*` hooks). Both
  P6-003 and P6-007 are required to modify it.
- P6-003/P6-007 may remain parallel-safe **only if** the implementation partitions
  the workspace (distinct Blade partials/includes and distinct Alpine
  components/regions) or otherwise assigns explicit file ownership. Otherwise
  sequence them.
- Neither task may modify the frozen P6-001/P6-002 semantics or the Phase 5
  translation schema.

### N.6 P6-002 record-completeness gap (unresolved)

The reviewer-owned artifact `reviews/P6-002-corrective-independent-re-review.md`
remains **absent** from the repository. A recovery search was performed (no such
file; `storage/orchestration` does not exist). Per the batch instruction, no
evidence was fabricated or reconstructed from builder prose; the record-completeness
subtask is stopped and the gap is reported. This does not reopen P6-002, which
remains DONE under `DECISION-P6-002-CLOSURE-001` on the HPO-accepted VERIFIED
verdict. The accepted reviewer should retain/add the actual artifact; until then
the VERIFIED verdict is not independently reproducible from a durable reviewer
file.

### N.7 Explicit HPO decisions still required

- **Promote P6-003 to READY** (after its contract + dependency reconciliation).
- **Promote P6-007 to READY** (after its contract + `DECISION-P6-007-SCOPE-001`).
- **Record completeness:** retain/add
  `reviews/P6-002-corrective-independent-re-review.md`.
- (Unchanged from §M.4, not addressed here: P6-008 explicit-selection semantics;
  P6-005 contract-only items if they exceed D6-04.)

### N.8 Explicit non-actions (this batch)

- No implementation of P6-003 or P6-007 (or any task) was started.
- P6-004, P6-005, P6-008, P6-009 were not authored or implemented; P6-008
  explicit-selection semantics were not resolved.
- No new Phase 7 task was authored, promoted, or implemented; P7-005 remains DONE
  and Phase 7 remains not generally authorized.
- No application, schema, test, migration, worker, or configuration change was
  made.

## O. Reconciliation — 2026-09-23 (P6-003/P6-007 HPO READY promotion + implementation)

Supersedes the "current eligibility" task-state lines above; all historical
sections are preserved unchanged.

### O.1 HPO READY promotion

- **P6-003 = READY**, promoted by the Human Product Owner
  (`DECISION-P6-003-READY-001`; batch recorded as
  `DECISION-P6-003-P6-007-READY-BATCH-001`). Its canonical contract exists and its
  binding dependencies (P6-001 DONE, P6-002 DONE, Phase 4 primitives, P6-006 DONE,
  DC-01) are satisfied.
- **P6-007 = READY**, promoted by the same decision. Its canonical contract exists,
  Phase 5 is CLOSED, P6-001/P6-002/P6-006 are DONE, and
  `DECISION-P6-007-SCOPE-001` freezes it as **presentation-only**.
- Both promotions are explicit per-task HPO actions; there is still no blanket
  READY for Phase 6.

### O.2 Execution order and workspace collision handling

- P6-003 and P6-007 both modify `resources/views/transcriptions/show.blade.php`
  (§N.5). Because no explicit file partitioning was established up front, the two
  tasks are **sequenced, not run concurrently**: P6-003 is implemented, tested,
  browser-verified, and pre-reviewed first; P6-007 is then implemented against the
  stabilized workspace.
- Collision mitigation used during implementation: P6-003 adds a dedicated
  workspace partial (`resources/views/transcriptions/partials/revision-toolbar.blade.php`)
  and a dedicated Alpine component (`transcriptEditing`) with its own `data-edit-*`
  hooks; P6-007 adds a separate comparison partial
  (`resources/views/transcriptions/partials/source-translation-comparison.blade.php`)
  with its own `data-compare-*` hooks. Neither reuses the reserved Phase 4 hooks
  (`data-seek-seconds`, `data-segment-language`) or P6-006's
  `data-filter-language` / `data-nav-seconds`.

### O.3 Implementation outcome

- **P6-003 = IMPLEMENTED_PENDING_REVIEW.** Text-editing + undo/redo inside the
  workspace, built on the frozen `RevisionService`; textual edits map to
  `EditKind::Textual` → `SourceTextChanged`; no translation-staleness persistence
  introduced.
- **P6-007 = IMPLEMENTED_PENDING_REVIEW.** Presentation-only source / active
  revision / persisted translation comparison; no writes, no staleness inference;
  machine-aligned translation labeled as such, edited revisions shown with an
  explicit mismatch.
- Neither task is self-verified; both await independent Claude Code review.

### O.4 Explicit non-actions (this batch)

- P6-004, P6-005, P6-008, P6-009 were not started.
- No additional Phase 7 task was started; Phase 7 remains not generally
  authorized.
- P6-002 is **not** reopened; the record-completeness gap for
  `reviews/P6-002-corrective-independent-re-review.md` remains open and no
  artifact was fabricated.
- P6-003/P6-007 redefine no P6-001/P6-002 semantics and persist no translation
  staleness.