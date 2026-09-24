# P6-007 Browser Verification Evidence (DC-01)

Date: 2026-09-23 (corrective re-run 2026-09-24)
Task: P6-007 — Source / Translation Comparison (presentation-only)
Harness: `verification/playwright.p6-007.config.js` +
`verification/p6-007/source-translation-comparison.spec.js`
Result JSON: `verification/p6-007/p6-007-browser-results.json`
Outcome: **8 passed / 8** (real Chromium; dedicated P6-007 verification DB).

The 2026-09-24 corrective re-run re-seeded a fresh verification DB and re-executed
the committed spec end-to-end; the only spec change is an added assertion that the
per-row "Edited after the translation was produced" note is **absent** for the
`none` fixture (edited revision, no translation), closing the MEDIUM
presentation-truthfulness finding.

## Environment

- Runner: Playwright 1.63.0, project `chromium` (Desktop Chrome), headless.
- Server: `php -d variables_order=EGPCS -S 127.0.0.1:8125 -t public
  verification/p4-003-server-router.php` against
  `database/p6-007-verification.sqlite`.
- Fixture seeder: `verification/p6-007-seed.php` (owner `p6-007@example.test`,
  non-owner `p6-007-intruder@example.test`).
- Isolation assertion: `browser.newContext({ storageState: intruder })` then
  `request.get('/transcriptions/{id}')` → HTTP 403.

## Fixture identity

| Fixture | Transcription id | Shape | Machine texts | ZH translation |
|---|---|---|---|---|
| `plain` | 1 | machine source only | `Machine first` / `Machine kedua` / `机器第三` | `Translated first` / `Terjemahan kedua` / `翻译第三` |
| `edited` | 2 | edited revision v2 | same | same |
| `identical` | 3 | initial revision only | same | same |
| `none` | 4 | edited revision v2 | same | (none) |
| `structural` | 5 | `new:*` identities revision | same | same |

Edited revision texts used by `edited`/`none`:
`Edited revision first` / `Edited revision kedua` / `已编辑第三`.

## Coverage → observed vs expected

1. **Comparison toggle/view** — on `plain`, the normal `[data-transcript-region]`
   is visible and the `[data-comparison-panel]` hidden; clicking `Compare` shows
   the panel, hides the normal region, sets `aria-pressed="true"`, and keeps the
   page (no navigation). Clicking `Transcript` restores. Observed: pass.
2. **Machine source ↔ translation by `segment_index`** — `plain` shows the
   `machine` state, target `ZH`, and each row's translation cell matches the
   persisted translation for that machine index. Observed: pass.
3. **No active revision** — `plain` shows `Machine source is authoritative`; the
   no-translation status is hidden. `none` shows the `revision` state, the
   no-translation status visible, and the machine-vs-revision comparison still
   works (edited text shown) without a translation. On `none` the per-row
   `[data-comparison-revision-edited-note]` and `[data-comparison-mismatch-note]`
   are asserted **absent** (count 0), so no false chronology/provenance claim is
   rendered when no translation was ever persisted. Observed: pass.
4. **Edited revision vs translation** — `edited` shows `Active revision v2`, the
   explicit `data-comparison-mismatch-note` ("translation is of the original
   machine source …"), the per-row "Edited after the translation was produced"
   note, the machine-source translation in the translation column, and the edited
   revision text in the revision column. Observed: pass.
5. **Textually identical revision** — `identical` shows the revision state and
   the machine text, with **no** mismatch note. Observed: pass.
6. **Structural incompatibility** — `structural` shows
   `data-comparison-revision-state="alignment-unavailable"` for the `new:*`
   segments; the machine-aligned translation remains in the translation column,
   and unaligned revision rows show `—` in the translation column (never remapped
   by index). Observed: pass.
7. **No mutation from comparison actions** — on `edited`, toggling between
   Transcript and Compare produces no `data-revision-notice` /
   `data-revision-error`; after reload the active revision is still `v2` and the
   edited text is unchanged. Observed: pass.
8. **Authorization/isolation** — a non-owner context receives HTTP 403 for the
   transcript workspace (and therefore no comparison). Observed: pass.

## Notes / residual

- `test-results/` (Playwright failure artifacts) is generated and gitignored; the
  authoritative evidence is the tracked `p6-007-browser-results.json`.
- No freshness/staleness state is displayed anywhere: only persisted facts
  (machine text, revision text, translation text, target language, and the
  explicit structural mismatch note defined by the P6-007 contract).
- Backend no-mutation and alignment guarantees are additionally asserted by
  `tests/Feature/Comparison/SourceTranslationComparisonTest.php`.
