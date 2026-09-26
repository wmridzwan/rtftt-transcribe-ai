# P6-008 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for P6-008 (Revision
History / Audit Surface + explicit historical activation), per DC-01. It
drives real Chromium against the real Laravel transcript workspace and the
dedicated P6-008 verification database.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

```powershell
$env:DB_DATABASE = "database/p6-008-verification.sqlite"
php -d variables_order=EGPCS verification/p6-008-seed.php
```

Seeded fixtures (local, non-production), owner `p6-008@example.test` and
non-owner `p6-008-intruder@example.test`:

| Fixture | Shape |
|---|---|
| `plain` | machine source only, no revisions |
| `linear` | v1 initial + v2 text edit, v2 active |
| `branched` | v1 + v2, undo to v1, v3 branch (v3 active; v2 is a sibling) |
| `stale` | completed MS translation + structural split (persisted staleness cause) |

## 2. Application server

```powershell
$env:DB_DATABASE = "database/p6-008-verification.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8128 -t public verification/p4-003-server-router.php
```

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-008.config.js
```

Results are written to the tracked file
`verification/p6-008/p6-008-browser-results.json`.

## 4. Required coverage (DC-01)

- history list rendering in version order with persisted metadata;
- active-revision identification;
- machine-source authoritative state;
- historical selection + activation through the product path;
- reload durability of the active pointer;
- arbitrary sibling-branch activation;
- stale-base conflict presentation with no pointer move;
- persisted staleness-cause consistency with the P6-005 record;
- non-owner fencing (403, no history leaked).
