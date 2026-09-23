# P6-006 — Advanced Navigation + Search/Filter

## Status

DONE — closed by the Human Product Owner on 2026-09-23
(`DECISION-P6-006-CLOSURE-001`) on the basis of the fresh independent corrective
re-review VERIFIED verdict
(`reviews/P6-006-P7-005-corrective-independent-re-review.md`; no
BLOCKER/HIGH/MEDIUM). All original review findings and corrective artifacts are
preserved unchanged. Residual LOW/INFO debt is carried forward non-blocking
(`DECISION-PHASE6-7-DEBT-CARRYFORWARD-001`); P6-006 is not reopened for it.

- Corrective cycle: H-1 (reserved Phase 4 selector collision), M-1 (stale filter
  count), M-2 (overlap navigation trap), M-3 (no-media feedback), M-4 (durable
  browser evidence) and the LOW items were addressed. See "Corrective Cycle"
  below.
- Original implementation: contract authored; independence confirmed;
  real-Chromium browser verification passed via
  `verification/p6-006/advanced-navigation-filter.spec.js`.

## Review

Review Files:
`reviews/P6-006-independent-review.md` (CHANGES_REQUESTED, original);
`reviews/P6-006-P7-005-corrective-independent-re-review.md` (VERIFIED,
corrective re-review).

Review Status: VERIFIED (2026-09-23). No BLOCKER/HIGH/MEDIUM. The reviewer
independently reproduced the committed browser harness (7/7) and the P4-003/
P4-004/P4-006 regression suites, confirming Phase 4 selector compatibility.
Non-blocking carry-forward (INFO): pre-existing order-dependent full-suite
flakiness; the known Phase 4 audio-playback timing flake; the pre-existing
`showRenameModal` console error. None attributable to P6-006.

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
3. Per-segment language is exposed on segment rows via the dedicated
   `data-filter-language` hook; filter controls are accessible and labeled.
   (Corrective reconciliation: the original wording named `data-segment-language`,
   but that attribute is a reserved Phase 4 selector owned by the language badge;
   P6-006 must not duplicate it. See Corrective Cycle H-1.)
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

## Corrective Cycle (2026-09-23)

Responds to `reviews/P6-006-independent-review.md` (CHANGES_REQUESTED).

- **H-1 — reserved selector collision removed.** The row-level
  `data-seek-seconds` and `data-segment-language` were removed. Rows now expose
  dedicated hooks: `data-filter-language` (filtering) and `data-nav-seconds`
  (navigation). The Phase 4 hooks remain unique to the timestamp seek button and
  the language badge. Re-ran the P4-003 (seek-locator), P4-004 and P4-006 browser
  suites: the previously failing V4-01/V4-10/V4-11/V4-35 now pass.
- **M-1 — filter count fixed.** `applyFilter()` maintains a reactive
  `filteredCount` that drives the `aria-live="polite"` label, so the count is
  never one render behind the DOM. The label now always reflects the visible
  set (including "all").
- **M-2 — stable navigation identity.** Keyboard navigation resolves its
  position from `navIndex` (stable `segment_index`) instead of the
  playback-owned `aria-current` row. Overlapping and zero-length intervals can no
  longer trap navigation or skip valid segments.
- **M-3 — no-media feedback.** Navigation always applies its own visible
  selection outline (`outline-*` classes, distinct from playback's `ring`/`bg`)
  and announces `Segment N of M selected` via a `role="status"` live region.
  Media playback is not faked.
- **M-4 — durable browser evidence.** Added a dedicated P6-006 harness:
  `verification/p6-006-seed.php`, `verification/p6-006-auth.setup.js`,
  `verification/p6-006/README.md`, the strengthened spec, and the tracked
  evidence document `verification/p6-006/P6-006-BROWSER-VERIFICATION-EVIDENCE.md`
  plus tracked results JSON. The spec asserts rendered visibility and computed
  outline, not only `hidden`.
- **LOW.** Modifier combinations (`Ctrl/Alt/Meta/Shift`) are ignored; keyboard
  instructions are associated via `aria-describedby`/`aria-keyshortcuts`; the
  full-transcript copy scope is documented on the control and in code.

## Cross-Task Contract Notes (for later P6/P7 contracts; not implemented here)

- Phase 4 `data-seek-seconds` / `data-segment-language` are **reserved
  compatibility hooks**; future workspace DOM additions must not reuse them.
- **P6-001 / P6-002 revision contracts must define which revision-layer segment
  identity navigation follows after split/merge** (active revision vs machine
  source), and whether `data-*` hooks carry revision-segment ids.
- **D6-03 must explicitly define overlap and zero-length timing semantics**;
  navigation now depends on ordered segment identity precisely because playback
  resolves overlaps to the lowest index.
- **Phase 7 must settle correlation-field naming** (`http_request_id` vs
  `request_id` vs `queue_job_id`) before metrics/tracing build on it.