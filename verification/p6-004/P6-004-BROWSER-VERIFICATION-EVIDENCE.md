# P6-004 Browser Verification Evidence (DC-01)

Date: 2026-09-24
Task: P6-004 — Timing Editing + Validation
Harness: `verification/playwright.p6-004.config.js` +
`verification/p6-004/timing-editing.spec.js`
Result JSON: `verification/p6-004/p6-004-browser-results.json`
Outcome: **10 passed / 10** (real Chromium; dedicated P6-004 verification DB).

## Environment

- Runner: Playwright 1.63.0, project `chromium` (Desktop Chrome), headless.
- Server: `php -d variables_order=EGPCS -S 127.0.0.1:8126 -t public
  verification/p4-003-server-router.php` against
  `database/p6-004-verification.sqlite`.
- Fixture seeder: `verification/p6-004-seed.php` (`migrate:fresh` + deterministic
  completed transcriptions; owner `p6-004@example.test`, non-owner
  `p6-004-intruder@example.test`).
- HTTP assertion for isolation: `browser.newContext({ storageState: intruder })`
  then `request.get('/transcriptions/{id}')`.
- Real audio player: generated 15 s WAV (`verification/fixtures/p6-004-audio.wav`),
  streamed through the authorized `/stream` route.

## Fixture identity

| Fixture | Transcription id | Shape |
|---|---|---|
| `main` | 1 | 3 segments (en/ms/zh), real WAV player |
| `negative` | 2 | 3 segments, real player |
| `inverted` | 3 | 3 segments, real player |
| `zero` | 4 | 3 segments, real player |
| `overlap` | 5 | 3 segments, real player |
| `conflict` | 6 | 3 segments, real player |
| `cancel` | 7 | 3 segments, real player |
| `immutable` | 8 | 3 segments, real player |
| `nav` | 9 | 3 segments, real player |

Machine source shared by every fixture:

1. `Original first` (en, 0.500–4.000)
2. `Original kedua` (ms, 4.000–8.000)
3. `原始第三段` (zh, 8.000–12.000)

## Coverage → observed vs expected

1. **Valid timing edit** — `main`: entering timing mode and saving
   `5.0–9.0 / 4.0–8.0 / 8.0–12.0` shows `data-timing-notice`, the
   `data-timing-revision-indicator` reads `Revision v2`
   (`data-timing-revision-state="revision"`), and the rendered
   `data-timing-current-*` values update. Expected: a new append-only revision
   becomes active. Observed: pass.
2. **Negative input rejected** — `negative`: start `-1.0` is rejected with a
   visible accessible `data-timing-error`; the revision indicator stays
   `machine`; a reload confirms no write. Expected: server-side rejection, no
   write. Observed: pass.
3. **`start > end` rejected** — `inverted`: `8.0 / 2.0` is rejected with
   `data-timing-error`; the indicator stays `machine`. Observed: pass.
4. **Zero-length accepted** — `zero`: `5.0–5.0` persists
   (`data-timing-current-start == "5"`, `data-timing-current-end == "5"`), and at
   `t=5.0` the zero-length segment is never active (position 1 is active).
   Expected: legal row, never active. Observed: pass.
5. **Overlap accepted** — `overlap`: two segments both `2.0–6.0` persist; the
   revision is v2. Expected: overlaps are legal. Observed: pass.
6. **Reload durability** — `main` reload keeps `Revision v2` and the edited
   timing. Observed: pass.
7. **Stale edit conflict** — `conflict`: page 1 and page 2 open the timing editor;
   page 2 saves first (→ `Revision v2`, start `2`); page 1's save is rejected with
   a visible `data-timing-conflict`; the indicator remains `Revision v2` and the
   rendered timing is page 2's value. Expected: clear, accessible conflict; no
   silent merge. Observed: pass.
8. **Cancel / no write** — `cancel`: changing a start input then cancelling hides
   the inputs, restores the rendered value, keeps the `machine` indicator, and a
   reload still shows the original timing. Observed: pass.
9. **Machine-source timing unchanged** — `immutable`: after saving `10.0–11.0`
   (`Revision v2`), Undo to `Revision v1` (the verbatim machine copy) renders the
   original `data-timing-current-start` (`0.5`). Expected: immutable machine
   source. Observed: pass. (Byte-for-byte machine immutability is additionally
   asserted by `tests/Feature/Editing/TranscriptTimingWorkspaceTest.php`.)
10. **Ownership denial** — the non-owner context receives HTTP **403** for the
    transcript workspace. Observed: pass.
11. **Playback seek uses active-revision timing after save** — `main`: after the
    edit, `[data-seek-seconds]` first value is `5`; clicking it seeks the player
    and activates position 0. Expected: active-revision timing drives seek.
    Observed: pass.
12. **Active-segment resolution uses active-revision timing** — `main`: at
    `t=5.0` the machine source resolved position 1, but after the edit the
    lowest-position overlapping segment (position 0, `5.0–9.0`) is active.
    Expected: revision timing is the playback source of truth. Observed: pass.
13. **P6-006 navigation remains position-based** — `nav`: after an out-of-time-
    order edit (`position 0` = `10.0–12.0`, `position 1` = `0.0–3.0`), Home +
    ArrowDown walk position order (`Segment 1 of 3` → `Segment 2 of 3`) while
    `[data-nav-seconds]` for position 0 is `10`. Expected: ordering by revision
    `position`, never by timestamp. Observed: pass.

## Notes / residual

- `test-results/` (Playwright failure artifacts) is generated and gitignored; the
  authoritative evidence is the tracked `p6-004-browser-results.json`.
- The pre-existing `showRenameModal is not defined` page error (documented Phase 4
  debt) is unrelated to P6-004 and does not affect the passing suite.
- This evidence covers browser behaviour only; per-segment validation
  (finite/non-negative/`start <= end`/precision), revision classification, and
  byte-for-byte machine immutability are additionally asserted by the PHP unit and
  feature suites.
- See `PHASE6-P6-004-IMPLEMENTATION-BATCH-REPORT.md` for the cross-suite Phase 4
  regression notes (known environmental playback flake; pre-existing P6-007
  comparison interaction with the older P4-006 V4-13 spec).