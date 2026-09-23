# P6-006 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for P6-006 (Advanced
Navigation + Search/Filter), per DC-01. It drives real Chromium against the real
Laravel workspace page and the dedicated P6-006 verification database.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- `ffmpeg` on PATH (only to generate the fixture WAV if it is absent).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

The seeder boots Laravel against the database selected by `DB_DATABASE`,
migrates it fresh, seeds deterministic transcriptions, and writes
`verification/p6-006-fixtures.json`:

```powershell
$env:DB_DATABASE = "database/p6-006-verification.sqlite"
php -d variables_order=EGPCS verification/p6-006-seed.php
```

Seeded fixtures (local, non-production):

| Fixture | Shape |
|---|---|
| `filter` | 8 segments across `en/ms/zh/ta/und`, real WAV player |
| `overlap` | overlapping intervals (`seg1` starts inside `seg0`) + a zero-length final segment, real player |
| `noMedia` | segments present, private object absent (no player) |

## 2. Application server

The docroot **must** be `public` so `/build/assets/*` resolves; the router serves
existing static files and forwards everything else to Laravel:

```powershell
$env:DB_DATABASE = "database/p6-006-verification.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8123 -t public verification/p4-003-server-router.php
```

(Historical evidence that omitted `-t public` served no app CSS; use the command
above.)

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-006.config.js
```

Results are written to the tracked file
`verification/p6-006/p6-006-browser-results.json` (the `list` reporter also prints
to stdout). The JSON is intentionally **not** under the gitignored
`verification/artifacts/` path.

## 4. Related Phase 4 regression suites

The P4-003/P4-004/P4-006 selectors are the reserved compatibility hooks
(`data-seek-seconds`, `data-segment-language`). To re-run those suites, seed the
matching DB and start the server against it:

```powershell
# P4-004 + P4-006 (share the P4-006 DB/fixtures)
$env:DB_DATABASE = "database/p4-006-verification.sqlite"
php -d variables_order=EGPCS verification/p4-006-seed.php
# ... start the server with that DB, then:
node_modules/.bin/playwright.cmd test -c verification/playwright.p4-004.config.js
node_modules/.bin/playwright.cmd test -c verification/playwright.p4-006.config.js

# P4-003
$env:DB_DATABASE = "database/p4-003-verification.sqlite"
php -d variables_order=EGPCS verification/p4-003-seed.php
# ... start the server with that DB, then:
node_modules/.bin/playwright.cmd test -c verification/playwright.config.js
```

The P4-003 audio-playback test (`audio: ... playback`) is a known environmental
flake on this machine (the audio element reports `currentTime === 0` after the
fixed wait); it is unrelated to P6-006. The seek-locator tests, which are the
H-1 regression surface, pass.
