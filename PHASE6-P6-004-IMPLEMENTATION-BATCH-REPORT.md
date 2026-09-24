# Phase 6 — P6-004 Timing Editing + Validation — Implementation Batch Report

Date: 2026-09-24
Task: P6-004 (Timing Editing + Validation)
Status: **P6-004 = IMPLEMENTED_PENDING_REVIEW** (not self-verified, not DONE)
Authority: `DECISION-P6-004-READY-001`; `DECISION-PHASE6-AUTHORIZATION-001`;
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025; frozen
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §5/§6; P6-002/P6-003 closures; DC-01.

## 0. HPO promotion decision

- `DECISION-P6-004-READY-001` promotes P6-004 to **READY** and authorizes
  implementation, recorded in `DECISION_QUEUE.md`, `DECISIONS.md`,
  `tasks/P6-004-timing-editing-validation.md`, `CURRENT_STATE.md`, `AGENTS.md`,
  `plan.md`, and `PHASE6-7-ELIGIBILITY-MATRIX.md` §Q/§R.

## 1. Implementation summary

- `App\Editing\TimingEditComposer` (new): narrow timing-only composer. Replaces
  `startSeconds`/`endSeconds`; preserves `RevisionSegmentIdentity`, `position`,
  `text`, and carried `language`; rebuilds fresh `RevisionSegmentData`.
- `TranscriptRevisionController::timing` (new) +
  `POST /transcriptions/{transcription}/revisions/timing`
  (`transcriptions.revisions.timing`): authorization-fenced, expected-base CAS,
  append-only via `RevisionService`.
- Workspace: `resources/views/transcriptions/partials/timing-toolbar.blade.php`
  (new) and a `transcriptTiming` Alpine component; per-row `data-timing-*-input`
  controls and visible `data-timing-current-*`; mutually exclusive with P6-003
  text editing via `p6-text-enter`/`p6-timing-enter` events.

## 2. Timing validation model

- Per segment: finite (reject `NaN`/`±INF`), non-negative, `start <= end`,
  millisecond precision with **no silent rounding** (a value beyond 3 fractional
  digits is rejected). The controller validates the payload shape
  (`timings[position][start|end]` numeric) and the composer enforces the values;
  validation is server-side and canonical.
- Cross-segment: overlap, nested overlap, equal starts, equal ends, zero-length,
  out-of-time-order positions, and extending one segment past a neighbor are all
  accepted; ordering is by revision `position`; no monotonicity rule is added.
- Classification: timing-only edit → `EditKind::Timing` →
  `TranslationStalenessReason::TimingChanged`. **No** staleness is persisted and
  no `translations.stale_at`/`staleness_reason` column is added (P6-005 owns it).
- Machine-source first edit: the pure machine sequence is materialized **in
  memory** and composed/validated **before any write**, so an invalid first edit
  leaves nothing behind; only a valid edit then materializes the durable initial
  revision and appends the derived edit.

## 3. Playback source-of-truth implementation

- The workspace projection (`TranscriptionController::displaySegments`) already
  derives `seek`, `nav_index`, and formatted labels from the active revision when
  one exists, and from the machine source otherwise; `formatted_end` was added.
- Consequently, after a timing edit becomes active, the player seek targets and
  active-segment resolution use the active revision's timing (half-open interval,
  lowest-`position` tie resolution preserved); with no active revision, Phase 4
  machine timing remains authoritative. Machine timing is never mutated.
- No reserved Phase 4 hook is reused: `data-seek-seconds` and
  `data-segment-language` keep their Phase 4 ownership; P6-003/P6-006/P6-007
  hooks are untouched; P6-004 adds only `data-timing-*` hooks.

## 4. Changed files

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

Review / governance:

- `reviews/pre-review/P6-004-pre-review.md` (new)
- `tasks/P6-004-timing-editing-validation.md`, `DECISIONS.md`,
  `DECISION_QUEUE.md`, `CURRENT_STATE.md`, `AGENTS.md`, `plan.md`,
  `PHASE6-7-ELIGIBILITY-MATRIX.md` §Q/§R, `PHASE5-7-DEPENDENCY-GRAPH.md`,
  this report.

## 5. Targeted / browser / full-suite results

| Gate | Result |
|---|---|
| `vendor/bin/pest tests/Unit/Editing/TimingEditComposerTest.php tests/Feature/Editing/TranscriptTimingWorkspaceTest.php` | 29 passed / 128 assertions |
| `vendor/bin/pest tests/Unit/Editing tests/Feature/Editing` | 157 passed / 615 assertions |
| `php artisan test --compact` (full PHP suite) | 832 tests, 831 passed, 1 skipped, 2 warnings, 0 failures |
| `vendor/bin/pint --dirty` | passed |
| `vendor/bin/phpstan analyse --memory-limit=1G` (level 7) | 0 errors |
| P6-004 Playwright (real Chromium, dedicated DB) | **10 passed / 10** |

P6-004 browser evidence covers all required DC-01 scenarios (see the evidence
doc): valid edit; negative rejected; `start > end` rejected; zero-length accepted;
overlap accepted; reload durability; stale conflict; cancel/no write;
machine-source timing unchanged; ownership denial; playback seek uses
active-revision timing after save; active-segment resolution uses active-revision
timing; P6-006 navigation stays position-based.

## 6. Regression results

| Suite | Result |
|---|---|
| P6-003 `transcript-editing.spec.js` | 8 passed / 8 |
| P6-006 `advanced-navigation-filter.spec.js` | 7 passed / 7 |
| P6-007 `source-translation-comparison.spec.js` | 8 passed / 8 |
| P4-004 `transcript-search-copy.spec.js` | 3 passed / 3 |
| P4-006 `phase4-integration.spec.js` (all except V4-08/V4-09 `play()` gates) | 11 passed / 12 — V4-13 fails (pre-existing, below) |
| P4-003 `transcript-playback.spec.js` | fails at the audio `play()` assertion (environmental flake, below) |

## 7. Known environmental flakes / pre-existing interactions (not P6-004 regressions)

- **Phase 4 headless playback flake**: `<audio>.currentTime` does not advance
  after `play()` in this environment (`currentTimeAfterPlay: 0`). The same value
  is present in the pre-existing `verification/artifacts/p4-003-browser-results.json`
  (timestamped 2026-09-23, before this batch). It affects P4-003 and the P4-006
  V4-08/V4-09 `play()` gates. P6-004 does not touch the media element, stream
  route, or player component. The non-`play()` P4-006 seek/active gates
  (V4-10/V4-11/V4-12) pass (see the pre-review for the full per-test breakdown).
- **Pre-existing P6-007/P4-006 V4-13 spec interaction**: the older V4-13
  `getByText('Segmen kedua').first()` resolves to the hidden P6-007 comparison
  table cell (rendered before the transcript rows). Introduced by P6-007
  (`985d2c5`), independent of P6-004. Recommend a scoped fix to the older spec
  (scope the locator to `[data-transcript-region]`) as a separate change.

## 8. Residual findings / honest limitations

- Machine-source first timing edit performs `materializeInitial` then `edit`; each
  is independently CAS-fenced. Validation now happens before the first write, so
  invalid input writes nothing. A concurrent writer between the two operations
  still surfaces as a conflict with a durable, recoverable materialized revision
  (the same disclosed P6-003 characteristic).
- Validation feedback for an invalid value is a post-redirect accessible
  `data-timing-error`; the entered draft is not retained across the redirect
  (mirrors P6-003's conflict pattern). The timing form is `novalidate` so the
  canonical server validation (and its accessible error) is authoritative.
- Exports still read the machine source; wiring export to the active revision is a
  P6-001 §9 / P6-009 gate item, not P6-004 scope.
- The pre-existing `showRenameModal` console error (Phase 4 debt) is unrelated.

## 9. Pre-review status

`reviews/pre-review/P6-004-pre-review.md` is authored and describes the scope,
domain model, HTTP boundary, workspace, preserved semantics, tests, verification,
regression notes, residual findings, and recommended independent-review focus.
P6-004 must not be self-verified or self-closed.

## 10. Recommended independent-review focus

1. Timing-only composition preserves identity/position/text/language; no
   monotonicity rule added (overlap/nested/equal/zero-length/out-of-time-order/
   extend-past accepted).
2. Machine-source first edit writes nothing on invalid input; machine timing is
   byte-for-byte immutable across timing edits/undo.
3. Expected-base concurrency and stale-conflict behavior at the HTTP boundary.
4. Playback source of truth: active-revision timing drives seek and
   active-segment resolution; P6-006 navigation stays position-based; machine
   timing does not leak into edited playback.
5. No translation-staleness persistence (P6-005); no split/merge or structural
   identity change.
6. Ownership/isolation; no later Phase 6/7 task started.
7. Reproduce the committed browser harness; re-check the Phase 4 regression notes
   to confirm they are environmental/pre-existing rather than P6-004 regressions.

## 11. Explicit confirmations

- P6-004 did **not** redefine the frozen timing semantics; it consumed
  `PHASE6-EDITING-DOMAIN-CONTRACT.md` §5/§6 as-is.
- Machine timing remains immutable (feature + browser evidence).
- No P6-005 behavior was implemented (no split/merge, no structural identity
  change, no persisted translation staleness, no translation remapping).
- No later P6/P7 task was started or promoted.
- P6-004 was **not** self-marked VERIFIED or DONE; it is
  `IMPLEMENTED_PENDING_REVIEW`.