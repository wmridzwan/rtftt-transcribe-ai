# Phase 6 — P6-003/P6-007 Contract-Authoring Batch Report

Date: 2026-09-23
Scope: Bounded governance + contract-authoring batch (no implementation)
Authority: `DECISION-PHASE6-AUTHORIZATION-001`; `DECISION-PHASE6-OWNER-DECISIONS-001`
(ADR-025); `DECISION-P6-001-CLOSURE-001`; `DECISION-P6-002-CLOSURE-001`;
`DECISION-P6-007-SCOPE-001`; frozen `PHASE6-EDITING-DOMAIN-CONTRACT.md`

## 1. P6-002 independent-review record

- **Not restored.** `reviews/P6-002-corrective-independent-re-review.md` remains
  absent. A recovery search found no such file and no `storage/orchestration`
  directory.
- Per the batch instruction, **no reviewer evidence was fabricated or
  reconstructed from builder prose**; the record-completeness subtask is stopped
  and the gap is reported.
- P6-002 is **not reopened**; it remains DONE (`DECISION-P6-002-CLOSURE-001`) on
  the HPO-accepted VERIFIED verdict.
- Action required: the accepted reviewer should retain/add the actual artifact so
  the VERIFIED verdict is independently reproducible.

## 2. P6-007 owner decision recorded

`DECISION-P6-007-SCOPE-001` (HPO 2026-09-23): **P6-007 Source/Translation
Comparison is presentation-only for its required Phase 6 scope.** P6-007 may
display machine source, active revision, persisted Phase 5 translation content,
and comparison relationships; it must not own or persist translation
invalidation; staleness display is optional and must degrade gracefully without
inference; a later P6-005 marker may be consumed through an explicit contract
without ownership. Recorded in `DECISION_QUEUE.md` and `DECISIONS.md`.

## 3. P6-003 contract summary

`tasks/P6-003-text-editing-undo-redo.md` — `CONTRACT_AUTHORED — PENDING HPO READY
PROMOTION`.

- **Editing behavior:** edit active-revision segment text only; machine source
  immutable; editing from machine source materializes the initial revision and
  appends the text edit; editing from an existing active revision appends a new
  revision; no in-place revision mutation; text-only change preserves identity,
  position, timing, language.
- **Concurrency:** every save states the expected active/base revision; stale base
  → `RevisionConflictException`; no silent merge; rejected save changes nothing.
- **Undo/redo:** strict-ancestor undo and unique-child redo (verified P6-002
  semantics); new edit after undo branches and invalidates automatic redo; old
  history durable.
- **Translation invalidation:** text edit = `EditKind::Textual` →
  `SourceTextChanged`; no staleness persistence (P6-005 owns
  `translations.stale_at`/`staleness_reason`).
- **Workspace integration:** edit mode; save/cancel; visible active-revision
  state; accessible conflict UX; keyboard/accessibility; P6-006 compatibility;
  preservation of reserved Phase 4 hooks (dedicated `data-edit-*` hooks).
- **Browser verification:** required (successful edit, cancel, stale conflict,
  undo, redo, branch-after-undo, reload durability, ownership denial, source
  immutability).

## 4. P6-007 contract summary

`tasks/P6-007-source-translation-comparison.md` — `CONTRACT_AUTHORED — PENDING HPO
READY PROMOTION`.

- **Comparison sources:** machine source vs active revision; source/revision vs
  translation; alignment by the existing persisted relationship where valid.
- **Alignment baseline (recorded fact):** `translation_segments.segment_index`
  aligns to the machine `transcription_segments.segment_index`; there is no
  persisted revision linkage, so a persisted translation is a translation of the
  machine source. The initial materialized revision is textually equivalent
  (`machine:<index>`); a text-edited revision is not.
- **Presentation-only boundary:** no source/revision mutation, no invalidation
  persistence, no silent remap, no translation-lifecycle ownership, no schema
  change.
- **Missing/stale state:** show only persisted facts; never infer freshness; do
  not block comparison because P6-005 is incomplete; a later P6-005 marker may be
  consumed without ownership.
- **Alignment behavior:** machine-aligned translation shown as machine-source
  translation; edited-revision mismatch made explicit, never silently aligned;
  unavailable translation is a first-class state; structurally incompatible
  future revision state does not map by `segment_index`.
- **Browser verification:** required (comparison toggle/view, source vs active
  revision, source/revision vs translation where available, no-translation state,
  authorization/isolation, no mutation).

## 5. Dependency status

| Task | Contract | Dependencies | READY-eligible now |
|---|---|---|---|
| P6-003 | Authored | P6-001 DONE, P6-002 DONE, Phase 4 primitives DONE, P6-006 DONE, DC-01 | No — explicit HPO READY required |
| P6-007 | Authored | Phase 5 CLOSED, P6-002 DONE, P6-001 frozen, P6-006 DONE, `DECISION-P6-007-SCOPE-001`, DC-01; P6-005 not a dependency | No — explicit HPO READY required |

Both binding implementation dependencies are satisfied; neither is promoted. The
batch stops at the READY boundary per `DECISION-PHASE6-AUTHORIZATION-001`.

## 6. Browser / integration requirements

- Both tasks require real-browser verification (DC-01) and feature tests.
- Both are P6-009 terminal-gate inputs.

## 7. Shared workspace-file collision risk

- Concrete: `resources/views/transcriptions/show.blade.php` (the single transcript
  workspace file containing `transcriptPlayback`, `transcriptSearch`, row markup,
  and reserved/owned `data-*` hooks) is required by both P6-003 and P6-007.
- Parallel-safe only if the workspace is partitioned (distinct Blade
  partials/includes and distinct Alpine components/regions) or explicit file
  ownership is assigned; otherwise sequence the tasks.
- Neither task may modify the frozen P6-001/P6-002 semantics or the Phase 5
  translation schema.

## 8. Explicit HPO decisions still required

- Promote **P6-003** to READY.
- Promote **P6-007** to READY.
- Retain/add `reviews/P6-002-corrective-independent-re-review.md`.
- (Not addressed here, unchanged) P6-008 explicit-selection semantics; P6-005
  contract-only items if they exceed D6-04.

## 9. Governance files updated

- `DECISION_QUEUE.md` — `DECISION-P6-007-SCOPE-001`.
- `DECISIONS.md` — P6-007 Scope section.
- `tasks/P6-003-text-editing-undo-redo.md` — new canonical contract.
- `tasks/P6-007-source-translation-comparison.md` — new canonical contract.
- `PHASE6-7-ELIGIBILITY-MATRIX.md` — §N reconciliation.
- `CURRENT_STATE.md`, `AGENTS.md`, `plan.md`, `PHASE5-7-DEPENDENCY-GRAPH.md` —
  Phase 6 state reconciled.

## 10. Confirmation: no implementation occurred

- No P6-003 or P6-007 implementation (or any other task) was started.
- P6-004, P6-005, P6-008, P6-009 were not authored or implemented; P6-008
  explicit-selection semantics were not resolved.
- No new Phase 7 task was authored, promoted, or implemented; P7-005 remains DONE
  and Phase 7 remains not generally authorized.
- No application, schema, test, migration, route, controller, view, JavaScript,
  worker, or configuration change was made.
