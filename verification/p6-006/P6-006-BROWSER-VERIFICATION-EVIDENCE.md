# P6-006 — Browser Verification Evidence (DC-01)

Task: P6-006 — Advanced Navigation + Search/Filter (Phase 6 early-start).
Date: 2026-09-23.
Tooling: Playwright (Chromium, headless), `verification/playwright.p6-006.config.js`.
Harness: `verification/p6-006/advanced-navigation-filter.spec.js`; reproduction
steps in `verification/p6-006/README.md`.

Result: **7 tests, 7 passed, ~14.3 s, 0 retries.**
Machine-readable (tracked): `verification/p6-006/p6-006-browser-results.json`.

## What is real

| Layer | Real or double |
|---|---|
| Browser, page rendering, Alpine, CSS, keyboard events | real (Chromium) |
| Laravel app (PHP built-in server), auth, sessions, routes, controllers | real |
| Fixtures/DB | dedicated SQLite `database/p6-006-verification.sqlite`, freshly migrated/seeded per run (`verification/p6-006-seed.php`) |

No product double is used. The suite asserts **rendered/visible** behaviour:
computed `outline-style`/`outline-width`, element visibility, and live-region
text — not merely the `hidden` attribute.

## Checks (finding → coverage → result)

| Finding | What the browser suite proves | Result |
|---|---|---|
| H-1 (reserved selector collision) | Phase 4 hooks remain unique (verified separately by the P4-003/P4-004/P4-006 regression runs below); P6-006 uses `data-filter-language` / `data-nav-seconds` | PASS |
| M-1 (stale filter count) | `8 segments` / `2 segments` / `1 segment` immediately after every switch, including repeated zh→ta→all→zh→all switching; label is `aria-live="polite"` | PASS |
| M-2 (overlap trap) | On overlapping data, `ArrowDown` moves nav selection 0→1→2→1→0 while playback stays on segment 0; zero-length final segment is reachable and wraps | PASS |
| M-3 (no-media feedback) | With no player: nav selection is visibly outlined, live region announces `Segment N of 3 selected`, and no playback-owned `aria-current` is faked | PASS |
| M-4 (reproducibility) | Committed seed + auth setup + config + tracked results + this evidence document + README | PASS |
| LOW modifier collisions | `Control/Alt/Meta/Shift+ArrowDown`, `Shift+J`, `Control/Alt+j` do not navigate; typing in the search input does not navigate | PASS |
| LOW instructions association | Region has `aria-describedby="transcript-nav-hint"` and `aria-keyshortcuts`; hint element resolves; nav status is `role="status"`/`aria-live="polite"` | PASS |
| LOW copy scope | `Copy transcript` carries `data-transcript-copy-scope="full"` and a title stating it copies the full transcript | PASS |

## Observed values

- Filter: all = `8 segments` (8 visible), `zh` = `2 segments` (2 visible, index 0 hidden, index 2 visible), `ta` = `1 segment` (index 3 visible); repeated switching stable.
- Search re-sync: `segmen` = 4 marks unfiltered, 0 marks under `zh`, 4 marks after clearing.
- Navigation (filter fixture, player present): ArrowDown 0→1, ArrowUp 1→0, End 7, Home 0, `j` 1, `k` 0.
- Overlap fixture: `aria-current` stays 0 while `data-nav-active` advances to 1 (stable identity), then 2, back to 1/0, End 4 (zero-length), ArrowDown wraps to 0.
- No-media fixture: player count 0, nav selection outlined, announcements `Segment 1..3 of 3 selected`, `aria-current` count 0.

## Phase 4 regression (H-1 verification)

Run against freshly seeded P4 databases with `-t public`:

| Suite | Result |
|---|---|
| P4-004 search/copy regression (`playwright.p4-004.config.js`) | 3/3 passed |
| P4-006 Phase 4 integration (`playwright.p4-006.config.js`) | 13/14 passed; **V4-01, V4-10, V4-11, V4-35 now pass** (previously failed with the duplicated selectors). V4-08 (`audio ... playback`) failed on `currentTime === 0` — the known environmental playback-start flake, not a selector/behaviour regression |
| P4-003 seek-locator tests (`playwright.config.js`, grep) | 8/8 passed, including every strict `[data-seek-seconds="..."]` locator test. The full P4-003 suite stops at the same pre-existing audio-playback flake (serial mode), unrelated to P6-006 |

## Residual / environmental notes

- The audio `<audio>` playback-start flake (`currentTime` remains 0 after the
  spec's fixed wait) reproduces on P4-003 and P4-006 baseline suites; it is
  independent of P6-006 and was already recorded by the P4-003/P4-006 reviewers.
- Pre-existing `ReferenceError: showRenameModal is not defined` remains; not
  attributable to P6-006.
