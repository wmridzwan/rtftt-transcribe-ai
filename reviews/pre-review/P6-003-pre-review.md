# P6-003 — Pre-Review Handoff (Text Editing + Undo/Redo)

Date: 2026-09-23
Task: `tasks/P6-003-text-editing-undo-redo.md`
Status: `IMPLEMENTED_PENDING_REVIEW` (not VERIFIED, not DONE)
Authority: `DECISION-P6-003-READY-001` /
`DECISION-P6-003-P6-007-READY-BATCH-001`; `DECISION-PHASE6-AUTHORIZATION-001`;
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025; frozen
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; verified P6-002
(`DECISION-P6-002-CLOSURE-001`); DC-01
Reviewer: Claude Code (fresh independent review required)

## Scope discipline

Implemented only the canonical P6-003 scope:

- text editing of the **active revision's** segment text (no timing edits, no
  split/merge, no structural change);
- append-only revisions via the frozen `RevisionService`; no in-place mutation;
- expected-base optimistic concurrency; no silent merge;
- strict-ancestor undo; unique-child redo; branch-after-undo;
- workspace edit mode / save / cancel, active-revision indicator, conflict UX.

Not touched: P6-004/P6-005/P6-008/P6-009; no translation-staleness persistence
(P6-005 owns that); no Phase 7 work; no change to the frozen P6-001/P6-002
semantics or schema.

## 1. What was built

### Domain helper — `app/Editing/TextEditComposer.php` (new)

Narrow, text-only composition over a base revision's segments. It preserves each
segment's `RevisionSegmentIdentity`, `position`, timing, and carried language
verbatim and replaces only `text`; the result is rebuilt as fresh
`RevisionSegmentData` objects, so the normal per-segment / cross-segment
validation applies. It rejects a submission that does not supply exactly one text
per base segment (count mismatch / unknown position / non-string). An empty
string is a legal distinct value.

### HTTP boundary — `app/Http/Controllers/TranscriptRevisionController.php` (new)

Authorization-fenced (`authorize('update')`) thin wrapper over `RevisionService`:

- `store`: when `expected_base` is empty, `materializeInitial()` then `edit()`
  appends the text edit derived from the durable initial copy; otherwise it
  verifies the expected base equals the active revision (else
  `RevisionConflictException::staleBase()`) and appends `edit()`.
- `undo`: `RevisionService::undo()` with target + expected token.
- `redo`: `RevisionService::redo()` with expected token.

Domain outcomes map to workspace feedback: stale → `revision_conflict` (with a
reload path), navigation/programmer rejection → `revision_error`, success →
`revision_notice`. Rejected calls leave persistence unchanged.

### Routes — `routes/web.php`

`POST /transcriptions/{transcription}/revisions` (`transcriptions.revisions.store`),
`.../revisions/undo`, `.../revisions/redo`, inside the `auth`/`verified` group.

### Workspace — `TranscriptionController::show`, `show.blade.php`, partial

`show()` now calls `RevisionService::active()`; when an active revision exists it
is authoritative for presentation (P6-001 §9), otherwise the machine source is
shown. A `displaySegments` helper emits the stable numeric navigation identity
(machine `segment_index`, or revision `position`), the revision `position` used
as the edit key, exact `seek` strings, and `MM:SS` start labels that match the
Phase 4 accessor. It also exposes the active revision id/version, the immediate
parent as the undo target, the deterministic redo target, and `canEdit`.

`resources/views/transcriptions/partials/revision-toolbar.blade.php` (new) renders
the active-revision indicator, Edit/Save/Cancel, Undo/Redo, live-region status,
and flash messaging, using dedicated `data-edit-*` / `data-revision-*` hooks.
`show.blade.php` wraps the search region in a `transcriptEditing` Alpine
component and adds per-segment `data-edit-text` textareas inside a single
`[data-edit-form]`. Save is a **native** submit button associated with the form by
`form="revision-edit-form"` (the surrounding toolbar is outside the form).

## 2. Preserved semantics (explicit)

- **Immutable machine source**: no write to `transcription_segments` /
  machine transcription columns; asserted byte-for-byte by feature tests and
  browser evidence.
- **Append-only revisions**: every save appends; existing rows are never mutated
  (feature test captures prior revision segments around a second edit).
- **Expected-base concurrency / no silent merge**: a save composed against the
  machine source after the active pointer moved is rejected as a conflict; the
  pointer and revision rows are unchanged and the conflict is surfaced.
- **Strict-ancestor undo / unique-child redo / branch-after-undo**: delegated to
  the already-verified `RevisionService`; HTTP and browser tests confirm the
  visible behaviour and that redo is disabled at a branch point.
- **P6-006 navigation/filter + reserved Phase 4 hooks**: the row markup keeps
  `data-segment-row` / `data-segment-index` / `data-filter-language` /
  `data-nav-seconds` and the seek button and language span keep
  `data-seek-seconds` / `data-segment-language`. P6-003 adds only its own hooks.
  P6-006's `isTypingTarget()` guard already ignores `input`/`textarea`/`select`/
  `contentEditable`, so arrow-key navigation does not hijack textarea typing.
- **Textual edit classification**: `EditKind::Textual` →
  `TranslationStalenessReason::SourceTextChanged`; P6-003 writes no staleness
  state and adds no `translations.stale_at` / `staleness_reason` column.

## 3. Files changed

Application:

- `app/Editing/TextEditComposer.php` (new)
- `app/Http/Controllers/TranscriptionController.php`
- `app/Http/Controllers/TranscriptRevisionController.php` (new)
- `routes/web.php`
- `resources/views/transcriptions/show.blade.php`
- `resources/views/transcriptions/partials/revision-toolbar.blade.php` (new)

Tests:

- `tests/Unit/Editing/TextEditComposerTest.php` (new)
- `tests/Feature/Editing/TranscriptEditingWorkspaceTest.php` (new)

Verification (DC-01):

- `verification/p6-003-seed.php`, `verification/p6-003-auth.setup.js`,
  `verification/playwright.p6-003.config.js`,
  `verification/p6-003/transcript-editing.spec.js`,
  `verification/p6-003/README.md`,
  `verification/p6-003/P6-003-BROWSER-VERIFICATION-EVIDENCE.md`,
  `verification/p6-003/p6-003-browser-results.json`.

Governance: `tasks/P6-003-text-editing-undo-redo.md`,
`PHASE6-7-ELIGIBILITY-MATRIX.md` §O, `DECISIONS.md`, `DECISION_QUEUE.md`,
`CURRENT_STATE.md`.

## 4. Tests

`tests/Unit/Editing/TextEditComposerTest.php` — 6 tests: text-only preservation of
identity/position/timing/language; empty-string legality; position ordering;
missing/unknown/non-string rejection.

`tests/Feature/Editing/TranscriptEditingWorkspaceTest.php` — 12 tests:

- machine-source edit materializes the initial revision and appends the edit;
  machine rows unchanged;
- edit from an existing active revision appends a new version; prior revision
  rows unchanged;
- identity/position/timing/language preserved on text-only edit;
- stale base rejected (no row/pointer change) with the conflict surfaced;
- undo to a strict ancestor + redo to the unique child;
- non-ancestor undo rejected, pointer unchanged;
- branch-after-undo invalidates automatic redo, old history durable;
- `EditKind::Textual` → `SourceTextChanged`; no staleness schema introduced;
- workspace renders the indicator/edit controls and keeps the P6-006 / Phase 4
  hooks;
- reload durability;
- ownership denial (403);
- machine source unchanged across edit/undo/redo.

Combined targeted result: **18 passed / 85 assertions**.

## 5. Verification results

| Gate | Result |
|---|---|
| `php artisan test --compact tests/Unit/Editing/TextEditComposerTest.php tests/Feature/Editing/TranscriptEditingWorkspaceTest.php` | 18 passed / 85 assertions |
| `php artisan test --compact` (full suite) | 789 tests, 788 passed, 1 skipped, 0 failures |
| `vendor/bin/pint` (changed/new files) | passed |
| `phpstan analyse` (level 7) | 0 errors |
| P6-003 Playwright (real Chromium, dedicated DB) | 8 passed / 8 |

Browser evidence: `verification/p6-003/P6-003-BROWSER-VERIFICATION-EVIDENCE.md`.
Required coverage all observed passing: enter edit mode; save; cancel; reload
durability; stale conflict; undo; redo; branch-after-undo; ownership denial;
machine-source immutability.

## 6. Residual findings / honest limitations

- Editing from machine source performs two repository operations
  (`materializeInitial` then `edit`). Each is independently CAS-fenced. If a
  concurrent writer moves the active pointer between the two, the second
  operation fails as a conflict and the materialized machine-copy revision is
  left active (text equal to the machine source) — a non-corrupt, recoverable
  state. This is not hidden; it is noted for the reviewer.
- The undo control targets the immediate parent (single-step strict ancestor).
  Arbitrary historical selection is explicitly out of scope (P6-008).
- Exports still read the machine source; wiring export to the active revision is
  the P6-001 §9 / P6-009 gate item, not P6-003 scope.
- The pre-existing `showRenameModal` console error (documented Phase 4 debt)
  remains unrelated.
- No BLOCKER/HIGH/MEDIUM finding is claimed to be absent by the implementer; the
  reviewer decides.

## 7. Independent-review focus

1. Confirm the append-only/machine-immutability guarantees and expected-base
   concurrency are real at the HTTP boundary (not just in the service).
2. Confirm the workspace does not break P6-006 keyboard navigation/filter/search
   or the reserved Phase 4 hooks, and that typing in the edit textarea does not
   trigger segment navigation.
3. Confirm P6-003 introduces no translation-staleness persistence and does not
   redefine any P6-001/P6-002 semantics.
4. Confirm ownership/isolation and that no later Phase 6/7 task was started.
5. Reproduce the browser evidence from the committed harness.

P6-003 remains `IMPLEMENTED_PENDING_REVIEW`. The implementation owner has not
self-verified and has not promoted or started any later task.
