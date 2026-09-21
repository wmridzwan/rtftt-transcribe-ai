# Phase 7 — Production Hardening — Planning Package

Date: 2026-09-20
Status: PLANNING ONLY — NOT AUTHORIZED FOR IMPLEMENTATION
Authority: `RTFTT-MASTER-ROADMAP.md` (Phase 7 = Production Hardening;
reserved; "Production Gate" section); `plan.md`; `CURRENT_STATE.md`;
`PROJECT_CONTEXT.md` §28 "Production Hardening"; `.ai/guidelines/orchestration-policy.md`
Supersedes: none.
Scope: Phase 7 = production hardening of the completed P1–P6 system. It is NOT
feature expansion.

This package is a planning artifact, NOT an authorization. Phase 7
implementation remains NOT AUTHORIZED until Phase 6 closure and a separate HPO
authorization, except where the HPO explicitly authorizes an earlier hardening
item. No task file is created; no task may be promoted to READY; no code,
schema, infrastructure, or deployment change is authorized.

## A. Repository State Confirmed

- Phases 1–4 CLOSED. Phase 5/6 NOT AUTHORIZED (planning only).
- Runtime assumptions today: SQLite (dev), `database` queue by default in
  `.env.example` (`QUEUE_CONNECTION=database`), Redis configured but not
  Horizon-managed, local private storage disk, FFmpeg + faster-whisper
  self-hosted worker, no production deployment/monitoring/backup.
- `CURRENT_STATE.md` "Future Gates": first-production-use deployment, rollback,
  backup/restore, monitoring, failed-job visibility, retention/deletion,
  derived-artifact deletion, log/privacy, legal/privacy validation,
  actor-vs-owner semantics.
- `RTFTT-MASTER-ROADMAP.md` "Production Gate": deployment procedure, migration
  safety, rollback, backup and restore verification, monitoring and failed-job
  visibility, retention and deletion, derived-artifact deletion, log/privacy
  behavior, required legal/privacy validation.

## B. Objective

Make the Phase 1–6 system deployable, operable, observable, secure, recoverable,
and capacity-validated for real production use, without introducing new product
features.

## C. Scope (proposed)

- production data store decision + migration strategy;
- Redis production certification; worker supervision; queue recovery;
  dead-letter/retry semantics;
- storage strategy (local private vs S3-compatible vs hybrid) with range
  streaming/seekability implications;
- structured logging, metrics, tracing, health checks, job observability;
- security hardening (headers, CSP, rate limiting, upload abuse limits,
  malware-scanning decision, dependency audit, secrets handling, audit logs);
- deployment process, env validation, migration safety, release/rollback;
- backup/restore and disaster recovery;
- retention/deletion/derived-artifact cleanup and orphaned-media cleanup;
- performance/capacity validation (500 MiB upload, FFprobe, transcription,
  translation, concurrency, streaming, transcript rendering, export);
- large-file and production browser support matrix;
- a final production-readiness gate.

## D. Explicit Phase 7 Non-Scope

- new product features or UX;
- live/realtime capabilities, collaboration, SaaS/tenancy;
- provider-abstraction redesign beyond a documented second-provider seam;
- changing frozen Phase 3/4/5/6 accepted contracts without a specific,
  justified, ADR-backed contradiction.

## E. Deferred Debt Inventory → Phase 7 Mapping

Debt source is preserved; nothing is silently dropped or re-attributed.

| Debt item | Origin | Phase 7 disposition | Notes |
|---|---|---|---|
| P4-002 seekable/local-stream assumption | `reviews/PHASE4-final-closure.md` §H | P7 storage/streaming task | Re-validate against chosen storage backend |
| Zero-byte media `Content-Length`/body edge | P4-002 LOW | P7 storage/streaming task | Latent, not demonstrated reachable |
| Bounded-stream regression assertion coverage | P4-002 LOW | P7 test-hardening | Add direct bounded-stream assertions |
| Range-edge branch coverage | P4-002 LOW | P7 test-hardening | Code-reviewed; add direct tests |
| Intermittent Playwright V4-08 timing flake | P4-003 LOW-2 / P4-006 LOW-1 | P7 browser determinism task | Eliminate flakiness before production |
| Pre-existing `showRenameModal is not defined` | PHASE4-final-closure §H INFO | P7 browser/JS fix | Predates Phase 3/4; fix + regression test |
| Phase 3 stale-attempt hardening residual | P3 non-blocking debt | P7 queue recovery task | Validate recovery under production queue |
| Redis version production certification | ADR-017 / Phase 3 (no live Redis in Batch 2) | P7 queue task | Live Redis gate already passed in P3-008; certify production version/config |
| Actor-vs-owner semantics | `CURRENT_STATE.md` Future Gates | P7 gate prerequisite (may become an ADR) | Required before admin-on-behalf-of/tenancy |
| SQLite concurrency observations | P2-004A/A1 BLOCKED; ADR-013 Option D | P7 data-store task | Drives data-store decision |
| Automated abandoned-staging cleanup (Option D) | P2/ADR-013 | P7 cleanup task | Requires separate authorization; not lifted by planning |
| P2-004A/P2-004A1 historical BLOCKED state | Phase 2 | P7 cleanup task or explicit deferral | Do not lose historical ownership |
| DEFERRED DEPLOYMENT REQUIREMENT (real 500 MiB multipart through prod stack) | `CURRENT_STATE.md` Phase 2 debt | P7 performance/deployment task | Must be proven in the deployed stack |
| DEFERRED UX VERIFICATION (upload progress browser evidence) | Phase 2 debt | P7 browser evidence task | Real browser runtime evidence |
| P4-004 LOW-1 whitespace-only query UI inconsistency | P4-004 review | P7 UX polish | Non-blocking |
| P4-004 LOW-2 unconsumed `WorkspaceAvailability`/`WorkspaceState` primitives | P4-004 review | P7 cleanup or consumed by P6 | Track, do not lose |
| P4-004 corrective INFO-1 dead-code `?? this.$root` | P4-004 corrective review | P7 cleanup | Non-blocking |
| P4-005 no-speech DOCX test-strength limitation | P4-005 LOW-1 | P7 test-hardening | Strengthen test |
| P4-001 LOW-1 redundant existence query | P4-001 review | P7 cleanup (optional) | Non-blocking |
| P4-001 LOW-2 missing exception-branch unit test | P4-001 review | P7 test-hardening | Optional |

## F. Detailed Contract (proposed)

### 6.1 Deferred debt inventory
See §E. All items retain their origin and are not re-attributed.

### 6.2 Production data store decision (D7-01)

Options:

- **Retain SQLite** — zero migration cost; adequate for single-admin/small
  concurrent load; concurrency/write-lock limitations observed in Phase 2
  remain.
- **Migrate to PostgreSQL (or MySQL)** — robust concurrency, mature operations,
  row-level locking; requires migration, deployment, and data migration
  evidence.
- **Support multiple DBs** — highest flexibility; highest testing/ops cost and
  risk of dialect divergence.

Recommendation: decide based on the production concurrency target. For a
single-admin first deployment, retaining SQLite with a documented concurrency
ceiling is acceptable; for multi-user concurrency, PostgreSQL before first
production use. Owner decision required. No migration is implemented here.

### 6.3 Queue / worker production strategy (D7-02)

Evaluate Redis; Horizon; process supervisor; queue monitoring; retry/dead-letter
semantics; worker health. Horizon was excluded from Phase 3 (ADR-017) and is not
automatically required now. Candidate: Redis + a process supervisor
(systemd/supervisord) with health checks and failed-job visibility; Horizon as
an optional later addition only if queue observability/autoscaling justifies it.

### 6.4 Storage strategy (D7-03)

Options: local private disk; S3-compatible object storage; hybrid abstraction.
Consider range streaming and seekability: the P4-002 range-stream endpoint is
built on a seekable local abstraction; S3-compatible storage must provide range
GETs without loading the full object into PHP memory, and derived/prepared audio
retention must be re-validated.

### 6.5 Performance / capacity

Define realistic validation, separating **product limits** (500 MiB upload
boundary, etc.) from **benchmark targets**:

- 500 MiB upload through the deployed HTTP stack (Phase 2 deferred requirement);
- FFprobe on large media;
- transcription RTF on target hardware;
- translation latency/throughput;
- concurrent jobs and worker saturation;
- media streaming under concurrent load;
- browser transcript rendering for long transcripts;
- export generation.

### 6.6 Production readiness gate

At minimum: clean deployment; migrations; queues; worker startup; upload;
transcription; translation; playback; exports; browser flows; security;
backup/restore; failure recovery; observability; load/performance; rollback.

## G. Recommended Phase 7 Task Decomposition (candidate — NOT created)

| Task | Title | Objective | Depends on |
|---|---|---|---|
| P7-001 | Production Configuration + Env Validation | Env validation, secrets handling, config hardening, fail-fast boot | D7-* resolved; Phase 6 CLOSED |
| P7-002 | Production Data Store Decision + Migration | Decide/implement SQLite vs PostgreSQL/MySQL; migration safety evidence | P7-001; D7-01 |
| P7-003 | Queue / Worker Supervision + Recovery | Redis certification, supervisor, recovery, dead-letter, failed-job visibility | P7-001; D7-02 |
| P7-004 | Storage Strategy + Streaming Hardening | Storage backend decision, range/stream hardening, zero-byte/range-edge, bounded-stream tests | P7-001; D7-03 |
| P7-005 | Observability | Structured logging, metrics, tracing, health checks, job observability | P7-001 |
| P7-006 | Security Hardening | Headers, CSP, rate limiting, upload abuse, malware-scan decision, dependency audit, audit logs | P7-001; D7-04 |
| P7-007 | Backup / Restore + Disaster Recovery | Backup/restore verification, DR runbook, RPO/RTO | P7-002/003/004; D7-07 |
| P7-008 | Deployment, Migration Safety, Rollback | Deploy procedure, migration safety, release/rollback strategy | P7-002/003/004 |
| P7-009 | Performance / Load / Large-File Validation | 500 MiB upload in prod stack, concurrency, streaming, render, export, translation | P7-002/003/004; D7-08 |
| P7-010 | Browser Support Matrix + Flake Elimination | Supported browser matrix; fix V4-08 flake; fix `showRenameModal`; Phase 2 UX evidence | P7-001; D7-05 |
| P7-011 | Retention, Derived-Artifact + Orphan Cleanup | Retention/deletion, derived artifacts, orphan media, Option D staging cleanup decision | P7-004; D7-06 |
| P7-012 | Production Readiness Gate | Final end-to-end production-readiness verification | All P7 tasks DONE |

## H. Dependency / Authorization Order

```text
Phase 6 CLOSED
→ HPO resolves D7-01..D7-08 (+ actor-vs-owner if needed) and records ADR(s)
→ HPO Phase 7 task/batch authorization (may authorize individual items early)
→ P7 contracts authored (BACKLOG; non-executable)
→ HPO promotes wave to READY
→ READY → IN_PROGRESS → REVIEW → independent review
→ VERIFIED → HPO closure → DONE
```

## I. Migration / Compatibility

- Data-store migration (if chosen) requires its own migration-safety and
  rollback evidence and must not modify historical migrations.
- Phase 3/4/5/6 accepted contracts remain authoritative.
- Option D (ADR-013) remains in force unless the HPO explicitly decides
  otherwise; it is not lifted by this package.

## J. Risks (summary; full register in `PHASE5-7-RISK-REGISTER.md`)

| Risk | Mitigation |
|---|---|
| SQLite scaling limits under production concurrency | D7-01 decision + load evidence |
| Redis/queue recovery gaps | Live Redis certification + supervisor + dead-letter handling |
| Storage/streaming seekability regression | Range tests against chosen backend; zero-byte/range-edge tests |
| Missing backup/restore or rollback | DR task with restore drill before gate |
| Security exposures (CSP, rate limiting, upload abuse) | Security hardening task + dependency audit |
| Large-file/runtime capacity unknown | Load/large-file task; product limits vs benchmark targets |
| Browser flake erodes verification trust | P7-010 determinism + supported matrix |
| Orphaned/derived artifacts accumulate | P7-011 retention/cleanup |
| Undecided retention/legal/privacy | HPO decision (D7-06) before gate |

## K. Explicit Non-Actions

- Phase 7 implementation is NOT authorized; it requires Phase 6 closure and a
  separate HPO authorization.
- No task is promoted to READY; no batch is authorized.
- No deployment, infrastructure, application, schema, test, or worker change is
  authorized.
- No owner decision is made on behalf of the HPO; §F are OPEN questions.
- Option D (ADR-013) is not lifted.