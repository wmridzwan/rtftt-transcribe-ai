# Security Hardening Baseline (P7-006)

Binding posture: D7-04 self-hosted ClamAV; D7-08 single-admin;
D7-05 Chromium-only; DC-02 tenancy redesign explicitly out of scope.

## 1. HTTP hardening

Global `SecurityHeaders` middleware: `X-Content-Type-Options: nosniff`,
`X-Frame-Options: DENY`, `Referrer-Policy:
strict-origin-when-cross-origin`, restrictive `Permissions-Policy`,
HSTS on secure responses. CSP default-deny (`CspPolicy::directives`):
`script/style-src 'self' 'unsafe-inline'` is justified — Alpine.js,
Flux/Livewire bootstrapping, and the upload XHR are inline by
framework design, and no external script/style source is loaded
anywhere. `frame-ancestors 'none'`, `object-src 'none'`,
`form-action/base-uri 'self'`, `media/connect-src 'self'` (plus
dev-only `ws:`/`wss:` when debug is on, for Vite HMR).

Rollout: `CSP_MODE=report-only` collects at `/csp-report` (public,
throttled, CSRF-exempt, body-capped, metadata-only logging);
`CSP_MODE=enforce` blocks. Transition is a config flag with
documented rollback.

## 2. Rate limiting + abuse limits (TD-002 pairing)

Named limiters (config `security.throttles`): `login` 5/min per
email+IP (Fortify semantics kept), `upload-initiate` 30/min per user,
`csp-report` 60/min per IP. `UploadAbuseGuard`: 60 uploads/hour/user
and 10 GiB/24h/user, enforced before ingestion (no partial state);
excess fails closed with HTTP 429.

## 3. ClamAV scanning (D7-04)

Uploads scan staged content via daemon socket (preferred) or TCP
loopback, streamed in 64 KiB chunks — full objects never load into
PHP memory. Verdicts: clean → persisted per media (verdict, engine,
signature date, timestamp); infected → moved to `quarantine/<sha>`
+ audit record + rejection without engine internals; unavailable /
timeout / error → fail closed (hold|reject per
`CLAMAV_UNAVAILABLE_MODE`, retryable message). Non-loopback TCP is
refused without `CLAMAV_ALLOW_NON_LOOPBACK` (never third parties).
Disabled by default: non-production skips with a log line; production
treats disabled as unavailable (fail closed).

Signatures: `clamav:health` probes reachability + freshness (max age
`CLAMAV_MAX_SIGNATURE_AGE_HOURS`, default 48h); stale holds scanning.
Scheduled daily; missed updates alert via this runbook's triage.
Signature updates themselves (freshclam or equivalent) are host
operations documented in `docs/DEPLOYMENT-RUNBOOK.md` §12.

EICAR-standard test string only for infected-path tests; never real
malware; quarantine artifacts cleaned by the test run.

## 4. Dependency audit

`composer audit` + `npm audit` outputs retained below with triage
(fix / upgrade / HPO-accepted risk with expiry). CI (`tests.yml`,
Dependency audit step) fails on new advisories.

## 5. Secrets hygiene + audit log

No secret in repo/logs/errors/evidence (tested); production errors
render without traces, secrets, or paths. `SecurityAuditLog` writes
auth failures, denials (401/403/419/429 via the headers middleware),
scan verdicts, throttle/abuse hits, guard refusals, and CSP
violations to the structured channel with correlation ids — metadata
only. Audit retention follows application log retention; D7-06 purge
interaction owned by P7-011.

## 6. Triage

| Signal | First action |
|---|---|
| `clamav:health` red | Daemon status → socket perms → signature age → freshclam run → re-probe |
| Uploads 503 "scanner unavailable" | `clamav:health`; daemon restart; fail-closed holds until green |
| Infected quarantine | Inspect `quarantine/<sha>` offline; notify uploader without internals; rotate nothing automatically |
| 429 spike on uploads | Limiter/throttle review; abuse guard counters; not an ingestion bug until proven |
| CSP violations in enforce | Check report log; allowlist delta review (never wildcard); rollback to report-only per deploy |
