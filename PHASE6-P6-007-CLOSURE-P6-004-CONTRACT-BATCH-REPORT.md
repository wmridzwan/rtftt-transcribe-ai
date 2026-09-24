# Phase 6 — P6-007 Closure + P6-004 Contract Batch Report

Date: 2026-09-24
Tasks: P6-007 (Source / Translation Comparison) — closure; P6-004 (Timing Editing
+ Validation) — canonical contract authoring
Status: **P6-007 = DONE; P6-004 = CONTRACT_AUTHORED (not READY, not implemented)**
Authority: `DECISION-P6-007-CLOSURE-001`; `DECISION-P6-007-SCOPE-001`;
`DECISION-P6-003-P6-007-READY-BATCH-001`; `DECISION-P6-003-CLOSURE-001`;
`reviews/P6-003-P6-007-independent-review.md`; D6-03; DC-01; ADR-025.

## 0. Explicit confirmations

- **P6-007 was accepted VERIFIED and closed DONE** by the HPO
  (`DECISION-P6-007-CLOSURE-001`): the fresh independent corrective re-review
  confirmed the original MEDIUM truthfulness defect closed and returned VERIFIED
  with no remaining BLOCKER/HIGH/MEDIUM/LOW/INFO finding. The historical review
  artifact and the corrective provenance are preserved unchanged; no historical
  finding was rewritten.
- **No P6-004 implementation occurred.** P6-004 was authored only and was **not**
  promoted to READY.
- **P6-005 was not authored, implemented, or promoted.** It remains
  dependency-blocked until P6-004 is DONE.
- **No P6-008 / P6-009 / additional Phase 7 work occurred.**
- **No staleness was inferred or persisted; no schema change.**

## 1. P6-007 closure

- Fresh independent corrective re-review verdict: **VERIFIED** with no remaining
  BLOCKER/HIGH/MEDIUM/LOW/INFO. Independently confirmed: the no-translation state
  is factual; no false "Edited after the translation was produced" claim remains;
  machine-source provenance is preserved when a persisted translation exists;
  structurally incompatible revisions are never silently index-remapped; no
  staleness is inferred or persisted; the comparison remains presentation-only
  (no writes); P6-003 shared-workspace behavior remains green; the real-browser
  (DC-01) evidence was independently reproduced.
- Transition `VERIFIED → DONE` recorded in `DECISIONS.md` / `DECISION_QUEUE.md`
  (`DECISION-P6-007-CLOSURE-001`) and `tasks/P6-007-source-translation-comparison.md`.
- Frozen downstream semantics recorded (task file "Frozen downstream semantics";
  `DECISION_QUEUE.md` / `DECISIONS.md` entries): Phase 5 translations align to the
  machine `segment_index`; P6-007 does not own the translation lifecycle and does
  not persist or infer staleness; edited-revision ↔ machine-translation provenance
  must remain explicit; structurally incompatible revisions must not be silently
  aligned.

## 2. P6-004 contract summary

Canonical contract: `tasks/P6-004-timing-editing-validation.md`
(`CONTRACT_AUTHORED — PENDING HPO READY PROMOTION`).

- Edits only the active revision's per-segment `start_seconds` / `end_seconds`;
  append-only, expected-base CAS, no in-place mutation; machine timing immutable.
- A dedicated composer (`TimingEditComposer`, analogous to `TextEditComposer`)
  rebuilds the ordered segment list, replacing timing only and re-validating
  through the ordinary `RevisionSegmentData` / `TimingInvariants` invariants.
- Timing-only edits classify as `EditKind::Timing` →
  `TranslationStalenessReason::TimingChanged`; **no** translation-staleness
  persistence (P6-005 owns it); mixed saves follow the frozen precedence.
- UX: timing edit mode, save/cancel, server-canonical validation feedback, stale
  conflict UX, visible current timing, accessible/keyboard controls, revision
  indicator; no split/merge.
- Real-browser (DC-01) verification is required with the acceptance scenarios in
  §4 below.

## 3. Timing invariants frozen (restated, not redefined)

Finite; non-negative; `start <= end`; millisecond precision (≤3 fractional
digits, no silent rounding); overlaps legal; zero-length legal but never active;
no cross-segment timestamp monotonicity; ordering by revision `position`, not
timestamp; machine timestamps immutable. Timing edits apply only to editable
revisions.

Multi-segment behavior explicitly defined: overlap creation, nested overlap,
equal starts, equal ends, zero-length, out-of-time-order positions, and editing
one segment past another's range are all legal; no hidden monotonicity
constraint may reject them.

## 4. Playback / navigation implications

- After an editable revision becomes active, playback seek targets and
  active-segment resolution use the **active revision's** timing; with no active
  revision, machine timing is used.
- Active-segment resolution remains the frozen half-open, lowest-`position` rule;
  zero-length segments are never active.
- Navigation continues to use the active revision's `position` (`nav_index`), not
  machine `segment_index`.
- Reserved Phase 4 hooks (`data-seek-seconds`, `data-segment-language`) and
  P6-006 hooks are not re-owned; P6-004 adds dedicated `data-timing-*` hooks.
- Machine-source timing is never mutated.

## 5. Browser requirements

DC-01 scenarios: valid timing edit; invalid negative time; `start > end`;
zero-length; overlap; reload durability; stale conflict; cancel/no write;
machine-source immutability; ownership denial; playback/navigation uses the active
revision timing after save. Evidence must record steps, environment, fixture
identity, and observed vs expected state.

## 6. READY eligibility and remaining owner decisions

- **P6-004 is READY-eligible**: canonical contract exists and dependencies are
  satisfied (P6-001 DONE, P6-002 DONE, P6-003 DONE, D6-03 adopted, Phase 4/P6-006
  primitives available; no Phase 5/P6-005 dependency).
- **Remaining owner decision:** explicit HPO READY promotion of P6-004
  (`DECISION-P6-004-READY-001`). This batch did **not** promote it.
- P6-005 remains dependency-blocked until P6-004 is DONE. P6-008 remains
  contract-gated; P6-009 remains FINAL_GATE_ONLY.

## 7. Changed files

Governance / task records:

- `tasks/P6-007-source-translation-comparison.md` (DONE + frozen downstream
  semantics)
- `tasks/P6-004-timing-editing-validation.md` (new — canonical contract)
- `DECISIONS.md`, `DECISION_QUEUE.md` (`DECISION-P6-007-CLOSURE-001`)
- `CURRENT_STATE.md`, `AGENTS.md`, `plan.md`
- `PHASE6-7-ELIGIBILITY-MATRIX.md` §Q
- `PHASE5-7-DEPENDENCY-GRAPH.md` (2026-09-24 Phase 6 status)
- this report

Unchanged / preserved:

- `reviews/P6-003-P6-007-independent-review.md` (historical CHANGES_REQUESTED
  review) and the corrective provenance
  (`PHASE6-P6-003-CLOSURE-P6-007-CORRECTIVE-BATCH-REPORT.md`, task-file corrective
  cycle).
- No application code, test, migration, or schema changed in this batch.

## 8. Record completeness (retained)

`reviews/P6-002-corrective-independent-re-review.md` remains absent; P6-002 is
**not** reopened and no artifact was fabricated or reconstructed from summaries.

## 9. Explicit non-actions

- No P6-004 implementation; P6-004 not promoted to READY.
- No P6-005/P6-008/P6-009 authoring, implementation, or promotion.
- No additional Phase 7 task; Phase 7 remains not generally authorized.
- No historical finding rewritten; no Phase 4/5/6 semantics redefined.