# P6-007 — Source / Translation Comparison

## Status

IMPLEMENTED_PENDING_REVIEW (2026-09-23).

Promoted to READY by the Human Product Owner
(`DECISION-P6-007-READY-001`, `DECISION-P6-003-P6-007-READY-BATCH-001`), then
implemented by OpenCode against the frozen P6-001/P6-002 foundation and the
closed Phase 5 translation data. Implementation, feature tests, and real-browser
(DC-01) evidence are complete. Independent review (Claude Code) is pending; this
task is **not** self-verified and **not** DONE.

Contract authored 2026-09-23 under the contract-authoring authorization
(`DECISION-PHASE6-AUTHORIZATION-001`) and the P6-007 owner decision
(`DECISION-P6-007-SCOPE-001`, presentation-only). Consumes the frozen P6-001
semantics, the verified P6-002 revision foundation, and the persisted Phase 5
translation data without redefining any of them.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019 boundary; ADR-025 D6-06).

## Objective

Provide a **presentation-only** source / revision / translation comparison inside
the existing transcript workspace (D6-06): let a user see the immutable machine
source, the active editable revision, and the persisted Phase 5 translation
side-by-side (or in an equivalent comparison view), without owning or persisting
translation invalidation.

## Owner decision consumed

`DECISION-P6-007-SCOPE-001` — **P6-007 is presentation-only for its required
Phase 6 scope.** It may display the immutable machine source, the active editable
revision, persisted Phase 5 translation content, and source / revision /
translation comparison relationships. It must not own or persist translation
invalidation. Translation-staleness display is optional; if staleness state is
not yet available because P6-005 has not implemented it, P6-007 must degrade
gracefully and must not invent or infer stale/current state. This preserves
P6-007 independence from P6-005.

## Persisted data reality (alignment baseline)

- Phase 5 persists `translations` (`transcription_id`, `target_language`,
  `status`, `full_text`, …) and `translation_segments`
  (`translation_id`, `segment_index`, `start_seconds`, `end_seconds`, `text`,
  `source_language`, unique `(translation_id, segment_index)`).
- `translation_segments.segment_index` aligns to the **machine**
  `transcription_segments.segment_index`. There is currently **no** persisted
  `revision_id`, source hash, or other linkage tying a translation to a specific
  revision.
- Therefore a persisted translation is, by construction, a translation of the
  **machine source**, not of an edited revision's text.
- The initial machine-materialized revision (`RevisionService::materializeInitial`)
  copies machine text/timing/language verbatim and assigns `machine:<index>`
  identities, so it is textually equivalent to the machine source; alignment by
  `segment_index` is valid for it.
- A text-edited revision (P6-003) preserves identity/position but changes text;
  its text no longer matches the machine source that the translation was produced
  from.
- A structurally edited revision (future P6-005 split/merge) may break the
  identity/position ↔ `segment_index` correspondence entirely.
- No `translations.stale_at` / `translations.staleness_reason` marker exists at
  contract time (owned by P6-005).

## Scope

### 1. Comparison sources

Support, as appropriate and only where the underlying data is valid:

- **Machine source vs active revision**: both are always available (machine
  source always recoverable; active revision may be `null`). When no active
  revision exists, present the machine source as authoritative.
- **Source/revision vs translation**: present the persisted translation next to
  the source it actually corresponds to (the machine source), and next to the
  active revision where alignment is valid.
- **Alignment by existing persisted relationship where valid**: align machine
  source ↔ translation by `segment_index`. Align the active revision ↔
  translation only where the active revision's segment identity/position still
  corresponds to the machine `segment_index` it was derived from **and** its text
  still matches the translated source (i.e. the machine-materialized initial
  revision). Never align by array index or by inference.

### 2. Presentation-only boundary

P6-007 must **not**:

- mutate the machine source (`transcription_segments` / machine columns);
- mutate revision history (no append, activate, or rewrite of revisions);
- persist translation invalidation (`translations.stale_at` /
  `staleness_reason`) or any other staleness state;
- silently remap translation content onto changed segment structure;
- own the translation lifecycle (dispatch, status, attempts, provider);
- add or alter translation/revision schema.

All comparison actions are read-only. No comparison interaction may write to the
database.

### 3. Missing / stale state

- If translation staleness is not available (P6-005 not implemented), show **only
  facts actually persisted**. Do not infer or display a freshness/staleness state.
- Do not block comparison merely because P6-005 is incomplete; comparison of
  available data must still work.
- If a later P6-005 staleness marker exists, P6-007 may **consume** it through an
  explicit, separately-approved contract without owning or writing it. Until then,
  no staleness column may be read as if it existed.
- Unavailable translation (none persisted, or not completed) is a first-class
  state: show a clear "no translation available" presentation; do not fabricate
  content or alignment.

### 4. Alignment behavior (no silent misalignment)

- **Current machine-aligned translation**: present machine source ↔ translation
  aligned by `segment_index`, labeled as the translation of the machine source.
- **Active revision whose text differs from the machine source**: the persisted
  translation belongs to the machine source. P6-007 must present the translation
  as corresponding to the machine source and must **not** present it as the
  translation of the edited revision text. Where a comparison between the edited
  revision and the translation is shown, the mismatch must be explicit (for
  example, "translation is of the original source; the active revision has since
  been edited") and never silently aligned as if current.
- **Unavailable translation**: show the no-translation state; comparison of
  machine source vs active revision remains available.
- **Structurally incompatible future revision state** (for example after P6-005
  split/merge changes identities/positions): do not map translation segments by
  `segment_index`; present alignment as unavailable/not valid for that revision
  rather than guessing or remapping.
- Never invent a source/revision identity that is not persisted.

### 5. Workspace integration

Inside `resources/views/transcriptions/show.blade.php` (the transcript
workspace; D6-06 requires comparison inside the workspace, not a separate
module):

- A discoverable **comparison toggle/view** that switches the transcript region
  between normal, machine-source, and comparison presentations without navigating
  away.
- Read-only rendering of source, active revision, and translation content; exact
  visual layout is an implementation detail.
- Must not break P6-006 keyboard navigation, language filter, search/copy, or
  active-segment highlighting, and must not reuse the reserved Phase 4 hooks
  (`data-seek-seconds`, `data-segment-language`) or P6-006's
  `data-filter-language` / `data-nav-seconds`.
- Authorization: reads require `TranscriptionPolicy::view` (owner/admin);
  non-owners must not see another user's transcription or translation.

## Non-Scope

- translation-invalidation persistence (P6-005);
- text editing / undo/redo (P6-003); timing editing (P6-004); split/merge (P6-005);
- revision history / audit surface (P6-008);
- translation lifecycle (dispatch/status/attempts/provider) and translation
  re-generation;
- any change to the Phase 5 translation contracts or the frozen P6-001/P6-002
  semantics;
- waveform/timeline (D6-09); speaker labels/annotations/bookmarks (D6-08);
- new translation/revision schema.

## Dependencies

- Phase 5 = CLOSED (persisted translations available);
- P6-002 = DONE (`DECISION-P6-002-CLOSURE-001`; revision read layer);
- P6-001 frozen semantics (active revision / machine source);
- P6-006 = DONE (navigation/search/filter must remain compatible);
- `DECISION-P6-007-SCOPE-001` (presentation-only);
- DC-01 browser verification (browser behavior is material);
- P6-005 is **not** a dependency (independence preserved).

## Acceptance Criteria

1. The workspace provides a read-only comparison view/toggle for machine source,
   active revision, and persisted translation where available.
2. Machine source vs active revision comparison works with and without an active
   revision; when no active revision exists, the machine source is authoritative.
3. Machine source ↔ translation alignment uses the persisted `segment_index`
   relationship; no alignment is inferred by array index.
4. When the active revision's text differs from the machine source, the persisted
   translation is presented as corresponding to the machine source, and any
   revision-vs-translation view states the mismatch explicitly; no silent
   alignment.
5. Structurally incompatible revision states do not map translation by
   `segment_index`; alignment is presented as unavailable rather than guessed.
6. No translation available → clear no-translation state; comparison of machine
   source vs active revision still works.
7. No staleness/freshness is inferred when no P6-005 marker exists; only persisted
   facts are shown.
8. Comparison actions perform no database writes (source, revision history, and
   translation state unchanged).
9. Authorization/isolation: a non-owner cannot view another user's comparison.
10. P6-006 navigation/search/filter and reserved Phase 4 hooks remain intact.
11. Relevant tests pass; Pint clean; PHPStan 0 errors; full regression green.

## Verification Requirements

- Feature tests for comparison read model/authorization/isolation and for the
  no-mutation guarantee (assert no revision/translation rows change).
- Real-browser verification (DC-01) for the comparison view and its states.
- Independent review of the implementation and evidence. The implementer must not
  self-verify or self-close.

## Browser Verification Required

Yes (DC-01). Required coverage:

- comparison toggle/view;
- source vs active revision;
- source/revision vs translation where available;
- no-translation state;
- authorization/isolation (non-owner denied);
- no mutation from comparison actions (persisted state unchanged).

Browser evidence must record exact steps, environment metadata, fixture identity,
observed vs expected state, and must not substitute backend tests for browser
behavior.

## Expected Reviewer

Claude Code.

## Owner Decision Dependencies

D6-06 (comparison surface); `DECISION-P6-007-SCOPE-001` (presentation-only);
DC-01; ADR-025. No further owner decision is required for this contract.

## Shared Workspace-File Collision Risk

P6-007 and P6-003 both target `resources/views/transcriptions/show.blade.php`.

- This is a **concrete shared-file collision risk** at implementation time.
- P6-007 may remain parallel-safe with P6-003 only if the workspace changes are
  partitioned (distinct Blade partials/includes and distinct Alpine
  components/regions) or coordinated with explicit file ownership; otherwise
  sequence them. See `PHASE6-7-ELIGIBILITY-MATRIX.md` §N.
- P6-007 reads the revision read layer and translation tables; it must not modify
  the frozen P6-001/P6-002 semantics or the Phase 5 translation schema.

## Readiness

Promoted to **READY** by the Human Product Owner
(`DECISION-P6-007-READY-001`) after this contract, `DECISION-P6-007-SCOPE-001`,
and its dependency reconciliation (`PHASE6-7-ELIGIBILITY-MATRIX.md` §N).
Implementation is authorized and complete; the task is
`IMPLEMENTED_PENDING_REVIEW` and awaits independent review.
