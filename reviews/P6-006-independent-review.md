# P6-006 — Independent Review (Advanced Navigation + Search/Filter)

Reviewer: Claude Code (fresh independent review context; did not implement)
Date: 2026-09-23
Task: `tasks/P6-006-advanced-navigation-search-filter.md`
Implementation commit: `084a2ab`
Authority reviewed: `DECISION-P6-006-AUTHORIZATION-001`,
`DECISION-PHASE6-AUTHORIZATION-001`, `DECISION-PHASE6-OWNER-DECISIONS-001`,
ADR-023, ADR-025 (D6-07, DC-01)

This review does not modify code, tests, task status, or governance state and
does not mark P6-006 DONE.

## Verdict

**CHANGES_REQUESTED** — one HIGH finding (committed Phase 4 browser gate
regression), plus MEDIUM findings.

## Findings

### BLOCKER

None.

### HIGH

**H-1 — P6-006 breaks the committed P4-006 Phase 4 integration browser gate
(duplicated `data-seek-seconds` / `data-segment-language` hooks).**

`resources/views/transcriptions/show.blade.php:165-166` adds
`data-segment-language` and `data-seek-seconds` to every `[data-segment-row]`.
Both attributes already exist on the per-segment language badge and the
timestamp seek button (lines 172, 185), and both are the Phase 4 browser-gate
selectors. With P6-006 applied, `verification/playwright.p4-006.config.js`
fails deterministically:

- V4-01: `[data-seek-seconds]` count expected 8, received 16 (the
  `[data-segment-language]` count is also doubled);
- V4-10, V4-11, V4-35: `locator('[data-seek-seconds="12.345"]')` strict-mode
  violation — resolves to 2 elements (row + button).

`verification/p4-003/transcript-playback.spec.js` uses the same locator pattern
(lines 139, 210, 217, 224, 237, 248, 271, 292) and would fail the same way
(inferred from source; not executed by this review).

This contradicts acceptance criterion 4 ("Existing search/copy/playback tests
remain green") and the pre-review claim that the P4-003/P4-006
no-timestamp-controls surface holds (only the no-speech case was considered).
The navigation code only needs the start time on the row; it can read it from
the row's existing seek button, or use a non-colliding attribute name.
Required: remove the selector collision and re-run the P4-003, P4-004, P4-006,
and P6-006 browser suites.

### MEDIUM

**M-1 — Filter count label is one step stale.** `filterLabel`
(`show.blade.php:486-492`) reads DOM `hidden` state, which `applyFilter()` sets
in a `$watch` callback that runs *after* the `x-text` effect re-evaluates.
Reproduced in real Chromium with the app CSS loaded:
`zh` → "8 segments" (2 visible), `ta` → "2 segments" (1 visible). The label is
`aria-live="polite"`, so screen readers announce the wrong count. Search match
counts (`countLabel`) are correct.

**M-2 — Keyboard navigation traps on overlapping intervals.** The
`resolveNavIndex()` function (`show.blade.php:493-505`) prefers the playback-owned
`aria-current` row over the row keyboard just targeted. Seeking to a segment
whose start falls inside the previous segment resolves (per the frozen P4
resolver: overlaps go to the lowest index) to the previous row, so ArrowDown
re-targets the same row forever. Reproduced on an isolated scratch DB (segment 1
start moved to 3.0 inside segment 0 `[0.5, 4)`): `Home=0 ArrowDown=0
ArrowDown=0 ArrowDown=0`; ArrowUp from 2 skips 1 → 0. Overlapping and
zero-length intervals are explicitly legal inputs under
`app/TranscriptExperience/ActiveSegmentResolver.php` and the Phase 3 writer
does not reject them.

**M-3 — No visual feedback when no media player is present.** When the media
file is unavailable (`$streamUrl === null`), `p4-seek` has no listener that
changes state (`seekTo` returns early), so keyboard navigation only scrolls:
no `aria-current`, no ring, no announcement. Reproduced (media path made
unavailable in the scratch DB): after ArrowDown ×2 and End, the current row set
and ring set are both `[]`. Acceptance criterion 1 ("update active
highlighting") is not met in this state.

**M-4 — DC-01 browser evidence is not durably retained, and the spec is weaker
than it looks.** The only run record is
`verification/artifacts/p6-006-browser-results.json`, which is gitignored
(`.gitignore:38`). There is no committed evidence document (compare
`P5-006-BROWSER-VERIFICATION-EVIDENCE.md`), no recorded server command or DB,
and the dedicated `database/p4-006-verification.sqlite` does not exist in the
working tree, so the provenance of the implementer's run cannot be reconstructed
from the repository. The spec checks the `hidden` attribute, not rendered
visibility; it does not check the filter label, keys ignored while typing in an
input, or regressions in the P4 suites (which is how H-1 went unnoticed). The
historically documented server command (`php -S 127.0.0.1:8123` with the
router, no `-t public`) serves `/build/assets/*` as 404, so a run using it has
no app CSS. This review re-ran with `-t public`.

### LOW

- **L-1 — Modifier combos are captured.** `Ctrl+J`, `Alt+J`, `Shift+J`,
  `Shift+ArrowDown`, and `Ctrl+End` all trigger navigation and `preventDefault`
  (Alpine key modifiers do not exclude unspecified system modifiers). This is
  scoped to region focus, so WCAG 2.1.4 is satisfied, but it can shadow
  AT/browser combos. Consider guarding on `ctrlKey/altKey/metaKey`.
- **L-2 — The navigation hint is not associated with the region.** Add
  `aria-describedby` (or `aria-keyshortcuts`) on `[data-transcript-region]`.
  When no player is present, navigation has no live announcement (see M-3).
- **L-3 — `Copy transcript` ignores the active filter.** This is defensible
  (it copies the full source), but it is undocumented. Worth one line in the
  task notes so the behavior is intentional.
- **INFO — Pre-existing console error** `ReferenceError: showRenameModal is not
  defined` from the rename modal (`show.blade.php:304`, outside the page
  `x-data` scope; introduced before P6-006, `a0e1d41`). Not attributable to
  P6-006.

## Acceptance Criteria

1. Keyboard next/previous/jump + disabled while typing — **PARTIAL.** Correct on
   non-overlapping data with a player; wraps; scoped to filtered rows;
   input/select focus correctly ignored. Fails M-2 (overlap trap) and M-3
   (no player).
2. Language filter shows only matching rows and re-syncs search counts —
   **MET for rows and search counts** (rendered visibility confirmed with CSS;
   `segmen`: all=4, en=2 with "1 of 2" → "2 of 2", zh=0 "No matches", back=4;
   hidden rows restored to exact raw text with no `<mark>`). The new filter
   label is stale (M-1).
3. `data-segment-language` on rows; accessible, labelled controls — **MET**
   (label + `aria-label`, region `role`/`aria-label`/`tabindex`). This change
   also causes H-1.
4. Existing search/copy/playback tests green; no `innerHTML` — **NOT MET**
   (H-1). There is no `innerHTML`/`x-html`; P4-004 browser suite 3/3 green.
5. No translation/revision/split-merge assumption — **MET.**
6. Pint, PHPStan, full suite — **MET** (reproduced).
7. Browser evidence retained (DC-01) — **NOT MET durably** (M-4).

## Scope / Independence

- The diff touches only `TranscriptionController::show` (read-only language
  list), the workspace view, one feature test, and the browser harness. There is
  no schema, translation, revision, comparison, split/merge, waveform, speaker,
  or bookmark code. The filter is purely presentational (`row.hidden`) and never
  writes transcript, translation, or revision state.
- Authorization: `$this->authorize('view', $transcription)` is unchanged. There
  are no new routes. Option values are Blade-escaped enum values.
- P4-004 `rootEl` invariant: all new DOM queries use `this.rootEl`, and no
  `this.$el` is used in handlers. **Intact.**
- Search highlights remain a separate layer from active highlighting.
- **Independence held**: P6-006 is genuinely independent of P5 translation and
  unfinished P6 semantics.

## Evidence Independently Reproduced

| Evidence | Result |
|---|---|
| `php artisan test --compact tests/Feature/TranscriptNavigationFilterTest.php tests/Feature/Observability` | 17 passed |
| `php artisan test --compact` (full) | 643 tests, 642 passed, 1 skipped |
| `vendor/bin/pint --test` (read-only) | passed |
| `vendor/bin/phpstan analyse` | 0 errors |
| P6-006 spec (`playwright.p6-006.config.js`), isolated scratch DB, `-t public` | 2/2 passed |
| P4-004 spec | 3/3 passed |
| P4-006 spec | 8 passed / 6 failed — V4-01/10/11/35 attributable to P6-006 (H-1); V4-08/09 environmental playback-start latency (verified: no seeks, playback advances after ~1.5 s) |
| Reviewer probe specs (scratchpad, not committed) | visibility, input/select isolation, filtered wrap-around, search re-sync, modifiers, overlap trap, no-player, stale label |

Harness hygiene: the review used a scratch SQLite DB outside the repository. It
backed up `verification/p4-006-fixtures.json` and restored it byte-for-byte,
and confirmed the working tree clean afterward. The seeder wrote fixture media to
the private media disk and Playwright wrote failure screenshots to
`test-results/` (both gitignored).

## Implications for P6-001 / P6-002

- Navigation and filtering key on `segment_index` and per-row DOM attributes.
  Once the revision layer introduces split/merge, the revision contract should
  define which layer's segment identity the workspace navigates (active revision
  vs machine source). It should also define whether `data-*` hooks carry
  revision-segment ids.
- The P6 timing invariants (D6-03) should state the overlap/zero-length policy
  explicitly. M-2 shows that UI navigation silently depends on it.
- Future workspace DOM additions should treat `data-seek-seconds` and
  `data-segment-language` as reserved Phase 4 gate hooks.

## Conclusion

**CHANGES_REQUESTED.** Return to the implementation owner (OpenCode) for H-1
and M-1..M-4. Then run a fresh re-review. P6-006 is not safe for HPO closure.
