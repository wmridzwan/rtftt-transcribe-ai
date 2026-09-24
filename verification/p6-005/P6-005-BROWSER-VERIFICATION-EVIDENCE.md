# P6-005 Browser Verification Evidence (DC-01)

Date: 2026-09-24
Task: P6-005 — Split / Merge + Translation Invalidation
Harness: `verification/playwright.p6-005.config.js` +
`verification/p6-005/split-merge.spec.js`
Result JSON: `verification/p6-005/p6-005-browser-results.json`
Outcome: **8 passed / 8, 0 unexpected** (real Chromium; dedicated P6-005
verification DB; 18.1s).

## Environment

- Runner: Playwright 1.63.0, project `chromium` (Desktop Chrome), headless.
- Server: `php -d variables_order=EGPCS -S 127.0.0.1:8127 -t public
  verification/p4-003-server-router.php` against
  `database/p6-005-verification.sqlite`.
- Fixture seeder: `verification/p6-005-seed.php` (`migrate:fresh` + deterministic
  completed transcriptions; owner `p6-005@example.test`, non-owner
  `p6-005-intruder@example.test`).
- HTTP assertion for isolation: `browser.newContext({ storageState: intruder })`
  then `request.get('/transcriptions/{id}')`.
- Real audio player: generated 15 s WAV (`verification/fixtures/p6-005-audio.wav`),
  streamed through the authorized `/stream` route.

## Fixture identity

| Fixture | Transcription id | Shape |
|---|---|---|
| `main` | 1 | 3 segments (en/ms/zh), real WAV player, completed `zh` translation |
| `merge` | 2 | 3 segments, real player, mixed languages |
| `boundary` | 3 | 3 segments, real player |
| `nonadjacent` | 4 | 3 segments, real player |
| `conflict` | 5 | 3 segments, real player |
| `cancel` | 6 | 3 segments, real player |
| `immutable` | 7 | 3 segments, real player |

Machine source shared by every fixture:

1. `Original first` (en, 0.500–4.000)
2. `Original kedua` (ms, 4.000–8.000)
3. `原始第三段` (zh, 8.000–12.000)

## Coverage → observed vs expected

1. **Valid interior split + invalidation + navigation** — `main`: entering
   structural mode and splitting segment 0 at `t=2.0`, text offset `8` shows
   `data-struct-notice`, `data-struct-revision-indicator` reads `Revision v2`
   (`data-struct-revision-state="revision"`), the first child renders `Original`
   (en) and the second `first` (en), and `data-seek-seconds` are `0.5` / `2`.
   The persisted invalidation is surfaced: `data-translation-staleness` is
   visible with `data-translation-stale="zh"` and
   `data-translation-stale-reason="SEGMENT_STRUCTURE_CHANGED"`. Reload keeps
   `Revision v2` and the stale marker. Home + ArrowDown walk position order
   (`Segment 1 of 4` → `Segment 2 of 4`). Expected: append-only revision, part
   language inherited, translation marked stale, navigation position-based.
   Observed: pass.
2. **Split boundary rejected** — `boundary`: a boundary equal to the start
   (`0.5`) and a text offset equal to the length (`14`) are each rejected with a
   visible accessible `data-struct-error`; the indicator stays `machine`; a
   reload confirms no write. Expected: strict-interior enforcement, no write.
   Observed: pass.
3. **Valid adjacent merge** — `merge`: selecting rows 0 and 1 and merging yields
   `data-struct-notice`, `Revision v2`, two rows, the first row's text is
   `Original first Original kedua` (single-space join, no trimming), and its
   language is `und` (mixed `en`/`ms`). Reload durability holds. Expected:
   adjacency-only merge, canonical join, mixed-language `und`. Observed: pass.
4. **Non-adjacent merge rejected** — `nonadjacent`: selecting rows 0 and 2 is
   rejected with `data-struct-error`; the indicator stays `machine`; reload
   confirms no write. Observed: pass.
5. **Stale structural conflict** — `conflict`: page 2 splits first (→
   `Revision v2`); page 1's stale structural save is rejected with a visible
   `data-struct-conflict`; the indicator remains `Revision v2` and no silent
   merge occurs. Expected: clear, accessible conflict. Observed: pass.
6. **Cancel / no write** — `cancel`: entering structural mode, choosing a split
   target, then cancelling hides the split controls and keeps the `machine`
   indicator; a reload still shows `machine`. Observed: pass.
7. **Ownership denial** — the non-owner context receives HTTP **403** for the
   transcript workspace. Observed: pass.
8. **Machine unchanged + P6-007 non-alignment** — `immutable`: splitting segment
   0 creates `Revision v2`; the comparison view shows
   `data-comparison-revision-state="alignment-unavailable"` for the structurally
   created segments (no index remap). Returning to the transcript view and
   Undo reaches `Revision v1` (the verbatim machine copy): `data-seek-seconds`
   first value is `0.5`, the first row is `Original first`, language `en`.
   Expected: immutable machine source; structural revisions not silently
   aligned. Observed: pass.

## Regression (adjacent browser suites, re-run after P6-005)

| Suite | Result |
|---|---|
| P6-003 `transcript-editing.spec.js` | 8 passed / 8 |
| P6-004 `timing-editing.spec.js` | 10 passed / 10 |
| P6-006 `advanced-navigation-filter.spec.js` | 7 passed / 7 |
| P6-007 `source-translation-comparison.spec.js` | 8 passed / 8 |

## Notes / residual

- `test-results/` (Playwright failure artifacts) is generated and gitignored; the
  authoritative evidence is the tracked `p6-005-browser-results.json`.
- The pre-existing `showRenameModal is not defined` page error (documented Phase 4
  debt) is unrelated to P6-005 and does not affect the passing suite.
- This evidence covers browser behaviour only; the invalidation lifecycle
  (first/repeated/precedence/causing-revision), byte-for-byte machine
  immutability, atomic rollback, and Phase 5 segment non-rewriting are
  additionally asserted by the PHP unit and feature suites.
- See `PHASE6-P6-005-IMPLEMENTATION-BATCH-REPORT.md` for the full PHP-suite and
  static-analysis results.