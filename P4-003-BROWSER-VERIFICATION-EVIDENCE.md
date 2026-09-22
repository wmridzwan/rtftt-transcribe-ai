# P4-003 — Browser Verification Evidence (Builder)

Date: 2026-09-20
Author: OpenCode (Builder)
Task: `tasks/P4-003-segment-navigation-synchronized-highlighting.md`
Decision: `DECISION-P4-BROWSER-VERIFICATION-001` (documented, reproducible,
environment-dependent browser verification with retained evidence; no browser
automation platform)

## 0. Execution Status

**Runtime browser execution: COMPLETED (2026-09-20) via authorized Playwright
verification tooling** (`DECISION-P4-BROWSER-VERIFICATION-002`). All ten
Playwright checks passed; actual observed values are recorded in
"EXECUTED RESULTS" below. The original protocol/fixture/expected-value sections
(§1–§11) are preserved as the reproducible plan.

History preserved: the initial status was protocol-only because the CLI
implementation agent had no GUI browser and no authorized automation
(`DECISION-P4-BROWSER-VERIFICATION-001`); that limitation triggered the tooling
reconciliation.

Backend automated tests are reported separately and are **not** represented as
proving browser behavior.

## 1. Environment Metadata

- OS: Windows (win32) — `[EXECUTOR: confirm]`
- Browser + version: `[EXECUTOR: e.g. Chrome 1xx / Edge 1xx / Firefox 1xx]`
- Application environment: local dev (`php artisan serve` / Herd), PHP 8.4,
  Laravel 13.17, Livewire 4.1, Alpine (bundled with Livewire)
- Frontend build: `npm run build` (or `npm run dev`) so the Vite manifest exists
- Database: local development SQLite only (never production/user data)
- Stream route: `GET /media/{mediaFile:uuid}/stream` (name `media.stream`)
- Verification date/time: `[EXECUTOR: record]`

Do not record secrets, tokens, or private filesystem paths.

## 2. Deterministic Fixture

Use a dedicated local development user and two uploaded media fixtures (one
audio, one video). Do not modify production or real user data.

Recommended fixture (audio and video use the same segment layout):

| segment_index | start_seconds | end_seconds | language | text (suggestion) |
|---|---|---|---|---|
| 0 | 0.000 | 4.000 | en | First segment |
| 1 | 4.000 | 8.500 | ms | Segmen kedua |
| 2 | 12.345 | 15.000 | zh | 第三段 |
| 3 | 15.000 | 20.000 | ta | நான்காம் |

This layout intentionally includes: an exact start (4.000), an exact end
(8.500), a gap (8.500 → 12.345), a non-integer start (12.345), and a boundary
pair (15.000 is the exact end of segment 2 and the exact start of segment 3).

Setup recipe (local dev only; adjust IDs):

1. Upload one audio fixture and one video fixture through the normal upload flow.
2. Create one completed transcription per media fixture with the segment layout
   above, e.g. via `php artisan tinker` against the local dev database:

```php
$user = App\Models\User::factory()->create(); // or an existing local test user
$media = App\Models\MediaFile::latest('id')->first(); // the uploaded fixture

$t = App\Models\Transcription::factory()->completed()->create([
    'user_id' => $user->id,
    'media_file_id' => $media->id,
    'title' => 'P4-003 audio fixture',
]);

$rows = [
    [0, 0.000, 4.000, 'en', 'First segment'],
    [1, 4.000, 8.500, 'ms', 'Segmen kedua'],
    [2, 12.345, 15.000, 'zh', '第三段'],
    [3, 15.000, 20.000, 'ta', 'நான்காம்'],
];
foreach ($rows as [$i, $s, $e, $lang, $text]) {
    $t->segments()->create([
        'segment_index' => $i, 'start_seconds' => $s, 'end_seconds' => $e,
        'language' => $lang, 'text' => $text,
    ]);
}
```

Repeat for the video fixture. Fixture duration must be at least 20 seconds.

## 3. Expected Values (computed from the fixture)

Active-segment semantics (canonical P4-001): `start_seconds <= currentTime <
end_seconds`, lowest `segment_index` wins on overlap, zero-length never active.

| currentTime | expected active segment_index |
|---|---|
| 0.000 | 0 |
| 3.999 | 0 |
| 4.000 | 1 |
| 8.499 | 1 |
| 8.500 | none (exact end; gap begins) |
| 10.000 | none (gap) |
| 12.344 | none (gap) |
| 12.345 | 2 |
| 14.999 | 2 |
| 15.000 | 3 |
| 19.999 | 3 |
| 20.000 | none (after final) |

Console parity check (run in the browser devtools console; must return `true`):

```js
const segs = [
  {index:0,start:0,end:4},
  {index:1,start:4,end:8.5},
  {index:2,start:12.345,end:15},
  {index:3,start:15,end:20},
];
[0, 3.999, 4, 8.499, 8.5, 10, 12.344, 12.345, 14.999, 15, 19.999, 20].map((t) => window.p4ResolveActive(segs, t));
// expected: [0,0,1,1,null,null,null,2,2,3,3,null]
```

## 4. Required Audio Browser Steps

Record `[EXECUTOR: ...]` for each.

1. Sign in as the fixture owner and open the audio transcription workspace.
   - `[EXECUTOR: workspace opened? yes/no]`
2. Confirm `<audio controls>` loads and `src` is the `media.stream` URL (not a
   filesystem path). `[EXECUTOR: src host/path shape, without secrets]`
3. Start playback; confirm it plays. `[EXECUTOR: playing? yes/no]`
4. Click the timestamp button for segment 2 (12.345).
   - Expected seek target: `12.345`
   - `[EXECUTOR: observed player.currentTime after seek]`
   - `[EXECUTOR: difference vs 12.345]`
5. Confirm segment 2 becomes active (`aria-current="true"` on its row).
   `[EXECUTOR: active index]`
6. Manually seek the native player into the gap (≈10.0s); confirm no active row.
   `[EXECUTOR: active index / none]`
7. Manually seek to 15.000; confirm segment 3 is active (not segment 2).
   `[EXECUTOR: active index]`
8. Pause and resume; confirm active semantics unchanged at the same time.
   `[EXECUTOR: observation]`
9. Confirm no unexpected P4-003 console errors. `[EXECUTOR: console state]`
10. Attach screenshots/recording. `[EXECUTOR: file names/paths]`

## 5. Required Video Browser Steps

Repeat §4 with the video fixture. Audio evidence does not prove video parity.

- `[EXECUTOR: <video> src is media.stream URL]`
- `[EXECUTOR: playback works]`
- `[EXECUTOR: click timestamp 12.345 → observed currentTime / difference]`
- `[EXECUTOR: active segment sync]`
- `[EXECUTOR: manual seek updates active segment]`

## 6. Seek Precision Evidence

For at least one non-integer timestamp (fixture: `12.345`):

```text
expected start_seconds: 12.345
observed currentTime:   [EXECUTOR]
difference:             [EXECUTOR]
```

Browser/media timing may introduce tiny runtime variance; record the actual
value rather than asserting binary equality. A screenshot of the formatted
timestamp alone is not sufficient evidence.

## 7. Boundary Evidence

Using the fixture table in §3, exercise and record at least: exact start
(4.000), exact end (8.500), gap (10.000), before first (seek near 0 with an
earlier boundary is not applicable at 0.000 — use a second fixture if desired),
and after final (20.000). Confirm the browser active state matches §3.

- `[EXECUTOR: per-time observed active index]`

## 8. Auto-Scroll Evidence

1. Scroll the transcript region so the active segment is out of view.
2. Let playback advance until the active segment changes.
   - Confirm the new active row becomes visible. `[EXECUTOR]`
3. With the active row already visible, confirm no unnecessary jump.
   `[EXECUTOR]`
4. Manually wheel-scroll the transcript; confirm auto-scroll is suspended.
   `[EXECUTOR]`
5. Perform an explicit playback interaction (play/pause/seek); confirm
   auto-scroll resumes. `[EXECUTOR]`

## 9. Keyboard / Accessibility Evidence

1. Tab to a timestamp button; confirm visible focus ring. `[EXECUTOR]`
2. Activate it with Enter/Space; confirm media seeks. `[EXECUTOR]`
3. Confirm the active row carries `aria-current="true"` and that active state is
   not conveyed by color alone (row also has a ring). `[EXECUTOR]`
4. Confirm auto-scroll does not steal keyboard focus. `[EXECUTOR]`

This is a baseline check, not full WCAG certification.

## 10. Search Coexistence Evidence

1. Enter a search query matching text in an active segment; confirm search
   `<mark>` highlighting appears. `[EXECUTOR]`
2. With a playback active segment present, confirm both states coexist (search
   `<mark>` inside the row and `aria-current` on the row). `[EXECUTOR]`
3. Clear search; confirm search highlights are removed and the playback active
   state remains. `[EXECUTOR]`

## 11. Reproducibility Notes

- The fixture is deterministic; the expected values in §3 are stable.
- A reviewer can repeat this protocol independently in a local environment.
- If the active-segment behavior diverges from §3, treat it as a P4-003 defect
  and follow the task's correction path; if it reveals a predecessor
  (P4-002/P4-004) regression, stop and report for governance handling.

## 12. EXECUTED RESULTS (2026-09-20)

Executed by the Builder with authorized Playwright verification tooling
(`DECISION-P4-BROWSER-VERIFICATION-002`). Backend tests are not represented as
proving this behavior; these are real browser runtime observations.

### Environment

- Date/time (UTC): 2026-09-20T07:08:32Z – 2026-09-20T07:08:49Z
- OS: Windows (win32)
- Browser: Chromium headless shell 153.0.8010.12 (Playwright
  chromium-headless-shell v1243)
- Playwright: 1.63.0 (dev dependency)
- Application: local `php -S` server at `http://127.0.0.1:8123` against a
  dedicated SQLite DB `database/p4-003-verification.sqlite`; PHP 8.4,
  Laravel 13.17, Livewire 4.1
- Auth: legitimate Fortify login as the seeded verified owner (session reused
  via Playwright `storageState`; no auth bypass)
- Fixtures: `p4-003-audio.wav` (`audio/wav`, 25s);
  `p4-003-video.webm` (`video/webm`, 25.008s); no-speech audio fixture
  (completed, `speech_detected=false`, no segments)
- Stream route: `GET /media/{mediaFile:uuid}/stream` (uuid values recorded in
  the results JSON; no storage path exposed)

### Commands

```text
# DB_DATABASE and APP_URL set in the environment:
php -d variables_order=EGPCS -S 127.0.0.1:8123 -t public verification/p4-003-server-router.php
node_modules/.bin/playwright test --config=verification/playwright.config.js
```

Result: **10 passed (19.2s)**. Machine-readable results:
`verification/artifacts/p4-003-browser-results.json`.

### Audio

- Element: `<audio>`; `src` = `http://127.0.0.1:8123/media/<uuid>/stream`
  (authorized `media.stream` route; not a storage URL)
- `duration` = 25; metadata loaded (`readyState >= 1`)
- Playback: `play()` resolved; `currentTime` advanced to `0.071546`
- Timestamp seek (segment 2): expected `12.345`, observed `12.345`,
  difference `0`; active segment = `2`

### Video

- Element: `<video>`; `src` = `http://127.0.0.1:8123/media/<uuid>/stream`
- `duration` = 25.008; metadata loaded
- Playback: `play()` resolved; `currentTime` advanced to `1.154992`

### Resolver parity (PHP canonical vs browser `window.p4ResolveActive`)

All 12 samples matched the canonical P4-001 expectations:

| time | expected | observed |
|---|---|---|
| 0.500 | 0 | 0 |
| 3.999 | 0 | 0 |
| 4.000 | 1 | 1 |
| 8.249 | 1 | 1 |
| 8.250 | none | none |
| 10.000 | none | none |
| 12.344 | none | none |
| 12.345 | 2 | 2 |
| 16.499 | 2 | 2 |
| 16.500 | 3 | 3 |
| 24.000 | none | none |
| 25.000 | none | none |

### Boundaries / gaps (real DOM `aria-current`)

Identical to the parity table above: all 12 samples matched in the live DOM
(`aria-current` on the expected row; none during the gap, before first, and
after final).

### Manual native seek

- `currentTime = 17.0` → active `3`
- `currentTime = 13.0` → active `2`

### Auto-scroll

- Region: `scrollHeight` 356 > `clientHeight` 294 (scrollable)
- Far active segment (22.5) scrolled into view: `scrollTop` = 62
- Already-visible active segment (21.0): delta `scrollTop` = 0 (no jump)
- Manual wheel scroll then playback crossed a boundary (21.0 → 22.6):
  `scrollTop` stayed 0 (suspended) with active `7`
- Explicit seek (22.5) resumed: `scrollTop` = 62

### Keyboard / accessibility

- Focused timestamp button `data-seek-seconds="12.345"`
- Enter activated it: `currentTime` = `12.345`; active segment = `2`
- Active row carries `aria-current="true"` (plus ring; not color alone)

### Search highlight coexistence (P4-004)

- Search query active: 5 `<mark>` search highlights
- Playback active segment present simultaneously: active = `2`
- After clearing search: `<mark>` count = 0; playback active remained `2`
- No P4-004 regression observed

### No-speech

- Seek controls = 0; segment rows = 0; player present = 1; empty-state = 1
- No JavaScript errors attributable to the no-speech workspace

### Console / errors

- `consoleErrors`: none
- `pageErrors`: 10× `ReferenceError: showRenameModal is not defined`
  (one per page load). This is a **pre-existing rename-modal Alpine scoping
  issue** in `resources/views/transcriptions/show.blade.php`, unrelated to
  P4-003 (the modal markup and its `x-bind:show="open || showRenameModal"`
  binding predate this task and are outside its scope). It does not affect
  P4-003 playback/search behavior. Reported, not silently changed.

### Retained artifacts

- `verification/artifacts/p4-003-browser-results.json` (structured results)
- `verification/artifacts/p4-003-audio-workspace.png`
- `verification/artifacts/p4-003-video-workspace.png`
- `verification/artifacts/p4-003-search-coexistence.png`
- `verification/artifacts/p4-003-no-speech.png`
- `verification/artifacts/auth-state.json` (session state; no secrets beyond a
  local test session; gitignored)

Generated binaries/fixtures and artifacts are gitignored
(`verification/artifacts/`, `verification/fixtures/`,
`database/p4-003-verification.sqlite`, `test-results/`, `playwright-report/`).

### Summary for Independent Review

- Implementation and automated tests: complete (see task file).
- Browser runtime evidence: **COMPLETE** — ten Playwright checks passed with
  real observed values; reproducible via the harness and commands above.
- One pre-existing, non-P4-003 page error (`showRenameModal`) is recorded for
  governance awareness.
