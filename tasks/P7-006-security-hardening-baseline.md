# P7-006 — Security Hardening Baseline

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-006-CLOSURE-001`) on the independent VERIFIED verdict
below. CSP `unsafe-eval` narrative justification HPO-accepted as
sufficient (F4 preserved as evidence gap, non-blocking); `guardRefusal`
call sites confirmed present at `ProductionConfigGuard.php:95` and
`ProductionPostureChecks.php:74` (F5 reconciled with evidence, no
action). AC4 real-daemon proof remains target-only.

VERIFIED — independent review (`reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md`
§2) 2026-09-26: AC1–AC3, AC5–AC8 independently reproduced PASS (no
fail-open behavior found on any security-critical path); AC4 recorded
BLOCKED-ENVIRONMENT (target-only, explicitly contract-permitted, no
clamd on this box). Non-blocking findings: F4 (CSP `unsafe-eval`
justification rests on narrative only, no "before" artifact retained)
and F5 (`guardRefusal()` has no call site) — neither BLOCKER/HIGH. Not
DONE; DONE requires HPO closure.

History preserved: BACKLOG — CONTRACT_AUTHORED → reconfirmed → READY (HPO
`DECISION-PHASE7-WAVE2-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-006-BUILDER-REPORT.md`;
real-daemon ClamAV proof recorded target-only with substitute evidence,
per contract) → VERIFIED with AC4 flagged BLOCKED-ENVIRONMENT and F4/F5
non-blocking findings (`reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md` §2,
2026-09-26) → DONE (HPO `DECISION-P7-006-CLOSURE-001`, 2026-09-26).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26, Wave 2
preparation while Wave 1 was under independent review; planning only,
no implementation) → reconfirmed against final Wave 1 DONE state
(P7-003/P7-008/P7-010 DONE; independent review confirms disjoint diffs
except additive `AppServiceProvider` registrations, no Firefox/WebKit
content, 14/14 Chromium-only configs) with NO semantic reconciliation
required (NO_WAVE1_DEPENDENCY holds; D7-04 scope and Chromium-only
boundary intact) → READY. Not authorized for implementation;
READY != EXECUTION AUTHORIZATION. No code written under this contract.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-006
- Canonical title: Security Hardening Baseline
- Phase: 7 — Production Hardening
- Proposed state: READY (HPO promotion
  `DECISION-PHASE7-WAVE2-READY-PROMOTION-001`, 2026-09-26; reconfirmed
  against final Wave 1 DONE state, no semantic reconciliation — see
  §5/§19)

## 2. Objective

Deliver the production security-hardening baseline for the adopted
single-admin posture: HTTP hardening (headers, CSP), rate limiting and
upload-abuse limits, self-hosted ClamAV malware scanning of uploads
with fully defined pass/fail/timeout/unavailable/quarantine/recovery
behavior, a dependency audit with a remediation record, secrets-hygiene
verification, and security-event audit logging — without touching
tenancy/authorization redesign (reserved for DC-02), without sending
user media to any third-party scanner, and without claiming the
upload-limit proof owned by P7-001/P7-009.

## 3. Why This Task Exists

- Uploaded media is user-supplied binary content; no malware-scanning
  step exists anywhere in the ingestion pipeline (D7-04, resolved as
  self-hosted ClamAV — implementation open).
- No production HTTP hardening baseline exists: security headers, CSP,
  rate limiting, and upload-abuse limits were never decided or applied.
- No dependency audit (composer/npm) with a retained remediation
  record exists; no security-event audit log exists.
- Production gate G-06 names exactly this baseline. It cannot close
  without this task.
- TD-002's limit increase must be paired with abuse limits; P7-001
  records the pairing, this task implements the abuse-limit side.

## 4. Binding Decisions / ADRs

- D7-04 = OPTION A (self-hosted ClamAV) —
  `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026. User media must not be
  sent to a third-party cloud scanning provider under any option. Do
  not reopen.
- D7-08 = OPTION A (single-admin) — rate/abuse limits target this
  model; no multi-tenant proof.
- D7-02/D7-05 respected (no Horizon admin surface to secure; CSP must
  permit the Chromium-verified workspace behavior P7-010 proved).
- Scope non-scope honored: tenancy/multi-actor authorization redesign
  is reserved for DC-02 and is not part of this task;
  distributed tracing/external metrics backends stay deferred beyond
  P7-005.
- `PHASE7-SCOPE-CONTRACT.md` §§B–F; `docs/PRODUCTION_READINESS_GATE.md`
  G-06.

## 5. Dependencies

- Hard prerequisites (satisfied at policy level):
  - D7-04 resolved (OPTION A); scope contract adopted; Phase 6 CLOSED.
  - P7-005 DONE — structured logging channel and correlation fields
    consumed unchanged for security-event logging.
- Wave 1 relationship: NO_WAVE1_DEPENDENCY (semantic). This task
  touches no Wave 1 file semantically and consumes no Wave 1 runtime
  behavior. File-level coordination only (see §16): `.env.example`
  additions (append-only, ClamAV keys) and `AppServiceProvider` boot
  ordering must be reconciled against the VERIFIED Wave 1 baseline at
  implementation time; implementation must not begin until Wave 1
  review closes (review isolation, not a scope dependency).
- Soft dependencies (coordinate, do not block):
  - P7-001 env-key registry — ClamAV/service key names are defined in
    §9 of this contract and registered with P7-001's mechanism when
    available; if P7-001 is not yet DONE, key names in §9 are
    authoritative and registration is a documented follow-up.
  - P7-008 runbook — scanning operations/triage documented by
    reference; P7-008 owns the runbook file.
- Downstream: P7-012 gate G-06.

## 6. In Scope

1. HTTP hardening: security headers (incl. HSTS posture appropriate to
   the deployment, `X-Content-Type-Options`, frame protections,
   referrer policy) and a content-security policy that permits the
   verified workspace (P4/P6/P7-010 Chromium behavior incl. media
   playback, Alpine/Flux inline behavior where required) and denies
   everything else by default; violations report-only first, then
   enforced, with the transition evidenced.
2. Rate limiting: throttle login/auth endpoints, upload initiation,
   and any unauthenticated-adjacent surface per the single-admin
   model; limits documented, tested, and disclosed.
3. Upload-abuse limits: per-file (aligned to the 500 MiB boundary),
   per-window count/byte caps, and oversized/rapid-fire rejection
   behavior — the abuse-limit side of the TD-002 pairing.
4. ClamAV integration: scan step in the upload/ingestion path with
   explicitly defined pass, fail/infected (quarantine-or-reject per
   §8), timeout, scanner-unavailable, and recovery behavior; signature
   freshness requirement; service-health check wired into operations
   (runbook + verify sub-check); no third-party egress of user media.
5. Dependency audit: `composer audit` + `npm audit` (or lockfile
   equivalents) executed, findings triaged with a retained remediation
   record (fix / upgrade / HPO-accepted risk with expiry).
6. Secrets hygiene: verify no secret in repo/logs/errors (extends the
   P7-001 rule to error pages, exception rendering in production with
   `APP_DEBUG=false`, and the ClamAV/socket path).
7. Security-event audit log: authentication failures, authorization
   denials, scan verdicts, rate-limit hits, and guard refusals logged
   through the P7-005 channel with correlation fields; retention of
   audit entries defined (cross-reference D7-06, owned by P7-011).

## 7. Explicit Non-Scope

- Tenancy / multi-actor authorization redesign (reserved for DC-02);
  no change to the ownership/admin isolation model.
- Penetration testing or formal security certification (not required
  by G-06; may be proposed separately, never smuggled in).
- Datastore migration (P7-002); queue internals (P7-003); deployment
  mechanics (P7-008); backup mechanism (P7-007); env certification
  (P7-001); browser matrix (P7-010); retention purge (P7-011).
- Third-party cloud malware scanning (excluded by D7-04).
- CSP changes that break the verified Chromium workspace: any
  playback/search/export regression fails this task's gate.

## 8. Architecture / Domain Contract

- Scan placement: uploads are held in a not-normally-usable state
  (quarantine staging or acceptance hold) until a pass verdict; the
  ingestion state machine gains exactly one scan gate withterminal
  infected handling (reject + quarantine record + operator notice) and
  non-terminal unavailable handling (degraded mode defined below, never
  silent skip). Scan verdicts are persisted per media (verdict, engine
  version, signature date, timestamp) for audit.
- Scanner-unavailable policy (explicit, HPO-visible): define whether
  uploads queue, reject, or proceed with a recorded risk flag. Default
  recommendation: reject-or-hold (fail closed) for a production launch;
  any risk-flag bypass requires an explicit HPO decision and is logged
  per upload.
- Middleware layering: hardening middleware composes with (never
  replaces) the existing auth/ownership stack; order documented and
  tested (abuse limits before expensive work, auth before scan where
  identity is required for audit).
- ClamAV service boundary: daemon socket/TCP connection only; no
  media bytes in logs; engine/signature versions surfaced to
  diagnostics without leaking service topology to clients.

## 9. Detailed Implementation Requirements

1. Headers + CSP: middleware/config delivering the header set;
   report-only CSP run with retained violation evidence, then enforced
   CSP; full Chromium workspace regression (playback, seek, search,
   copy, export, editing surfaces read-only paths) green under the
   enforced policy.
2. Throttles: named rate limiters with documented thresholds;
   429 behavior tested; throttle keys do not leak identity.
3. Abuse limits: enforced caps with exact-boundary tests (at/over),
   clear user-facing rejection messages, no partial-ingestion residue
   on rejection (compensation path tested).
4. ClamAV: service integration + all six behaviors tested with a
   controllable scanner double for unit/feature scope (timeout,
   unavailable, infected via EICAR-standard test string only — never a
   real malware sample) plus one real-daemon integration proof where
   the verification environment provides ClamAV; target-only steps
   flagged per P7-008 AC2 precedent.
5. Signature freshness: maximum acceptable signature age enforced with
   a health check; stale signatures hold (not skip) scanning.
6. Dependency audit artifacts retained (`audit` outputs + triage
   record); CI step added so new advisories fail loudly.
7. Production error rendering verified (`APP_DEBUG=false`): no stack
   trace, no secret, no path disclosure; security events in the audit
   log with correlation IDs.
8. Env keys for ClamAV/service tuning defined in-contract (§5 soft
   dependency) and appended to `.env.example` without touching P7-003
   queue keys.

## 10. Failure / Recovery Semantics

- Scanner failure/timeout/unavailable fails closed (hold or reject
  per §8 policy); queued uploads resume scanning automatically on
  recovery; no upload is silently marked clean.
- Infected verdict: terminal for that artifact (reject + quarantine
  record); operator procedure in the runbook; uploader notified
  without disclosing engine internals.
- Rate-limit/abuse-limit rejections are safe to retry after the
  window; no partial media/transcription state persists (or partial
  state is compensated — tested).
- CSP report-only → enforced transition is reversible per-deploy
  (config flag) with the rollback documented.

## 11. Security / Privacy Requirements

- No user media leaves the host for scanning (D7-04 hard constraint);
  assert in tests that the scan path addresses only the configured
  local daemon endpoint.
- EICAR-standard test string only for infected-path tests; never a
  real sample; test quarantine artifacts cleaned by the test run.
- Audit log contains verdicts and metadata only — never media bytes,
  never secrets, never raw request bodies.
- Dependency triage risks accepted by the HPO carry expiry/review
  dates; expired acceptances fail the audit step.

## 12. Observability / Operations Requirements

- Scan verdicts, signature age, service reachability, throttle hits,
  and CSP violations flow through the P7-005 structured channel with
  correlation fields; dashboard/runbook queries documented.
- Diagnostics command gains a security section (header presence,
  CSP mode, limiter registration, scanner reachability + signature
  age) within P7-005 field naming; no P7-005 line format altered.
- Signature-update mechanism (freshclam or equivalent) scheduled and
  monitored; missed updates alert per the runbook.

## 13. Acceptance Criteria

- AC1: Enforced header set + CSP present on authenticated and public
  responses; report-only → enforced transition evidenced; workspace
  Chromium regression green under enforcement.
- AC2: Rate/abuse limits enforced at documented thresholds with
  exact-boundary tests; rejection leaves no partial ingestion state.
- AC3: ClamAV pass/fail/timeout/unavailable/quarantine/recovery all
  defined and tested; unavailable fails closed per §8 policy; no
  silent clean-marking on any path.
- AC4: Real-daemon integration proof (or explicitly flagged
  target-only item with substitute evidence); signature-freshness
  hold proven.
- AC5: Dependency audit retained with triage; CI fails on new
  advisories; expired risk acceptances fail.
- AC6: Production error rendering leaks nothing; security audit log
  covers auth failures, denials, verdicts, throttle hits, guard
  refusals with correlation IDs.
- AC7: No third-party egress of user media on any scan path
  (test-asserted); no secret in repo/logs/evidence.
- AC8: Full suite green, Pint clean, PHPStan 0, no P6 regression, no
  Wave 1 file semantically altered (diff audit).

## 14. Test / Verification Requirements

- Feature tests: header/CSP assertions, limiter boundaries (429),
  abuse-limit boundaries + compensation, scan six-behavior matrix
  (double-driven), EICAR infected path, stale-signature hold,
  production error rendering, audit-log coverage, no-egress
  assertion, secrets-absence.
- Real-daemon proof where available; target-only flagging where not
  (P7-008 AC2 precedent); EICAR only.
- Full PHP suite + Pint + PHPStan level 7; Phase 6 suites in the run;
  one real-Chromium workspace pass under the enforced CSP (DC-01).

## 15. Technical-Debt Mapping

- TD-002 (abuse-limit side of the pairing): implemented here; G-01
  proof NOT claimed. NOT marked closed here.
- No other TD item is owned by this task. Nothing marked closed.

## 16. Risks / Regression Concerns

- CSP breaking the verified workspace is the top risk (Alpine/Flux
  inline behavior, media playback, blob/object URLs for export
  download). Mitigation: report-only evidence first, enforced only
  with a green Chromium workspace run; per-surface allowlist deltas
  reviewed line by line.
- ClamAV latency on 500 MiB uploads: scan must stream, never load
  full objects into PHP memory; timeout values reconciled with the
  P7-003 job-timeout invariant (scan time is inside the job budget).
- `.env.example`/`AppServiceProvider` shared-file collision with the
  Wave 1 baseline: implementation edits wait for Wave 1 review
  closure and reconcile against VERIFIED files (review isolation).
- Fail-closed unavailability can block all uploads during a scanner
  outage: the §8 policy + runbook recovery path + signature/update
  monitoring bound this; HPO visibility required on the policy.

## 17. Completion Evidence

- Builder report (files, AC verdicts + evidence pointers, test
  matrix incl. full-suite counts/Pint/PHPStan, TD mapping, known
  limitations incl. any target-only ClamAV items).
- Retained artifacts: CSP report-only violations, enforced-policy
  Chromium results, audit outputs + triage record, scan-behavior
  matrix results (redacted), diagnostics security section output.

## 18. Reviewer Checklist

- [ ] CSP enforced with a green Chromium workspace run; no
      over-broad allowlist (each directive justified).
- [ ] All six scan behaviors tested; no path silently marks clean;
      unavailable policy matches §8 and is HPO-visible.
- [ ] No third-party egress assertion present and passing; EICAR only,
      cleaned up.
- [ ] Abuse limits exact-boundary tested with compensation proven;
      TD-002 pairing recorded, G-01 proof not claimed.
- [ ] Dependency triage complete with expiries; production errors leak
      nothing; audit log covers the required events.
- [ ] Diff audit: no Wave 1 file semantically altered; `.env.example`
      additions append-only; full suite + Pint + PHPStan reproduced.

## 19. State Transition Rule

READY (HPO promotion `DECISION-PHASE7-WAVE2-READY-PROMOTION-001`,
2026-09-26; NO_WAVE1_DEPENDENCY reconfirmed against final Wave 1 DONE
state — no new dependency arose). READY → IN_PROGRESS only when work
begins under an explicit HPO Wave 2 execution authorization (not
granted). Shared-file coordination (`.env.example` append-only,
`AppServiceProvider` boot ordering) remains implementation-time
reconciliation against the DONE baseline. REVIEW → VERIFIED/
CHANGES_REQUESTED by the independent reviewer; VERIFIED → DONE only by
HPO closure. `READY != EXECUTION AUTHORIZATION.`
