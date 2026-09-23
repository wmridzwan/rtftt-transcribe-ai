# Phase 6 — P6-003 + P6-007 Implementation Batch Report

Date: 2026-09-23
Tasks: P6-003 (Text Editing + Undo/Redo); P6-007 (Source / Translation
Comparison)
Status: **P6-003 = IMPLEMENTED_PENDING_REVIEW; P6-007 =
IMPLEMENTED_PENDING_REVIEW** (neither self-verified, neither DONE)
Authority: `DECISION-P6-003-READY-001`, `DECISION-P6-007-READY-001`
(batch `DECISION-P6-003-P6-007-READY-BATCH-001`);
`DECISION-PHASE6-AUTHORIZATION-001`; `DECISION-PHASE6-OWNER-DECISIONS-001`
(ADR-025); `DECISION-P6-007-SCOPE-001`; `DECISION-P6-002-CLOSURE-001`; DC-01.

## 0. Explicit confirmations

- **Both tasks were explicitly HPO-promoted to READY before implementation**
  (`DECISION-P6-003-READY-001`, `DECISION-P6-007-READY-001`, recorded as
  `DECISION-P6-003-P6-007-READY-BATCH-001`). There was no blanket Phase 6 READY.
- **Neither task redefined P6-001/P6-002 semantics.** Both consume the frozen
  `PHASE6-EDITING-DOMAIN-CONTRACT.md` and the verified `RevisionService` /
  persistence unchanged; no schema, version, ancestry, redo, or conflict semantic
  was altered.
- **P6-007 did not persist or infer translation staleness.** It adds/reads no
  `translations.stale_at` / `staleness_reason` and renders only persisted facts.
- **No later P6/P7 task was started.** P6-004, P6-005, P6-008, P6-009 and every
  Phase 7 task (other than the already-DONE P7-005) were not started.
- **Neither task was self-marked VERIFIED.** Both are `IMPLEMENTED_PENDING_REVIEW`
  and are handed to Claude Code for independent review.

## 1. P6-003 — Text Editing + Undo/Redo

### Implementation summary

Segment text editing inside the existing transcript workspace, built entirely on
the frozen revision foundation:

- `TextEditComposer` composes a text-only edit, preserving identity, position,
  timing, and carried language; empty text is legal and distinct from deletion.
- `TranscriptRevisionController` (`store`/`undo`/`redo`) is an
  authorization-fenced HTTP boundary over `RevisionService`. Editing from the
  machine source materializes the durable initial machine-copy revision and then
  appends the edit derived from it; editing an active revision appends a new
  version. Undo moves to the immediate strict ancestor; redo moves to the unique
  child.
- Stale bases are rejected as the canonical conflict with no silent merge and no
  partial write; the workspace shows an accessible conflict message and a reload
  path.
- `TranscriptionController::show` now treats the active revision as authoritative
  for presentation when one exists, exposing the active revision id/version,
  undo/redo availability, and `canEdit`.
- The workspace adds a `transcriptEditing` Alpine component, a
  `revision-toolbar` partial (indicator, Edit/Save/Cancel, Undo/Redo, live-region
  status, flash messaging), and per-segment `data-edit-text` textareas inside a
  single form. Save is a native form-associated submit.

Preserved: immutable machine source; append-only revisions; expected-base
concurrency; no silent merge; strict-ancestor undo; unique-child redo;
branch-after-undo; P6-006 navigation/filter and reserved Phase 4 hooks.
`EditKind::Textual` → `SourceTextChanged`; no translation-staleness persistence.

### Changed files

- `app/Editing/TextEditComposer.php` (new)
- `app/Http/Controllers/TranscriptionController.php`
- `app/Http/Controllers/TranscriptRevisionController.php` (new)
- `routes/web.php`
- `resources/views/transcriptions/show.blade.php`
- `resources/views/transcriptions/partials/revision-toolbar.blade.php` (new)
- `tests/Unit/Editing/TextEditComposerTest.php` (new)
- `tests/Feature/Editing/TranscriptEditingWorkspaceTest.php` (new)
- `verification/p6-003-seed.php`, `verification/p6-003-auth.setup.js`,
  `verification/playwright.p6-003.config.js`,
  `verification/p6-003/*` (spec, README, evidence, results JSON)

### Browser evidence

`verification/p6-003/P6-003-BROWSER-VERIFICATION-EVIDENCE.md`; real Chromium:
**8 passed / 8** — enter edit mode, save, cancel, reload durability, stale
conflict, undo, redo, branch-after-undo, ownership denial, machine-source
immutability.

### Regression / quality

- Targeted: 18 passed / 85 assertions.
- Full PHP suite: 797 tests, 796 passed, 1 skipped, 0 failures (2 pre-existing
  warnings).
- Pint clean; PHPStan level 7: 0 errors.

### Residual findings

- Machine-source edit performs `materializeInitial` + `edit` as two separately
  CAS-fenced repository operations; a race between them leaves the materialized
  machine-copy revision active (non-corrupt, recoverable).
- Undo targets the immediate parent (single-step); explicit historical selection
  remains P6-008.
- Exports still read the machine source (P6-001 §9 / P6-009 gate item).

### Recommended independent-review focus

Append-only/machine-immutability and expected-base concurrency at the HTTP
boundary; P6-006 / reserved-hook compatibility and the textarea typing guard;
absence of translation-staleness persistence; ownership/isolation; browser
evidence reproducibility.

## 2. P6-007 — Source / Translation Comparison

### Implementation summary

Presentation-only comparison inside the workspace (`DECISION-P6-007-SCOPE-001`):

- `ComparisonBuilder` builds a read-only view model aligning the persisted
  Phase 5 translation to the **machine** `segment_index`, and aligning an active
  revision segment to a machine index only where its identity is machine
  provenance (`machine:<index>`). Non-machine identities become unaligned rows
  and are never index-mapped.
- `ComparisonRow` / `TranscriptComparison` carry only persisted facts; no
  freshness/staleness is derived.
- A `source-translation-comparison` partial renders a Transcript/Compare toggle
  and a read-only Machine source / Active revision / Translation table with
  dedicated `data-compare-*` hooks. It presents: machine authoritative (no
  revision), machine ↔ translation alignment, edited revision with an explicit
  mismatch note, no-translation state, and alignment-unavailable for structural
  changes.

No writes; no schema change; no lifecycle ownership.

### Changed files

- `app/Comparison/ComparisonRow.php`, `app/Comparison/TranscriptComparison.php`,
  `app/Comparison/ComparisonBuilder.php` (new)
- `app/Http/Controllers/TranscriptionController.php`
- `resources/views/transcriptions/show.blade.php`
- `resources/views/transcriptions/partials/source-translation-comparison.blade.php` (new)
- `tests/Feature/Comparison/SourceTranslationComparisonTest.php` (new)
- `verification/p6-007-seed.php`, `verification/p6-007-auth.setup.js`,
  `verification/playwright.p6-007.config.js`,
  `verification/p6-007/*` (spec, README, evidence, results JSON)

### Browser evidence

`verification/p6-007/P6-007-BROWSER-VERIFICATION-EVIDENCE.md`; real Chromium:
**8 passed / 8** — comparison toggle/view, source vs active revision,
source/revision vs translation, no-translation state, structural
alignment-unavailable, no mutation, authorization/isolation.

### Regression / quality

- Targeted: 8 passed / 47 assertions.
- Full PHP suite: 797 tests, 796 passed, 1 skipped, 0 failures.
- Pint clean; PHPStan level 7: 0 errors.

### Residual findings

- Structural revisions show their own segments as unaligned rows (revision text +
  "alignment unavailable", translation cell empty); machine rows read "No
  matching revision segment". Deliberate "do not guess" presentation.
- No target-language chooser: the newest completed translation is shown.
- P6-005 staleness, if later added, may only be consumed through a separately
  approved contract.

### Recommended independent-review focus

No writes / no staleness inference; alignment baseline correctness (edited vs
structural revisions never silently mapped); no-translation as a factual state;
P6-006 / reserved-hook integrity; isolation; browser evidence reproducibility.

## 3. Workspace collision handling

Both tasks target `resources/views/transcriptions/show.blade.php`. No explicit
file partitioning was established up front, so the tasks were **sequenced, not run
concurrently** (P6-003 first, then P6-007 against the stabilized workspace), per
the HPO execution order. Additional isolation:

- P6-003 owns `partials/revision-toolbar.blade.php`, the `transcriptEditing`
  Alpine component, and `data-edit-*` / `data-revision-*` hooks.
- P6-007 owns `partials/source-translation-comparison.blade.php`, the workspace
  `transcriptView` toggle, and `data-compare-*` hooks.
- Neither reuses the reserved Phase 4 hooks or P6-006's hooks.
- Both share only the workspace-level wrapper and the read-only revision/
  translation read layer; neither modifies the other's region.

## 4. Governance records updated

- `tasks/P6-003-text-editing-undo-redo.md`,
  `tasks/P6-007-source-translation-comparison.md` → `IMPLEMENTED_PENDING_REVIEW`.
- `PHASE6-7-ELIGIBILITY-MATRIX.md` §O (promotion, execution order, collision
  handling, outcome, non-actions).
- `DECISIONS.md`, `DECISION_QUEUE.md`
  (`DECISION-P6-003-P6-007-READY-BATCH-001`).
- `CURRENT_STATE.md` Phase 6 paragraph.

## 5. P6-002 evidence gap (retained)

`reviews/P6-002-corrective-independent-re-review.md` is still absent. P6-002 is
**not** reopened; no artifact was fabricated or reconstructed from summaries. The
accepted reviewer should add the actual artifact when available.
