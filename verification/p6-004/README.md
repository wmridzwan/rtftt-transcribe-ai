# P6-004 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for P6-004 (Timing Editing
+ Validation), per DC-01. It drives real Chromium against the real Laravel
transcript workspace and the dedicated P6-004 verification database.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- `ffmpeg` on PATH (only to generate the fixture WAV if it is absent).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

```powershell
$env:DB_DATABASE = "database/p6-004-verification.sqlite"
php -d variables_order=EGPCS verification/p6-004-seed.php
```

Seeded fixtures (local, non-production), owner `p6-004@example.test` and
non-owner `p6-004-intruder@example.test`:

| Fixture | Shape |
|---|---|
| `main` | 3 multilingual segments, real WAV player — valid edit / reload / playback + active resolution |
| `negative` | 3 segments, real player — negative timestamp rejected |
| `inverted` | 3 segments, real player — `start > end` rejected |
| `zero` | 3 segments, real player — zero-length accepted but never active |
| `overlap` | 3 segments, real player — overlap accepted |
| `conflict` | 3 segments, real player — stale-write conflict from two pages |
| `cancel` | 3 segments, real player — cancel discards local timing edits |
| `immutable` | 3 segments, real player — machine-source timing unchanged |
| `nav` | 3 segments, real player — P6-006 navigation stays position-based |

## 2. Application server

The docroot **must** be `public` so `/build/assets/*` resolves:

```powershell
$env:DB_DATABASE = "database/p6-004-verification.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8126 -t public verification/p4-003-server-router.php
```

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-004.config.js
```

Results are written to the tracked file
`verification/p6-004/p6-004-browser-results.json`.

## 4. Required coverage (DC-01)

- valid timing edit; negative input rejected; `start > end` rejected;
- zero-length accepted; overlap accepted; reload durability;
- stale edit conflict; cancel / no write; machine-source timing unchanged;
- ownership denial;
- playback seek uses active-revision timing after save;
- active-segment resolution uses active-revision timing;
- P6-006 navigation remains position-based.