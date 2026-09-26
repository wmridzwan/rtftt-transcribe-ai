# P6-009-RERUN-01 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for the P6-009 full
final-gate rerun (`P6-009-RERUN-01`, after P6-010 DONE), per DC-01. It drives
real Chromium against the real Laravel transcript workspace and a dedicated
rerun database. It never reads or writes the original failed-run artifacts
under `verification/p6-009/`.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

```powershell
$env:DB_DATABASE = "database/p6-009-rerun-01.sqlite"
php -d variables_order=EGPCS verification/p6-009-rerun-01-seed.php
```

Seeded fixtures (local, non-production), owner `p6-009@example.test` and
non-owner `p6-009-intruder@example.test` — same shapes as the original run:

| Fixture | Shape |
|---|---|
| `plain` | machine source only, no revisions |
| `linear` | v1 initial + v2 text edit, v2 active |
| `branched` | v1 + v2, undo to v1, v3 branch (v3 active; v2 is a sibling) |
| `branched2` | same shape, reserved for the stale-base conflict check |
| `stale` | completed MS translation + structural split (persisted staleness cause) |

Fixture map: `verification/p6-009-rerun-01-fixtures.json`.

## 2. Application server (rerun port 8130)

```powershell
$env:DB_DATABASE = "database/p6-009-rerun-01.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8130 -t public verification/p4-003-server-router.php
```

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-009-rerun-01.config.js
```

Results: `verification/p6-009-rerun-01/p6-009-rerun-01-browser-results.json`
(12/12 passed at rerun execution).

## 4. Required coverage (DC-01) + rerun delta

Same 12 integrated scenarios as the original run, renamed `P6-009-RERUN-01`
through `P6-009-RERUN-12`, with one strengthened check:

- `P6-009-RERUN-11`: export downloads carry the **active revision** text
  (TXT/SRT/VTT content assertions against edited vs machine strings) with
  machine-source fallback on the plain fixture — the F-001 regression check
  at browser level. The original run asserted entry points + 200 status only.
