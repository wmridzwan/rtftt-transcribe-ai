# P6-009 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for P6-009
(FINAL_GATE_ONLY Phase 6 integration verification), per DC-01. It drives real
Chromium against the real Laravel transcript workspace and the dedicated
P6-009 verification database.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

```powershell
$env:DB_DATABASE = "database/p6-009-verification.sqlite"
php -d variables_order=EGPCS verification/p6-009-seed.php
```

Seeded fixtures (local, non-production), owner `p6-009@example.test` and
non-owner `p6-009-intruder@example.test`:

| Fixture | Shape |
|---|---|
| `plain` | machine source only, no revisions |
| `linear` | v1 initial + v2 text edit, v2 active |
| `branched` | v1 + v2, undo to v1, v3 branch (v3 active; v2 is a sibling) |
| `branched2` | same shape, reserved for the stale-base conflict check |
| `stale` | completed MS translation + structural split (persisted staleness cause) |

## 2. Application server

```powershell
$env:DB_DATABASE = "database/p6-009-verification.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8129 -t public verification/p4-003-server-router.php
```

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-009.config.js
```

Results are written to the tracked file
`verification/p6-009/p6-009-browser-results.json`.

## 4. Required coverage (DC-01)

- workspace presents the active revision with history + comparison hooks;
- machine-source authoritative state;
- undo / redo through the product path (order-agnostic);
- sibling-branch activation beyond undo reach;
- reload durability of the active pointer;
- stale-base conflict presentation with no pointer move;
- persisted staleness-cause consistency with the P6-005 record;
- comparison truthfulness (mismatch note; no-translation state);
- export entry points present with a 200 TXT download;
- non-owner fencing (403, no workspace leaked).
