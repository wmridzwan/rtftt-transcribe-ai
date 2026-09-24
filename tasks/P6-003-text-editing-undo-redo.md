# P6-003 — Text Editing + Undo/Redo

## Status

DONE (2026-09-24; HPO closure `DECISION-P6-003-CLOSURE-001`).

Promoted to READY by the Human Product Owner
(`DECISION-P6-003-READY-001`, `DECISION-P6-003-P6-007-READY-BATCH-001`), then
implemented by OpenCode against the frozen P6-001/P6-002 foundation. Independent
review (Claude Code, `reviews/P6-003-P6-007-independent-review.md`) returned
**VERIFIED** with no remaining BLOCKER/HIGH/MEDIUM finding. The HPO accepted the
verdict and transitioned the task `VERIFIED → DONE`
(`DECISION-P6-003-CLOSURE-001`). The independent review artifact is preserved
unchanged.

Contract authored 2026-09-23 under the contract-authoring authorization
(`DECISION-PHASE6-AUTHORIZATION-001`) and the adopted owner decisions
(`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025). Consumes the frozen P6-001
domain semantics and the verified P6-002 persistence foundation without
redefining them.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019 boundary; ADR-025 D6-01/D6-02/D6-05).

## Objective

Deliver user-facing **segment text editing with undo/redo** inside the existing
transcript workspace, built entirely on the frozen revision foundation:

- edit the active revision's segment text;
- never mutate the immutable machine source in place;
- persist every edit as a new append-only revision;
- provide undo (strict ancestor) and redo (unique deterministic child);
- surface stale-write conflicts without silent merge;
- expose the active revision state to the user.

## Frozen foundation consumed (must not be redefined)

- `PHASE6-EDITING-DOMAIN-CONTRACT.md` (FROZEN): immutable machine source +
  append-only editable revisions + one active pointer (`null` = machine source) +
  durable graph/history; transcription-scoped monotonic version; `parent_revision_id`
  ancestry; strict-ancestor undo; deterministic unique-child redo;
  `RevisionSegmentIdentity` / contiguous `position`; timing invariants;
  translation-invalidation precedence.
- P6-002 (`DECISION-P6-002-CLOSURE-001`), implemented and independently VERIFIED:
  - `App\Editing\RevisionService` — authorization-fenced application boundary:
    `materializeInitial`, `edit`, `undo`, `redo`, `active`, `history`,
    `redoTarget`;
  - `App\Editing\RevisionFactory` — `materializeInitial` / `derive`;
  - `App\Editing\RevisionRepository` (`activeFor`, `find`, `historyFor`,
    `childrenOf`, `redoTargetFor`, `nextVersionFor`, `append`, `activate`);
  - `App\Editing\RevisionConflictException` (stale base / non-monotonic version);
  - `App\Editing\UndoUnavailableException` (non-ancestor undo target);
  - `App\Editing\RedoUnavailableException` (no unique child);
  - `App\Editing\EditKind` / `App\Editing\TranslationInvalidationPolicy`;
  - persistence: `transcript_revisions`, `transcript_revision_segments`,
    `transcriptions.active_revision_id`.

P6-003 may consume these interfaces. It must not add in-place revision mutation,
change version/ancestry/redo semantics, or alter the frozen schema.

## Scope

### 1. Editing behavior

- Edit **active-revision segment text only**. No timing edits (P6-004), no
  split/merge (P6-005), no structural changes.
- The immutable machine source (`transcription_segments` / `transcriptions`
  machine columns) is never written. `TranscriptRevisionSegment` /
  `transcript_revisions` are append-only.
- **Editing from machine source** (no active revision, `active_revision_id = null`):
  materialize the initial revision from the immutable machine source
  (`RevisionService::materializeInitial`) and append the user's text edit as a
  derived revision from it. The materialized machine-copy revision is durable and
  reachable by undo; the edited revision becomes active.
- **Editing from an existing active revision**: append a new revision derived
  from the active base (`RevisionService::edit`). Existing revisions are never
  mutated.
- A text edit preserves each segment's `RevisionSegmentIdentity`, `position`,
  timing (`startSeconds`/`endSeconds`), and carried `language`; only `text`
  changes. An empty string is a legal distinct value (not a deletion).
- Validation is server-side: the edit is rejected if the resulting segment
  sequence violates `RevisionSegmentData` / `TranscriptRevision` invariants
  (positions contiguous `0..n-1`, unique identities, valid timing).
- Ownership: mutations require `TranscriptionPolicy::update` (owner or admin),
  enforced by `RevisionService`.

### 2. Concurrency

- Every save states the **expected active/base revision id** it was composed
  against.
- A stale base (the active pointer moved) fails as
  `RevisionConflictException::staleBase()` — the canonical domain conflict.
- No silent merge, no last-writer-wins, no automatic rebase.
- A rejected save leaves persistence unchanged (no partial revision, active
  pointer unchanged).

### 3. Undo / redo

- **Undo** calls `RevisionService::undo()` with the verified strict-ancestor
  semantics: the target must be a strict ancestor of the current active revision
  (walking `parent_revision_id`); self/sibling/cousin/descendant/unrelated/
  cross-transcription targets are rejected (`UndoUnavailableException` /
  `InvalidArgumentException`), and the active pointer is unchanged on rejection.
- **Redo** calls `RevisionService::redo()` with the verified unique-child
  semantics. Redo is unavailable at a branch point or a tip with no child
  (`redoTarget` is `null`); the redo control is disabled, never guessed.
- A new edit after undo **branches** from the active revision and invalidates the
  prior automatic redo path (the branch point then has multiple children).
- Old branches and all historical revisions remain durable and are never deleted.
- Undo/redo are active-pointer movements only; they never rewrite history and
  never mutate machine rows.

### 4. Translation invalidation

- A text edit is `EditKind::Textual`, which maps to
  `TranslationStalenessReason::SourceTextChanged` per the frozen
  `TranslationInvalidationPolicy` (all edit kinds invalidate; content is never
  silently remapped).
- P6-003 **must not** persist translation-staleness markers. The
  `translations.stale_at` / `translations.staleness_reason` schema and its
  write path are owned by P6-005. P6-003 may reference the policy for
  classification/audit, but must not add columns or write staleness state unless
  an already-approved interface explicitly requires it (none exists at contract
  time).

### 5. Workspace integration

Inside `resources/views/transcriptions/show.blade.php` (the existing transcript
workspace):

- **Edit mode**: an explicit, discoverable mode (per-segment or transcript-wide)
  in which segment text becomes editable. Entering edit mode must not alter
  persisted state.
- **Save / cancel**:
  - Save sends the edited text plus the expected active/base revision id.
  - Cancel discards local edits and persists nothing.
  - On success, the workspace reflects the new active revision.
- **Visible active revision state**: the workspace indicates whether the machine
  source or an active revision is authoritative (for example, an active-revision
  indicator/version label), so the user can tell that edits are being layered.
- **Conflict UX**: a stale-write conflict shows a clear, accessible message and a
  reload/reconcile path. It must never silently overwrite or merge.
- **Keyboard / accessibility**: edit controls are keyboard operable and labeled;
  focus is managed on mode entry/exit; validation/conflict messages are exposed
  via live regions. While a text input is focused, P6-006 arrow-key segment
  navigation must not hijack typing (native inputs, matching P6-006's existing
  guard).
- **Navigation compatibility with P6-006**: text editing must not break keyboard
  next/previous/jump, the language filter, search/copy, or active-segment
  highlighting. Edited text must render as text (never `innerHTML`).
- **Reserved Phase 4 hooks**: `data-seek-seconds` and `data-segment-language` are
  reserved Phase 4 selectors and must not be reused on containers/rows. P6-006's
  `data-filter-language` / `data-nav-seconds` are likewise owned by P6-006. P6-003
  must add its own dedicated hooks (for example `data-edit-*`) rather than reuse
  reserved selectors.
- **No new standalone product surface**: editing stays inside the transcript
  workspace (D6-01/D6-05).

## Non-Scope

- timing edits / validation (P6-004);
- split/merge + translation-invalidation persistence (P6-005);
- source/translation comparison (P6-007);
- revision history / audit surface beyond the minimal active-revision indicator
  (P6-008);
- translation lifecycle changes or staleness persistence;
- any change to frozen Phase 3/4/5 contracts, the frozen P6-001 semantics, or the
  verified P6-002 persistence semantics;
- waveform/timeline (D6-09); speaker labels/annotations/bookmarks (D6-08);
- new public API surface beyond what the workspace requires.

## Dependencies

- P6-001 = DONE (frozen semantics);
- P6-002 = DONE (`DECISION-P6-002-CLOSURE-001`; verified persistence + service);
- Phase 4 primitives (`transcriptPlayback`, `transcriptSearch`, reserved hooks);
- P6-006 = DONE (navigation/search/filter must remain compatible);
- DC-01 browser verification (browser behavior is material).

## Acceptance Criteria

1. Editing a segment's text from the machine source materializes the initial
   revision and appends the edit; the machine source is byte-for-byte unchanged.
2. Editing from an existing active revision appends a new revision derived from
   it; no existing revision row is mutated; versions remain unique per
   transcription.
3. A text edit preserves segment identity, position, timing, and language; only
   text changes.
4. A stale base is rejected with `RevisionConflictException`; persistence is
   unchanged and the conflict is surfaced in the UI.
5. Undo moves the active pointer to a strict ancestor only; invalid targets are
   rejected and change nothing.
6. Redo moves to the unique child only; it is unavailable at a branch point; a
   new edit after undo invalidates automatic redo while old history stays durable.
7. Text edits are classified `SourceTextChanged`; no translation-staleness
   persistence is introduced (no `translations.stale_at`/`staleness_reason`).
8. Edit mode, save/cancel, active-revision state, and conflict UX are present,
   keyboard accessible, and do not break P6-006 navigation/search/filter or the
   reserved Phase 4 hooks.
9. Ownership: a non-owner cannot edit/undo/redo; owner/admin can.
10. Reload durability: edits, active revision, and undo/redo state survive reload.
11. Relevant tests pass; Pint clean; PHPStan 0 errors; full regression green.

## Verification Requirements

- Feature tests against the real database for edit, materialize-then-edit,
  append-only behavior, stale-base conflict, strict-ancestor undo, unique-child
  redo, branch-after-undo, ownership, and reload durability.
- Real-browser verification (DC-01) for the user-visible editing behavior below.
- Independent review of the implementation and evidence. The implementer must not
  self-verify or self-close.

## Browser Verification Required

Yes (DC-01). Required coverage:

- successful edit (persist + visible result);
- cancel (no persisted change);
- stale edit conflict (clear, accessible, no silent merge);
- undo;
- redo;
- branch-after-undo (redo unavailable; old history durable);
- reload durability;
- ownership denial (non-owner cannot edit);
- source immutability (machine transcript unchanged after editing).

Browser evidence must record the exact steps, environment metadata, fixture
identity, observed vs expected state, and must not substitute backend tests for
browser behavior.

## Expected Reviewer

Claude Code.

## Owner Decision Dependencies

D6-01 (immutable source + editable layer), D6-02 (durable undo/redo/versions),
D6-05 (visible revision state); DC-01; ADR-025. No additional owner decision is
required for this contract.

## Shared Workspace-File Collision Risk

P6-003 and P6-007 both target `resources/views/transcriptions/show.blade.php`
(the transcript workspace, which also contains `transcriptPlayback`,
`transcriptSearch`, the row markup, and the reserved/owned `data-*` hooks).

- This is a **concrete shared-file collision risk** at implementation time.
- P6-003 and P6-007 may remain parallel-safe only if the workspace changes are
  partitioned (for example, distinct Blade partials/includes and distinct Alpine
  components/regions) or are otherwise coordinated with explicit file ownership.
- Recommended: treat P6-003 and P6-007 as parallel-safe only after the contracts
  and implementation plan partition the workspace surface; otherwise sequence
  them. See `PHASE6-7-ELIGIBILITY-MATRIX.md` §N.
- `RevisionService` / revision persistence are consumed read/write by P6-003 and
  read by P6-007; neither may modify the frozen P6-001/P6-002 semantics.

## Readiness

Promoted to **READY** by the Human Product Owner
(`DECISION-P6-003-READY-001`) after this contract and its dependency
reconciliation (`PHASE6-7-ELIGIBILITY-MATRIX.md` §N). Implementation is complete,
independently reviewed VERIFIED with no remaining BLOCKER/HIGH/MEDIUM finding, and
closed **DONE** by the HPO (`DECISION-P6-003-CLOSURE-001`).
