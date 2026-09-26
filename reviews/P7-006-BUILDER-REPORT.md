# P7-006 Builder Report — Security Hardening Baseline

Task: `tasks/P7-006-security-hardening-baseline.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Files changed (new unless noted)

- `config/security.php` (new) — headers, `csp_mode` (default enforce), throttles, abuse caps (60/hour, 10 GiB/day), ClamAV settings (socket-first, loopback-only, 60s timeout, 48h signature age, reject mode, disabled default).
- `app/Http/Middleware/SecurityHeaders.php` (new, `App\Http\Middleware`) — baseline headers, HSTS on secure/forced, CSP enforce/report-only, 401/403/419/429 denial audit with correlation.
- `app/Security/CspPolicy.php` (new) — default-deny allowlist; `unsafe-eval` justified by report-only EvalError evidence (Alpine/Livewire requirement); no external sources; dev-only ws allowance.
- `app/Security/SecurityAuditLog.php` (new) — structured-channel metadata-only logging (auth failures with domain shape only; denials; scan verdicts; throttle/abuse hits; guard refusals; CSP violations).
- `app/Security/UploadAbuseGuard.php` (new) — cache counters, 429 fail-closed before ingestion (no partial state).
- `app/Security/ClamavScanner.php` (new) — socket/TCP-loopback daemon, INSTREAM 64 KiB streaming (never full load), six verdicts, endpoint refusal for non-loopback, injectable connector, VERSION/signature parsing, `EICAR` constant.
- `app/Http/Controllers/CspReportController.php` (new) — public throttled CSRF-exempt 204/413/422 report sink, body-capped, metadata-only.
- `app/Console/Commands/ClamavHealth.php` (new) — `clamav:health` reachability + freshness probe; exit 0/1; scheduled daily.
- `database/migrations/2026_09_26_121022_add_scan_verdict_to_media_files_table.php` (new) — nullable scan_verdict/engine/signature_date/scanned_at; appended to `deploy/migrations-inventory.json` by owning task (P7-008 test semantics honored).
- `app/Actions/MediaIngestionService.php` (edit) — `gateMalwareScan()` between checksum and promotion: skipped (disabled non-prod), clean → verdict columns on row, infected → quarantine move + audit + rejection (no row), unavailable/timeout/error → 503 fail-closed; compensation-safe (quarantine survives cleanup).
- `app/Models/MediaFile.php` (edit) — fillable + `scanned_at` cast + docblock for scan columns.
- `app/Http/Controllers/MediaUploadController.php` (edit) — abuse-guard check pre-ingest; HttpException rethrow preserving 429/503 statuses.
- `routes/web.php` (edit) — `throttle:upload-initiate` on upload store; public throttled `/csp-report`.
- `bootstrap/app.php` (edit) — global `SecurityHeaders` append; `csp-report` CSRF-exempt.
- `app/Providers/AppServiceProvider.php` (edit) — config-driven `login`/`upload-initiate`/`csp-report` limiters (request-time reads); `Failed`-login audit listener (domain shape only).
- `app/Providers/FortifyServiceProvider.php` (edit) — removed hardcoded `login` limiter (single owner: AppServiceProvider; default semantics unchanged at 5/min); kept `two-factor`.
- `app/Console/Commands/ObservabilityDiagnostics.php` (edit, additive) — security section (CSP mode, limiter presence, ClamAV state/endpoint).
- `.env.example` (edit, append-only) — ClamAV block; no P7-003 key touched.
- `docs/SECURITY-BASELINE.md` (new) — full baseline + triage; `docs/DEPENDENCY-AUDIT.md` (new) — composer audit clean + npm audit 0 vulns + triage table; runbook §12 reference append (P7-008 owns file).
- `.github/workflows/tests.yml` (edit) — Dependency audit step (`composer audit && npm audit --omit=dev`) before CI checks.
- Tests: `tests/Feature/Security/` 6 files, 34 tests — scanner matrix incl. genuine 1s timeout (8), headers/CSP/denial audit incl. 403+429 paths (7), throttles + abuse boundaries + no-partial-state (5), ingest scan integration incl. quarantine + 503 + skipped (4), csp-report/health/error-rendering/diagnostics (7), CSP allowlist pin (3).

## Notable implementation finding (reported, not hidden)

Report-only browser run proved the workspace needs `unsafe-eval` (Alpine `new Function` EvalErrors); the allowlist was widened exactly there with retained evidence, then enforced green. Pre-existing gap closed as a side effect: Fortify logins were effectively unthrottled (config disabled the internal check with no middleware replacement); they are now throttled at the documented 5/min.

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 headers + CSP, transition evidenced, workspace green | PASS | Header/CSP tests; report-only + enforce Chromium runs 1/1 each, zero console/page errors, seek 0.5s; `p7-006-csp-{report-only,enforce}.json` retained |
| AC2 rate/abuse limits, exact boundaries, no partial state | PASS | Throttle 429 tests (upload + login + csp-report); abuse count/byte boundary tests; controller no-partial-state test |
| AC3 six scan behaviors, fail-closed, no silent clean | PASS | Double-driven matrix (clean/infected/unavailable/timeout/error/refused) + ingest integration (quarantine, 503, skipped) |
| AC4 real-daemon proof / target-only | **TARGET-ONLY (honest)** | No clamd on this Windows box (no binaries, 3310 closed); `clamav:health` correctly reports unreachable/stale with exit 1; matrix + procedure substitute per contract |
| AC5 dependency audit + CI | PASS | Both audits clean, retained; CI step added |
| AC6 error rendering + audit log | PASS | APP_DEBUG=false leak test; denial/verdict/throttle/guard audit tests |
| AC7 no third-party egress | PASS | Default loopback endpoint; refusal test; no secret in repo/logs/evidence |
| AC8 standard gate | PASS | Full suite 1006/1005+1 pre-existing skip; Pint clean; PHPStan 0; Wave 1 files only additively touched (diff audit §below) |

## Diff audit (Wave 1 / shared files)

Additive-only: AppServiceProvider (limiter/listener methods + 1 posture line from P7-001), DeploymentVerify (2 sub-checks from P7-001), ObservabilityDiagnostics (2 sections), ProductionConfigGuard (2 audit lines), routes/console (2 schedules), .env.example (2 appended blocks), runbook (§11 + §12 + §13 appends), migrations-inventory (+1 pin). One behavioral consolidation: Fortify login limiter single-owned (default 5/min preserved). One P6-owned test adjusted: rollback `--step` 5→6 (newest migration is P7-006's) + scan_verdict down() assertion — reported, not hidden.

## Test / verification matrix

- New P7-006 suites: 34/34. Full suite shared above. Browser: Playwright real Chromium, `playwright.p7-006.config.js` (chromium-only, workers 1, retries 0), dedicated seeded DB (`database/p7-006.sqlite` + `verification/p7-006*` retained, untracked, per P7-010 precedent).
- `.env` was temporarily overridden for the browser run and restored byte-identical (verified by diff); detached serve processes all stopped (port verified down).

## TD mapping

- TD-002 abuse-limit side implemented; G-01 proof NOT claimed. Nothing marked closed.

## Known limitations (for reviewer)

1. Real-daemon ClamAV proof is target-only (see AC4).
2. Production-scale scan latency (500 MiB streaming time) unmeasured here; timeout reconciled with job budget by config, not by load run (P7-009 concern).

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed.
