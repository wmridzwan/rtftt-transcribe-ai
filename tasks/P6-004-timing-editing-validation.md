# P6-004 — Timing Editing + Validation

## Status

DONE (2026-09-24; HPO closure `DECISION-P6-004-CLOSURE-001`).

Promoted to READY and authorized for implementation by the Human Product Owner
(`DECISION-P6-004-READY-001`), then implemented by OpenCode against the frozen
P6-001/P6-002 foundation and the closed P6-003 workspace. The fresh independent
review confirmed the frozen timing semantics are implemented exactly, invalid
first edits are write-free, active-revision timing is the playback/active
resolution source of truth, machine timing is immutable, overlap/nested/equal/
zero-length/out-of-time-order timing remain legal, stale-base/CAS behavior is
correct, no P6-003/P6-006/P6-007 regression was found, no split/merge or
translation-staleness persistence was introduced, and no BLOCKER/HIGH/MEDIUM
finding remains. The HPO accepted the verdict and transitioned the task
`VERIFIED → DONE` (`DECISION-P6-004-CLOSURE-001`). The independent review and the
historical implementation/pre-review artifacts are preserved unchanged.

Canonical contract authored under the Phase 6 contract-authoring authorization
(`DECISION-PHASE6-AUTHORIZATION-001`) and the adopted owner decisions
(`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025; D6-03). Consumes the frozen
P6-001 domain semantics, the verified P6-002 persistence foundation, and the
closed P6-003 text-editing workspace without redefining any of them.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 6 — Advanced Transcript UX (ADR-019 boundary; ADR-025 D6-03).

## Objective

Deliver user-facing **segment timing (start/end) editing with server-side
validation** inside the existing transcript workspace, built entirely on the
frozen revision foundation:

- edit the active revision's per-segment `start_seconds` / `end_seconds`;
- never mutate the immutable machine source in place;
- persist every timing edit as a new append-only revision;
- validate timing server-side under the frozen D6-03 invariants;
- surface stale-write conflicts and validation errors without silent merge;
- keep playback, active-segment resolution, and navigation driven by the active
  revision's timing once an editable revision is active.

## Frozen foundation consumed (must not be redefined)

- `PHASE6-EDITING-DOMAIN-CONTRACT.md` (FROZEN): immutable machine source +
  append-only editable revisions + one active pointer (`null` = machine source) +
  durable graph/history; transcript-scoped monotonic version;
  `parent_revision_id` ancestry; expected-base CAS concurrency; strict-ancestor
  undo; deterministic unique-child redo; `RevisionSegmentIdentity` / contiguous
  `position`; **timing invariants (§5)**; translation-invalidation precedence;
  reserved Phase 4 hooks.
- P6-002 (`DECISION-P6-002-CLOSURE-001`), implemented and independently VERIFIED:
  - `App\Editing\RevisionService` — authorization-fenced application boundary:
    `materializeInitial`, `edit`, `undo`, `redo`, `active`, `history`,
    `redoTarget`;
  - `App\Editing\RevisionFactory` / `RevisionRepository` (append-only,
    expected-base CAS, monotonic version);
  - `App\Editing\RevisionSegmentData` (per-segment timing validation),
    `App\Editing\TimingInvariants` (`assertValidTiming`, `isZeroLength`,
    `overlaps`, `activeAt` with half-open intervals and lowest-`position`
    resolution);
  - `App\Editing\EditKind` / `App\Editing\TranslationInvalidationPolicy`
    (`EditKind::Timing` → `TranslationStalenessReason::TimingChanged`);
  - persistence: `transcript_revisions`, `transcript_revision_segments`,
    `transcriptions.active_revision_id`.
- P6-003 (`DECISION-P6-003-CLOSURE-001`), independently VERIFIED: the workspace
  edit surface, the `{ baseRevisionId, canEdit }` client contract, the
  `expected_base` save discipline, the materialize-then-edit path, and the
  save/cancel/conflict UX patterns. P6-004 mirrors these patterns for timing and
  must not regress them.

P6-004 may consume these interfaces. It must not add in-place revision mutation,
change version/ancestry/redo semantics, alter the frozen schema, or redefine the
frozen timing invariants.

## Timing semantics (D6-03 — frozen; restated, not redefined)

All timing values are seconds with at most millisecond precision, matching the
frozen Phase 3/4 storage precision (`decimal(12,3)`).

Per-segment validation (rejects with `InvalidArgumentException`, mapped to a
workspace validation error; never a partial write):

1. `start_seconds` and `end_seconds` are finite numbers (not NaN/±INF).
2. Both are `>= 0`.
3. `start_seconds <= end_seconds` (equality is the zero-length case).
4. Millisecond precision: a submitted value carrying more than three fractional
   digits is rejected; the implementation must not silently round or truncate.

Cross-segment policy (explicit; no hidden monotonicity):

- **Overlaps are LEGAL** (a revision may contain overlapping `[start, end)`
  intervals).
- **Overlap resolution** uses the lowest `position` (frozen Phase 4 rule with
  revision `position` in place of `segment_index`).
- **Zero-length (`start == end`) segments are LEGAL** but are **never active**
  for playback (half-open `start <= t < end`).
- **Cross-segment timestamp monotonicity is NOT required.** Ordering is by
  revision `position`, never by timestamp.
- **Machine timestamps remain immutable.** A timing edit writes only the
  revision layer; `transcription_segments.start_seconds` / `end_seconds` are
  never touched.

## Scope

### 1. Timing-edit behavior

- Edit **active-revision segment timing only** (`start_seconds` / `end_seconds`).
  No text edits (P6-003), no split/merge or identity restructuring (P6-005).
- The immutable machine source (`transcription_segments` / `transcriptions`
  machine columns) is never written. `TranscriptRevisionSegment` /
  `transcript_revisions` are append-only.
- **Editing from machine source** (no active revision, `active_revision_id = null`):
  materialize the initial revision from the immutable machine source
  (`RevisionService::materializeInitial`, which copies machine timing verbatim),
  then append the timing edit as a derived revision from it. The materialized
  machine-copy revision is durable and reachable by undo; the edited revision
  becomes active.
- **Editing from an existing active revision**: append a new revision derived
  from the active base (`RevisionService::edit`). Existing revisions are never
  mutated.
- A **timing-only** edit preserves each segment's `RevisionSegmentIdentity`,
  `position`, `text`, and carried `language`; only `startSeconds` / `endSeconds`
  change. (Mirror of the P6-003 rule that a text-only edit preserves everything
  but `text`.)
- Validation is **server-side and canonical** (never client-only). The edit is
  rejected if any segment violates §"Timing semantics" or if the resulting
  sequence violates `RevisionSegmentData` / `TranscriptRevision` invariants
  (positions contiguous `0..n-1`, unique identities).
- Ownership: mutations require `TranscriptionPolicy::update` (owner or admin),
  enforced by `RevisionService`.

A dedicated composer (analogous to `TextEditComposer`, e.g.
`App\Editing\TimingEditComposer::compose(array $base, array $timingsByPosition)`)
is the required composition boundary: it rebuilds the ordered
`list<RevisionSegmentData>`, replacing timing only and re-validating through the
ordinary per-segment / cross-segment invariants.

### 2. Concurrency (expected-base)

- Every save states the **expected active/base revision id** it was composed
  against (`expected_base` in the workspace, mirroring P6-003).
- A stale base (the active pointer moved) fails as
  `RevisionConflictException::staleBase()` — the canonical domain conflict.
- No silent merge, no last-writer-wins, no automatic rebase.
- A rejected save leaves persistence unchanged (no partial revision, active
  pointer unchanged).

### 3. Append-only / no in-place mutation

- A timing edit always creates a new revision derived from the base; no existing
  revision row is mutated and no version is reused.
- Undo/redo remain exactly the P6-003 active-pointer movements (unchanged).

### 4. Translation invalidation (classification only)

- A timing-only edit is `EditKind::Timing` → `TranslationStalenessReason::TimingChanged`
  per the frozen `TranslationInvalidationPolicy`.
- If a single save also changes text (out of P6-004's required scope), the frozen
  mixed-category precedence applies (`TimingChanged` wins over `SourceTextChanged`);
  every applicable kind remains invalidating.
- P6-004 **must not** persist translation-staleness markers. The
  `translations.stale_at` / `translations.staleness_reason` schema and write path
  are owned by P6-005. P6-004 may reference the policy for
  classification/audit only; no columns and no staleness writes.

### 5. Multi-segment timing rules (explicit)

Given the frozen invariants, P6-004 must define and accept the following
behaviors (all validated per segment only; no cross-segment rejection):

| Case | Behavior |
|---|---|
| Overlap creation | Legal. Two positions may overlap; both persist unchanged. |
| Nested overlap | Legal. One interval may fully contain another; both persist. |
| Equal starts | Legal. Two positions may share a start; both persist. |
| Equal ends | Legal. Two positions may share an end; both persist. |
| Zero-length segment | Legal (`start == end`); persists; **never active** in playback. |
| Out-of-time-order positions | Legal. A later `position` may hold an earlier time range. |
| Edit one segment past another's range | Legal. It may overlap or extend beyond a neighbor; neighbors are **not** auto-adjusted. |

No hidden monotonicity constraint may be introduced that rejects any of the
above, because that would contradict the frozen P6-001 §5 policy. Overlap
resolution for playback is determined at read time by the lowest `position`
(frozen `TimingInvariants::activeAt`).

## Non-Scope

- split / merge / segment-identity restructuring (P6-005);
- persisted translation staleness / translation remapping (P6-005);
- text editing / undo/redo behavior changes (P6-003 — must remain green);
- source/translation comparison changes (P6-007 — must remain green);
- revision history / audit surface beyond the existing active-revision indicator
  (P6-008);
- translation lifecycle changes;
- any change to frozen Phase 3/4/5 contracts, the frozen P6-001 semantics, or the
  verified P6-002 persistence semantics;
- waveform/timeline (D6-09); speaker labels/annotations/bookmarks (D6-08);
- new public API surface beyond what the workspace requires.

## Playback / navigation interaction

Timing edits must remain compatible with Phase 4 playback, active-segment
resolution, P6-006 navigation, active-revision presentation, and the reserved
Phase 4 hooks.

- **Which timestamps drive playback after an editable revision becomes active:**
  the **active revision's** `startSeconds` / `endSeconds` (fed through the
  workspace's revision-aware display/playback projection) drive the player seek
  targets and active-segment resolution. Machine-source timing drives playback
  **only** when no active revision exists (`active_revision_id = null`).
- **Active-segment resolution** stays the frozen half-open rule with
  lowest-`position` overlap resolution; a zero-length segment is never active.
- **Navigation** continues to follow the active revision's `position`
  (`nav_index`), never machine `segment_index`, once a revision exists.
- **Machine-source timing is never mutated** by a timing edit; the machine
  transcript remains recoverable and authoritative when no revision is active.
- The reserved Phase 4 hooks (`data-seek-seconds`, `data-segment-language`) are
  **owned by Phase 4** and must not be reused on containers/rows or given new
  semantics. P6-004 must feed them only through the existing revision-aware
  projection and add its own dedicated `data-timing-*` hooks for new controls.
  P6-006's `data-filter-language` / `data-nav-seconds` are likewise owned by
  P6-006.

## UX contract

Inside `resources/views/transcriptions/show.blade.php` (the transcript
workspace; D6-06 workspace placement):

- **Timing edit mode**: an explicit, discoverable mode (per-segment or
  transcript-wide) in which each segment's start/end become editable numeric
  fields. Entering the mode must not alter persisted state.
- **Save / cancel**:
  - Save sends the edited timings plus the expected active/base revision id.
  - Cancel discards local changes and persists nothing.
  - On success the workspace reflects the new active revision and its timing.
- **Validation feedback**: client-side constraint hints may exist, but the
  server is canonical. A rejected timing edit surfaces an accessible
  (live-region) message naming the invalid segment; no partial write occurs.
- **Stale-edit conflict UX**: a stale base shows a clear, accessible conflict
  message and a reload/reconcile path (mirroring the P6-003
  `revision_conflict` behavior); it must never silently overwrite or merge.
- **Visible current timing**: the workspace shows each segment's current
  start/end (the existing formatted start label may be retained; an end label or
  range display is required so the user can see the current timing).
- **Accessible controls**: numeric start/end inputs are labeled per segment,
  keyboard operable, and focus is managed on mode entry/exit. Millisecond
  precision is accepted (e.g. `step="0.001"`, `min="0"`).
- **Keyboard behavior**: while a timing input is focused, P6-006 arrow-key
  segment navigation must not hijack typing (native inputs, matching P6-006's
  existing guard).
- **Revision indicator after save**: the existing active-revision indicator (or
  an equivalent dedicated `data-timing-*` indicator) makes clear that a new
  active revision is authoritative after a timing save.
- **Do not implement split/merge.** No operation may create, delete, or
  re-identify segments.

## Dependencies

- P6-001 = DONE (frozen D6-03 timing invariants);
- P6-002 = DONE (`DECISION-P6-002-CLOSURE-001`; verified revision service);
- P6-003 = DONE (`DECISION-P6-003-CLOSURE-001`; shared workspace surface and
  save/conflict patterns);
- Phase 4 primitives (`SegmentTimestamp`, `ActiveSegmentResolver`, reserved
  hooks) and P6-006 = DONE (navigation/search/filter must remain compatible);
- D6-03 (adopted); DC-01 browser verification (browser behavior is material);
- P6-005 is **not** a dependency.

## Acceptance Criteria

1. A timing edit changes only `start_seconds` / `end_seconds` of the targeted
   segment(s); text, language, identity, and `position` are preserved.
2. Editing from the machine source materializes the initial revision and appends
   the timing edit; the machine `transcription_segments` timing is byte-for-byte
   unchanged.
3. Editing from an existing active revision appends a new revision derived from
   it; no existing revision row is mutated; versions remain unique per
   transcription.
4. Per-segment validation is server-side: finite, non-negative, `start <= end`,
   at most millisecond precision; violations are rejected with no partial write.
5. Overlap, nested overlap, equal starts, equal ends, zero-length, out-of-time-
   order positions, and extending one segment past another's range are all
   accepted and persisted (no hidden monotonicity rejection).
6. A zero-length segment persists but is never selected as the active segment for
   playback.
7. A stale base is rejected with `RevisionConflictException`; persistence is
   unchanged and the conflict is surfaced in the UI.
8. A timing edit is classified `EditKind::Timing` →
   `TranslationStalenessReason::TimingChanged`; no translation-staleness
   persistence is introduced (no `translations.stale_at`/`staleness_reason`).
9. After a timing save, playback/active-segment resolution uses the active
   revision's timing; with no active revision, machine timing is used.
10. Timing edit mode, save/cancel, validation feedback, conflict UX, visible
    current timing, accessible controls, keyboard behavior, and the revision
    indicator are present and do not break P6-003 text editing/undo-redo,
    P6-006 navigation/search/filter, P6-007 comparison, or the reserved Phase 4
    hooks.
11. Ownership: a non-owner cannot save a timing edit; owner/admin can.
12. Reload durability: timing edits, active revision, and undo/redo state survive
    reload.
13. No split/merge, no segment-identity restructuring, no translation remapping,
    no persisted translation staleness.
14. Relevant tests pass; Pint clean; PHPStan 0 errors; full regression green.

## Verification Requirements

- Feature tests against the real database for: valid timing edit; negative time;
  `start > end`; non-finite; sub-millisecond precision; zero-length; overlap;
  nested overlap; equal starts/ends; out-of-time-order positions; extend-past-
  neighbor; machine-source materialize-then-edit; append-only behavior;
  stale-base conflict; ownership; reload durability; machine-source immutability;
  and the absence of `translations.stale_at`/`staleness_reason`.
- Real-browser verification (DC-01) for the user-visible timing-editing behavior
  below.
- Independent review of the implementation and evidence. The implementer must not
  self-verify or self-close.

## Browser Verification Required

Yes (DC-01). Required coverage (at minimum):

- valid timing edit (persist + visible result + playback/navigation uses the new
  active revision timing after save);
- invalid negative time (rejected, no write);
- `start > end` (rejected, no write);
- zero-length segment (accepted, not active);
- overlap (accepted, lowest-`position` active resolution);
- reload durability;
- stale conflict (clear, accessible, no silent merge);
- cancel / no write;
- machine-source immutability (machine transcript timing unchanged after editing);
- ownership denial (non-owner cannot save).

Browser evidence must record exact steps, environment metadata, fixture identity,
observed vs expected state, and must not substitute backend tests for browser
behavior.

## Expected Reviewer

Claude Code.

## Owner Decision Dependencies

D6-01 (immutable source + editable layer), D6-02 (revision/version semantics),
D6-03 (timestamp editing invariants), D6-05 (visible revision state); DC-01;
ADR-025. No additional owner decision is required for this contract.

## Shared Workspace-File Collision Risk

P6-004 targets `resources/views/transcriptions/show.blade.php` (and its
`partials/`), which currently contains `transcriptPlayback`, `transcriptSearch`,
`transcriptEditing`, the P6-007 comparison partial, the row markup, and the
reserved/owned `data-*` hooks.

- P6-003 (the previous owner of the edit surface) is DONE, so the earlier P6-003 ↔
  P6-004 timing-edit workspace collision is no longer an active parallel risk.
- P6-004 must add its timing-edit controls via a distinct partial and/or Alpine
  component with dedicated `data-timing-*` hooks, and must not regress P6-003
  text editing/undo-redo, P6-006 navigation/search/filter, P6-007 comparison, or
  the reserved Phase 4 hooks.
- `RevisionService` / revision persistence are consumed write-through; P6-004 may
  not modify the frozen P6-001/P6-002 semantics.

## Readiness

Promoted to **READY** by the Human Product Owner
(`DECISION-P6-004-READY-001`, 2026-09-24) after this canonical contract and its
dependency reconciliation (`PHASE6-7-ELIGIBILITY-MATRIX.md` §Q/§R). The
dependencies (P6-001 DONE, P6-002 DONE, P6-003 DONE, D6-03 adopted, Phase 4/P6-006
primitives available) are satisfied and P6-004 does not depend on Phase 5 or
P6-005. Implementation is complete, independently reviewed VERIFIED with no
remaining BLOCKER/HIGH/MEDIUM finding, and closed **DONE** by the HPO
(`DECISION-P6-004-CLOSURE-001`). P6-005 is no longer blocked by P6-004 (it is now
`CONTRACT_REQUIRED / READY-ELIGIBLE AFTER CONTRACT`).

## Implementation summary

- `App\Editing\TimingEditComposer` — narrow timing-only composer that preserves
  identity/position/text/language, rejects non-finite/negative/`start > end`/
  sub-millisecond input without silent rounding, and accepts overlap, nested
  overlap, equal starts/ends, zero-length, out-of-time-order positions, and
  extending past a neighbor (no monotonicity rule added).
- `TranscriptRevisionController::timing` (`transcriptions.revisions.timing`) —
  authorization-fenced, expected-base CAS, append-only via `RevisionService`; the
  machine-source first edit validates against the pure machine sequence **before**
  any write; stale → `timing_conflict`, invalid → `timing_error`, success →
  `timing_notice`.
- Workspace — `transcriptions/partials/timing-toolbar.blade.php` and a
  `transcriptTiming` Alpine component with dedicated `data-timing-*` hooks;
  per-row start/end inputs and visible current timing; mutually exclusive with
  P6-003 text editing via `p6-text-enter`/`p6-timing-enter` events.
- Playback/navigation already consume the active revision's timing through the
  workspace projection; no machine timing leaks and no reserved hook is reused.