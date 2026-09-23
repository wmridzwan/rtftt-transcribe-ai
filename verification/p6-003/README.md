# P6-003 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for P6-003 (Text Editing +
Undo/Redo), per DC-01. It drives real Chromium against the real Laravel
transcript workspace and the dedicated P6-003 verification database.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- `ffmpeg` on PATH (only to generate the fixture WAV if it is absent).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

```powershell
$env:DB_DATABASE = "database/p6-003-verification.sqlite"
php -d variables_order=EGPCS verification/p6-003-seed.php
```

Seeded fixtures (local, non-production), owner `p6-003@example.test` and
non-owner `p6-003-intruder@example.test`:

| Fixture | Shape |
|---|---|
| `main` | 3 multilingual segments (en/ms/zh), real WAV player — save / reload durability |
| `cancel` | 3 segments, real player — cancel discards local edits |
| `conflict` | 3 segments, real player — stale-write conflict from two pages |
| `history` | 3 segments, real player — undo / redo |
| `branch` | 3 segments, real player — new edit after undo disables automatic redo |
| `immutable` | 3 segments, real player — machine-source immutability after editing |

## 2. Application server

The docroot **must** be `public` so `/build/assets/*` resolves:

```powershell
$env:DB_DATABASE = "database/p6-003-verification.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8124 -t public verification/p4-003-server-router.php
```

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-003.config.js
```

Results are written to the tracked file
`verification/p6-003/p6-003-browser-results.json`.

## 4. Required coverage (DC-01)

- enter edit mode; save; cancel; reload durability;
- stale edit conflict (clear, accessible, no silent merge);
- undo; redo; branch-after-undo (redo unavailable; old history durable);
- ownership denial (non-owner cannot view/edit);
- machine-source immutability (original machine text still intact after editing).
