# P6-003 Browser Verification Evidence (DC-01)

Date: 2026-09-23
Task: P6-003 — Text Editing + Undo/Redo
Harness: `verification/playwright.p6-003.config.js` +
`verification/p6-003/transcript-editing.spec.js`
Result JSON: `verification/p6-003/p6-003-browser-results.json`
Outcome: **8 passed / 8** (real Chromium; dedicated P6-003 verification DB).

## Environment

- Runner: Playwright 1.63.0, project `chromium` (Desktop Chrome), headless.
- Server: `php -d variables_order=EGPCS -S 127.0.0.1:8124 -t public
  verification/p4-003-server-router.php` against
  `database/p6-003-verification.sqlite`.
- Fixture seeder: `verification/p6-003-seed.php` (`migrate:fresh` + deterministic
  completed transcriptions; owner `p6-003@example.test`, non-owner
  `p6-003-intruder@example.test`).
- HTTP assertion for isolation: `browser.newContext({ storageState: intruder })`
  then `request.get('/transcriptions/{id}')`.

## Fixture identity

| Fixture | Transcription id | Shape |
|---|---|---|
| `main` | 1 | 3 segments (en/ms/zh), real WAV player |
| `cancel` | 2 | 3 segments, real player |
| `conflict` | 3 | 3 segments, real player |
| `history` | 4 | 3 segments, real player |
| `branch` | 5 | 3 segments, real player |
| `immutable` | 6 | 3 segments, real player |

Segment labels shared by every fixture (machine source):

1. `Original first` (en, 00:00)
2. `Original kedua` (ms, 00:04)
3. `原始第三段` (zh, 00:08)

## Coverage → observed vs expected

1. **Enter edit mode** — `data-edit-enter` visible; after click the first
   `data-edit-text` is visible and focused, Save/Cancel appear, the read-only
   `data-segment-text` hides, and the indicator still reads `Machine transcript`.
   Expected: presentation-only; nothing persisted. Observed: pass.
2. **Save** — changing segment 1 text and saving shows the `data-revision-notice`
   live region, indicator `Revision v2` (`data-revision-state="revision"`), and
   the edited text. Expected: a new append-only revision becomes active.
   Observed: pass.
3. **Reload durability** — after a fresh reload the indicator still reads
   `Revision v2` and the edited text persists. Observed: pass.
4. **Cancel** — editing then cancelling hides the textareas, restores the
   read-only original text, keeps the `Machine transcript` indicator, and a
   reload still shows the original. Expected: no persisted change. Observed:
   pass.
5. **Stale edit conflict** — page 1 and page 2 both open the machine-source
   editor; page 2 saves first (→ `Revision v2`); page 1's save is rejected with a
   visible `data-revision-conflict` alert, the indicator remains `Revision v2`,
   and the rendered text is page 2's value. Expected: clear, accessible conflict;
   no silent merge/overwrite. Observed: pass.
6. **Undo / redo** — after saving `History v2`, Undo moves the indicator to
   `Revision v1` and renders the original machine text; Redo moves back to
   `Revision v2` and `History v2`. Expected: durable active-pointer movement.
   Observed: pass.
7. **Branch after undo** — save `Branch v2`, Undo to `Revision v1`, then edit
   again → `Revision v3` whose parent is v1. The Redo control is **disabled**
   (branch point has two children). Expected: old history durable; automatic redo
   must not guess among siblings. Observed: pass.
8. **Ownership denial** — the non-owner context receives HTTP **403** for the
   transcript workspace (and therefore no edit surface). Expected: non-owner
   cannot view or edit. Observed: pass.
9. **Machine-source immutability** — after editing segment 1 to `Mutating edit`,
   Undo reaches `Revision v1`, which renders the verbatim original machine text;
   a reload still shows the original. Expected: the immutable machine source is
   unchanged. Observed: pass.

## Notes / residual

- `test-results/` (Playwright failure artifacts) is generated and gitignored; the
  authoritative evidence is the tracked `p6-003-browser-results.json`.
- The pre-existing `showRenameModal is not defined` page error (documented Phase 4
  debt) remains unrelated to P6-003 and does not affect the passing suite.
- This evidence covers browser behaviour only; byte-for-byte machine immutability
  and revision-row immutability are additionally asserted by
  `tests/Feature/Editing/TranscriptEditingWorkspaceTest.php` and
  `tests/Feature/Editing/MachineSourceImmutabilityTest.php`.
