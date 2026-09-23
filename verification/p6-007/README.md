# P6-007 Browser Verification Harness — Runbook

Committed, reproducible browser-verification tooling for P6-007 (Source /
Translation Comparison; presentation-only), per DC-01. It drives real Chromium
against the real Laravel transcript workspace and the dedicated P6-007
verification database.

## Prerequisites

- PHP 8.4 (`php` on PATH; Herd `php84`).
- Node + Playwright with Chromium installed (`node_modules/.bin/playwright.cmd`).
- `ffmpeg` on PATH (only to generate the fixture WAV if it is absent).
- Built front-end assets (`npm run build`) so `/build/assets/*` resolves.

## 1. Fixtures and database

```powershell
$env:DB_DATABASE = "database/p6-007-verification.sqlite"
php -d variables_order=EGPCS verification/p6-007-seed.php
```

Seeded fixtures (local, non-production), owner `p6-007@example.test` and
non-owner `p6-007-intruder@example.test`:

| Fixture | Shape |
|---|---|
| `plain` | machine source only + completed ZH translation |
| `edited` | edited active revision (v2) + ZH translation |
| `identical` | machine-materialized initial revision + ZH translation |
| `none` | edited active revision, no translation |
| `structural` | structurally changed revision (`new:*` identities) + ZH translation |

## 2. Application server

```powershell
$env:DB_DATABASE = "database/p6-007-verification.sqlite"
php -d variables_order=EGPCS -S 127.0.0.1:8125 -t public verification/p4-003-server-router.php
```

## 3. Run the browser suite

With the server running:

```powershell
node_modules/.bin/playwright.cmd test -c verification/playwright.p6-007.config.js
```

Results are written to the tracked file
`verification/p6-007/p6-007-browser-results.json`.

## 4. Required coverage (DC-01)

- comparison toggle/view switching the transcript region without navigating away;
- machine source vs active revision;
- source/revision vs persisted translation where available;
- no-translation state;
- authorization/isolation (non-owner denied);
- no mutation from comparison actions (persisted state unchanged).

## 5. Alignment note

Persisted Phase 5 `translation_segments.segment_index` aligns to the **machine**
`transcription_segments.segment_index`, not to revision segment identity. The
editor therefore labels a translation as belonging to the machine source when
the active revision has been edited, and presents alignment as unavailable for
structurally changed revisions rather than remapping by index.
