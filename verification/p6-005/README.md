# P6-005 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for P6-005 (Split / Merge +
Translation Invalidation), per DC-01. It drives real Chromium against the real
Laravel transcript workspace and the dedicated P6-005 verification database.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- `ffmpeg` on PATH (only to generate the fixture WAV if it is absent).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

```powershell
$env:DB_DATABASE = "database/p6-005-verification.sqlite"
php -d variables_order=EGPCS verification/p6-005-seed.php
```

Seeded fixtures (local, non-production), owner `p6-005@example.test` and
non-owner `p6-005-intruder@example.test`:

| Fixture | Shape |
|---|---|
| `main` | 3 multilingual segments, real WAV player, completed `zh` translation — valid split / reload durability / invalidation surfaced / navigation |
| `merge` | 3 multilingual segments, real WAV player — valid adjacent merge (mixed-language → `und`) |
| `boundary` | 3 segments, real WAV player — split at a boundary rejected |
| `nonadjacent` | 3 segments, real WAV player — non-adjacent merge rejected |
| `conflict` | 3 segments, real WAV player — stale structural conflict from two pages |
| `cancel` | 3 segments, real WAV player — cancel discards the structural selection |
| `immutable` | 3 segments, real WAV player — machine source unchanged; P6-007 non-alignment |

## 2. Application server

The docroot **must** be `public` so `/build/assets/*` resolves:

```powershell
$env:DB_DATABASE = "database/p6-005-verification.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8127 -t public verification/p4-003-server-router.php
```

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-005.config.js
```

Results are written to the tracked file
`verification/p6-005/p6-005-browser-results.json`.

## 4. Required coverage (DC-01)

- valid interior split (persist + visible result + reload durability);
- split at a boundary rejected with an accessible error and no write;
- resulting text/timing/language;
- revision-history preservation (undo to the machine copy);
- persisted translation invalidation visible/durable;
- valid adjacent merge (plain-space join; mixed-language → `und`);
- non-adjacent merge rejected;
- reload durability;
- stale structural conflict (clear, accessible, no silent merge);
- cancel / no write;
- ownership denial;
- machine source unchanged;
- P6-006 navigation remains position-based after structural edits;
- P6-007 does not silently align a structurally changed revision to the machine
  translation.