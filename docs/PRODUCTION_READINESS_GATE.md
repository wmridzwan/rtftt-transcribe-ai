# Production Readiness Gate — Inventory (P7-012 prerequisites)

Date: 2026-09-24
Authority: `AGENTS.md`; `PHASE7-PLANNING.md`; `PHASE5-7-RISK-REGISTER.md`;
`docs/TECHNICAL_DEBT_REGISTER.md`.
Status: PLANNING ONLY — this inventory defines gate conditions; it does not
authorize Phase 7 implementation, create tasks, or promote any task to READY.
Phase 7 remains NOT GENERALLY AUTHORIZED; P7-012 remains FINAL_GATE_ONLY and
cannot run before Phase 6 is CLOSED.

## Entry criteria (before any gate run)

1. Phase 6 CLOSED by HPO (requires P6-005 review → VERIFIED → DONE, then
   P6-008 contract/implementation/closure, then P6-009 final integration
   verification DONE).
2. HPO resolves D7-01..D7-08 (data store, queue/worker, storage, security,
   browser matrix, retention, backup/DR, capacity targets) and records ADR(s).
3. HPO authorizes Phase 7 execution (task contracts first, then READY waves).
4. All `YES` production blockers in `docs/TECHNICAL_DEBT_REGISTER.md` resolved;
   all `REQUIRES HPO DECISION` items decided.

## Gate conditions

| # | Condition | Evidence required | Maps to |
|---|---|---|---|
| G-01 | Deployed environment meets the 500 MiB receiving contract | Retained artifact: real 500 MiB multipart upload through the production HTTP stack (PHP/web-server/proxy limits, timeouts, temp + durable capacity) | TD-002; P7-001/P7-009 |
| G-02 | Production data store decided and migration-safe | D7-01 decision; migration + rollback evidence; concurrency/load evidence for the chosen store | P7-002; R-08 |
| G-03 | Production queue/worker topology supervised and recoverable | Redis certified (version/config/auth); supervisor + health checks; dead-letter policy; failed-job visibility; stale recovery proven under the production queue; `sync` prohibited for transcription/translation queues | TD-003; P7-003; R-10 |
| G-04 | Redis authentication posture decided per environment | Auth/network/TLS posture recorded; no passwordless Redis outside loopback isolation | TD-004; P7-001/P7-003 |
| G-05 | Storage backend decided; range streaming/seekability proven | Range GETs without full-object PHP load; zero-byte and range-edge coverage; bounded-stream regression tests | P7-004; R-09 |
| G-06 | Security hardening baseline | Headers, CSP, rate limiting, upload abuse limits, malware-scan decision, dependency audit, secrets handling, audit logs | P7-006; R-14 |
| G-07 | Observability beyond the P7-005 foundation | Metrics/tracing backends if adopted; alerting; production dashboards/runbooks (P7-005 DONE covers structured logging, correlation, diagnostics) | P7-005 (DONE) |
| G-08 | Backup/restore and rollback proven | Restore drill with RPO/RTO evidence; release/rollback procedure evidence | P7-007/P7-008; R-16 |
| G-09 | Retention/deletion/derived-artifact policy executed | D7-06 decision; orphaned-media and derived-artifact cleanup; explicit Option D outcome for staging cleanup | TD-007; P7-011; R-15 |
| G-10 | Performance/capacity targets met | 500 MiB upload, FFprobe, transcription RTF, translation latency/throughput, concurrency, streaming, render, export under target load | P7-009; R-12/R-13 |
| G-11 | Browser support matrix green with deterministic verification | Supported matrix executed; V4-08/V4-09 flake eliminated or HPO-accepted with documented posture; `showRenameModal` resolved | TD-005; P7-010; R-11 |
| G-12 | Full real-model E2E verification passed (dedicated requirement, §Full chain) | Retained end-to-end evidence with zero doubles | TD-001; P7-012 |
| G-13 | Legal/privacy validation complete | Required legal/privacy sign-off recorded | P7-012 prerequisites |

## Full chain real-model E2E (mandatory, no-fake rule)

One continuous run must cover, at minimum:

real media upload (HTTP endpoint)
→ ingestion (staging → validation → promotion → persistence)
→ real transcription engine (live queue → authenticated worker → real FFmpeg → real faster-whisper canonical model)
→ persisted transcript + segments
→ translation where applicable (live queue → authenticated worker → real canonical translation model → atomic persistence)
→ Transcript Workspace (real browser: playback, navigation, search, comparison)
→ final delivery/export path (TXT/SRT/VTT/DOCX or applicable targets)

Rules:

- No fake transcription provider, worker double, or seeded transcript may
  satisfy any link of this chain. Seeded fixtures may be used for regression
  suites but never as substitutes in the gate run.
- Component gates already passed (P3-008 transcription; P5-008 translation with
  browser-to-real-model proof) are retained as history and do not need
  re-litigation; the gate run proves the unbroken chain, not the components.
- Attempt-token fencing, stale recovery, retry/recovery, and queue-timeout
  behavior may be proven by the contract-level and two-process concurrency
  suites (per the P5-008 separation precedent), not by destructive real-model
  scenarios — but the successful path must be real end to end.
- All gate evidence (environment, versions, model revisions, queue payloads,
  browser results, exports) must be retained in `verification/` like prior
  phase gates.

## Explicit non-actions

- No Phase 7 task is created, authorized, or promoted by this inventory.
- No owner decision (D7-*) is made here.
- No production deployment or configuration change is authorized.
