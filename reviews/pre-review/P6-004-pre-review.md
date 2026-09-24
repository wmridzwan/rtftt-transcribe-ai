# P6-004 — Pre-Review Handoff (Timing Editing + Validation)

Date: 2026-09-24
Task: `tasks/P6-004-timing-editing-validation.md`
Status: `IMPLEMENTED_PENDING_REVIEW` (not VERIFIED, not DONE)
Authority: `DECISION-P6-004-READY-001`; `DECISION-PHASE6-AUTHORIZATION-001`;
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025; frozen
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §5/§6; verified P6-002
(`DECISION-P6-002-CLOSURE-001`); closed P6-003 (`DECISION-P6-003-CLOSURE-001`);
DC-01
Reviewer: Claude Code (fresh independent review required)

## Scope discipline

Implemented only the canonical P6-004 scope:

- timing-only editing (`start_seconds` / `end_seconds`) of the **active
  revision's** segments (no text edits, no split/merge, no structural change);
- append-only revisions via the frozen `RevisionService`; no in-place mutation;
- expected-base optimistic concurrency; no silent merge;
- server-side timing validation under the frozen D6-03 invariants;
- workspace timing edit mode / save / cancel, validation + conflict feedback,
  active-revision indicator, dedicated `data-timing-*` hooks.

Not touched: P6-005 (split/merge, translation-invalidation persistence),
P6-008/P6-009; no translation-staleness persistence; no Phase 7 work; no change
to the frozen P6-001/P6-002 semantics or the schema.

## 1. What was built

### Domain helper — `app/Editing/TimingEditComposer.php` (new)

Narrow, timing-only composition over a base revision's segments. It preserves each
segment's `RevisionSegmentIdentity`, `position`, `text`, and carried `language`
verbatim and replaces only `startSeconds` / `endSeconds`; the result is rebuilt as
fresh `RevisionSegmentData` objects, so the normal per-segment / cross-segment
validation applies. It rejects a submission that does not supply exactly one
timing per base segment (count mismatch / unknown position / non-array entry /
missing start or end / non-numeric), and per value it rejects non-finite
(`NaN`/`±INF`), negative, `start > end`, and sub-millisecond precision **without
silent rounding**. It deliberately permits overlap, nested overlap, equal
starts/ends, zero-length, out-of-time-order positions, and extending a segment
past a neighbor — no monotonicity rule is introduced.

### HTTP boundary — `TranscriptRevisionController::timing` (`transcriptions.revisions.timing`)

Authorization-fenced (`authorize('update')`) thin wrapper over `RevisionService`:

- validates `expected_base` (nullable) and the `timings[position][start|end]`
  payload (`required|array|min:1`, `*` array, `*.start`/`*.end` required numeric);
- when `expected_base` is empty it materializes the **pure** machine-source
  sequence in memory, composes/validates against it **before any write**, then
  materializes the durable initial revision (machine timing copied verbatim) and
  appends the edit derived from it — so an invalid first timing edit never leaves
  a materialized revision behind;
- otherwise it verifies the expected base equals the active revision (else
  `RevisionConflictException::staleBase()`) and appends `edit()`.

Domain outcomes map to workspace feedback: stale → `timing_conflict` (with a
reload path), validation/programmer rejection → `timing_error`, success →
`timing_notice`. Rejected calls leave persistence unchanged (including the
machine-source first edit).

### Route — `routes/web.php`

`POST /transcriptions/{transcription}/revisions/timing` inside the `auth`/`verified`
group, alongside the P6-003 revision routes.

### Workspace — `TranscriptionController::show`, `show.blade.php`, partial

`displaySegments` now also exposes `formatted_end`; active-revision timing already
drives the `seek` / `nav_index` / `formatted_start` projection (P6-001 §9), so
playback/active-segment resolution uses revision timing automatically once a
timing edit is active, and machine timing when no revision exists.

`resources/views/transcriptions/partials/timing-toolbar.blade.php` (new) renders
the timing indicator, Edit/Save/Cancel, live-region status, `$errors`, and
`timing_conflict` / `timing_error` / `timing_notice` messaging, using dedicated
`data-timing-*` hooks. The timing form is external (`#timing-edit-form`, marked
`novalidate` so server-side validation is canonical) and the per-row timing inputs
associate with it via the `form` attribute, because the row region is already
wrapped by P6-003's `#revision-edit-form` (forms must not nest). A new
`transcriptTiming` Alpine component owns `timingEditMode` / `enter` /
`cancelTiming` / `forceExit`; P6-003's `transcriptEditing` gained a matching
`forceExit()` and both dispatch `p6-text-enter` / `p6-timing-enter` so the two
edit modes are mutually exclusive without sharing state. Each row renders its
visible current start/end (`data-timing-current-start` / `data-timing-current-end`)
and, in timing mode, `data-timing-start-input` / `data-timing-end-input`.

## 2. Preserved semantics (explicit)

- **Frozen D6-03 timing invariants consumed, not redefined**: finite,
  non-negative, `start <= end`, millisecond precision, intuitive overlap /
  nested / equal-bounds / zero-length legality, no cross-segment monotonicity,
  ordering by revision `position`, immutable machine timing. The implementation
  introduces no new timing rule.
- **Immutable machine source**: no write to `transcription_segments` / machine
  transcription columns; asserted byte-for-byte by feature tests and browser
  evidence.
- **Append-only revisions**: every save appends; existing rows are never mutated
  (feature test captures the prior revision segments around a second edit).
- **Expected-base concurrency / no silent merge**: a timing save composed against
  the machine source after the pointer moved is rejected as a conflict; the
  pointer and revision rows are unchanged and the conflict is surfaced.
- **Schema unchanged**: no `translations.stale_at` / `staleness_reason` column or
  write path; classification is `EditKind::Timing` →
  `TranslationStalenessReason::TimingChanged` only (P6-005 owns persistence).
- **Playback source of truth**: when an active revision exists, seek controls and
  active-segment resolution use active-revision timing (half-open, lowest-`position`
  tie resolution preserved); machine timing is used only when no revision exists.
- **P6-006 navigation**: still ordered by the active revision's `position`
  (`data-segment-index`); verified after out-of-time-order timing edits.
- **P6-003 / P6-007 compatibility**: text editing/undo/redo and the comparison
  partial are untouched; the timing controls use only `data-timing-*` and do not
  repurpose the reserved Phase 4 hooks (`data-seek-seconds`,
  `data-segment-language`) or P6-003/P6-006/P6-007 hooks. P6-006's
  `isTypingTarget()` guard already ignores `input`/`textarea`/`select`/
  `contentEditable`, so arrow-key navigation does not hijack timing-input typing.

## 3. Files changed

Application:

- `app/Editing/TimingEditComposer.php` (new)
- `app/Http/Controllers/TranscriptRevisionController.php`
- `app/Http/Controllers/TranscriptionController.php`
- `routes/web.php`
- `resources/views/transcriptions/show.blade.php`
- `resources/views/transcriptions/partials/timing-toolbar.blade.php` (new)

Tests:

- `tests/Unit/Editing/TimingEditComposerTest.php` (new)
- `tests/Feature/Editing/TranscriptTimingWorkspaceTest.php` (new)

Verification (DC-01):

- `verification/p6-004-seed.php`, `verification/p6-004-auth.setup.js`,
  `verification/playwright.p6-004.config.js`,
  `verification/p6-004/timing-editing.spec.js`,
  `verification/p6-004/README.md`,
  `verification/p6-004/P6-004-BROWSER-VERIFICATION-EVIDENCE.md`,
  `verification/p6-004/p6-004-browser-results.json`.

Governance: `tasks/P6-004-timing-editing-validation.md`, `DECISIONS.md`,
`DECISION_QUEUE.md`, `CURRENT_STATE.md`, `AGENTS.md`, `plan.md`,
`PHASE6-7-ELIGIBILITY-MATRIX.md` §R, `PHASE5-7-DEPENDENCY-GRAPH.md`.

## 4. Tests

`tests/Unit/Editing/TimingEditComposerTest.php` — 16 tests: timing-only
preservation of identity/position/text/language; position ordering; overlap /
nested / equal starts / equal ends / zero-length / out-of-time-order / extend-past
acceptance; numeric-string normalization; missing/unknown/non-array/missing-field
/ non-numeric / NaN / INF / negative / `start > end` / sub-millisecond rejection.

`tests/Feature/Editing/TranscriptTimingWorkspaceTest.php` — 13 tests:

- machine-source timing edit materializes the initial revision and appends the
  edit (parent/version/active pointer); machine rows unchanged;
- edit from an existing active revision changes only timing; prior rows unchanged;
- overlap/nested/equal-end/zero-length/out-of-time-order acceptance at the HTTP
  boundary; zero-length persisted but never active;
- negative / `start > end` / sub-millisecond rejected with no write;
- stale base rejected (no row/pointer change) with the conflict surfaced;
- ownership denial (403);
- `EditKind::Timing` → `TimingChanged`; no staleness schema introduced;
- playback seek/navigation projection uses active-revision timing (and machine
  timing without a revision), with active-segment resolution differing between the
  two at `t=5.0`;
- dedicated `data-timing-*` hooks render while reserved Phase 4 / P6-003 / P6-006
  hooks remain;
- reload durability.

Combined targeted result: **29 passed / 128 assertions**.

## 5. Verification results

| Gate | Result |
|---|---|
| `vendor/bin/pest tests/Unit/Editing/TimingEditComposerTest.php tests/Feature/Editing/TranscriptTimingWorkspaceTest.php` | 29 passed / 128 assertions |
| `vendor/bin/pest tests/Unit/Editing tests/Feature/Editing` | 157 passed / 615 assertions |
| `php artisan test --compact` (full suite) | 832 tests, 831 passed, 1 skipped, 2 warnings, 0 failures |
| `vendor/bin/pint --dirty` | passed |
| `phpstan analyse --memory-limit=1G` (level 7) | 0 errors |
| P6-004 Playwright (real Chromium, dedicated DB) | 10 passed / 10 |

Browser evidence: `verification/p6-004/P6-004-BROWSER-VERIFICATION-EVIDENCE.md`.
All required DC-01 scenarios observed passing (see the evidence doc).

## 6. Phase 4 / P6-003 / P6-006 / P6-007 regression runs

| Suite | Result |
|---|---|
| P6-003 `transcript-editing.spec.js` | 8 passed / 8 |
| P6-006 `advanced-navigation-filter.spec.js` | 7 passed / 7 |
| P6-007 `source-translation-comparison.spec.js` | 8 passed / 8 |
| P4-004 `transcript-search-copy.spec.js` | 3 passed / 3 |
| P4-006 `phase4-integration.spec.js` (excluding the two `play()` gates V4-08/V4-09) | 11 passed / 12; V4-13 failed (see below) |
| P4-003 `transcript-playback.spec.js` | fails at the audio `play()` assertion (known environmental flake, see below) |

### Known environmental Phase 4 playback flake (not a P6-004 regression)

`P4-003` "real audio playback via authorized stream" fails because headless
Chromium does not advance `<audio>.currentTime` after `play()` in this
environment (`currentTimeAfterPlay: 0`). The same value is present in the
**pre-existing** tracked artifact `verification/artifacts/p4-003-browser-results.json`
(downloaded before this batch), and the element/metadata/stream-src assertions
that precede it all pass. P6-004 does not touch the media element, the stream
route, or the player component. The same pre-existing flake affects the
`P4-006` V4-08/V4-09 `play()` gates, which are likewise unrelated to timing
editing. The seek/active-segment gates that do not depend on playback advancing
(V4-10, V4-11, V4-12) pass.

### Pre-existing P6-007 comparison interaction with the older P4-006 V4-13 spec

`P4-006` V4-13 ("multilingual display") fails because its
`getByText('Segmen kedua').first()` now resolves to a **hidden**
`<td data-comparison-machine>` cell inside the P6-007 comparison table (which
renders machine text) rather than the visible transcript row. The comparison
partial is rendered before the transcript rows in the DOM and was introduced by
P6-007 (committed `985d2c5`), before this batch; P6-004 does not add, move, or
hide any text in that table. This is a P4-006-spec/P6-007 interaction, not a
timing-editing regression. A separate, scoped fix (scope the older V4-13 locator
to the transcript region) is recommended, not part of P6-004.

## 7. Residual findings / honest limitations

- The machine-source first timing edit materializes the initial revision and then
  appends; each operation is independently CAS-fenced. The composition/validation
  now happens before the first write, so an **invalid** first edit leaves nothing
  behind. A concurrent writer between the two internal operations still surfaces
  as a conflict with a durable, recoverable materialized revision — the same
  disclosed P6-003 characteristic.
- Validation feedback for a negative / inverted / sub-millisecond value is a
  post-redirect accessible `data-timing-error` (server-canonical); the entered
  draft is not retained across the redirect (mirrors P6-003's conflict pattern).
- The timing form is marked `novalidate` so browser-native `min`/`step` do not
  pre-empt the canonical server validation and its accessible error.
- Exports still read the machine source; wiring exports to the active revision is
  the P6-001 §9 / P6-009 gate item, not P6-004 scope.
- The pre-existing `showRenameModal` console error (documented Phase 4 debt)
  remains unrelated.
- No BLOCKER/HIGH/MEDIUM finding is claimed to be absent by the implementer; the
  reviewer decides.

## 8. Independent-review focus

1. Confirm timing-only composition preserves identity/position/text/language and
   that no monotonicity rule was added (overlap / nested / equal / zero-length /
   out-of-time-order / extend-past all accepted).
2. Confirm the machine-source first edit cannot write on invalid input, and that
   machine timing is byte-for-byte immutable across timing edits/undo.
3. Confirm expected-base concurrency and stale-conflict behavior at the HTTP
   boundary (not just in the service).
4. Confirm the playback source of truth: active-revision timing drives seek and
   active-segment resolution, P6-006 navigation stays position-based, and machine
   timing does not leak into edited playback.
5. Confirm P6-004 introduces no translation-staleness persistence (P6-005) and no
   split/merge or structural identity change.
6. Confirm ownership/isolation and that no later Phase 6/7 task was started.
7. Reproduce the browser evidence from the committed harness, and re-check the
   Phase 4 regression notes (environmental playback flake; P6-007/V4-13 spec
   interaction) to confirm they are not P6-004 regressions.

P6-004 remains `IMPLEMENTED_PENDING_REVIEW`. The implementation owner has not
self-verified and has not promoted or started any later task.