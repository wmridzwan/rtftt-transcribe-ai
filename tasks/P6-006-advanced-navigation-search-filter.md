# P6-006 — Advanced Navigation + Search/Filter

## Status

IMPLEMENTED_PENDING_REVIEW — implementation complete 2026-09-23. Contract
authored and independence confirmed; implementation + 6 feature tests pass
(full suite 643/642); Pint clean; PHPStan 0; real-Chromium browser verification
passed (2/2) via `verification/p6-006/advanced-navigation-filter.spec.js`.
Fresh independent review pending. Not VERIFIED; not DONE.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 6 — Advanced Transcript UX (early-start exception,
`DECISION-P6-006-AUTHORIZATION-001`; ADR-023; ADR-025 D6-07)

## Objective

Enhance navigation, keyboard movement, and search/filtering **inside the existing
transcript workspace**, without creating a standalone search product and without
touching translation/revision semantics.

## Independence Confirmation (required by the early-start authorization)

Confirmed during contract authoring:

- **No translation invalidation semantics:** P6-006 operates on the read-only
  display/navigation of the completed machine transcript and its segments; it has
  no dependency on the P6 revision layer or on translation staleness/invalidation.
- **No revision ownership assumptions beyond frozen P6 contracts:** P6-006 reads
  the persisted `TranscriptionSegment` read model (Phase 3/4 frozen); it does not
  assume any new revision tables. If a later revision layer changes the read
  model, P6-006 is reconciled then, not forced now.
- **No dependency on unfinished P6 comparison/split-merge behavior:** P6-006 does
  not consume P6-007 (comparison) or P6-005 (split/merge); it builds only on
  Phase 4 primitives (`transcriptPlayback` active-segment highlight,
  `data-segment-*` attributes, `TranscriptCopy`).

Independence holds; P6-006 proceeds as a bounded early-start task.

## Scope

1. Keyboard navigation within the transcript region: next/previous segment and
   jump to first/last, using the existing `p4-seek` event so
   `transcriptPlayback` owns highlighting and auto-scroll. Must not fire while
   focus is inside a text input/select.
2. A language filter within the workspace over the persisted per-segment
   `language` (`ms`, `en`, `zh`, `ta`, `und`), affecting both the rendered rows
   and the client-side search match set so counts stay consistent.
3. Row-level `data-segment-language` exposure (additive) plus accessible filter
   controls; all DOM queries use the captured component root (never `this.$el`
   inside event handlers), preserving the P4-004 corrective invariant.
4. Preserve existing search/copy behavior (no `innerHTML`, exact Unicode text,
   `<mark>` rendering) and active-segment semantics (search highlights remain a
   separate layer from active highlighting).

## Non-Scope

- translation/revision logic; split/merge; comparison (P6-005/P6-007);
- server-side cross-transcript search (existing index filters unchanged);
- new standalone search product, saved searches, or query language;
- schema changes; provider/queue changes;
- waveform/timeline (D6-09).

## Dependencies

- Phase 4 primitives DONE (P4-003, P4-004);
- P6-001 contract not required for this bounded task;
- browser behavior material → DC-01 browser evidence required.

## Acceptance Criteria

1. Keyboard next/previous/jump navigate segments and update active highlighting
   via the existing seek event; disabled while typing in an input.
2. Language filter shows only matching rows and re-syncs search match
   counts/current index.
3. `data-segment-language` is present on segment rows; filter controls are
   accessible and labeled.
4. Existing search/copy/playback tests remain green; no `innerHTML` introduced.
5. No translation/revision/split-merge assumption is introduced.
6. Pint, PHPStan, and the full regression suite pass.
7. Browser evidence retained for the keyboard/filter behavior (DC-01).

## Verification Requirements

Feature tests + browser verification. Independent review of the implementation
and evidence.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

Yes (DC-01).

## Owner Decision Dependencies

D6-07 (adopted); DC-01; ADR-025.