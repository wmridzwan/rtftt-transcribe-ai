# Phase 7 — Production Hardening — Adopted Scope Contract

Date adopted: 2026-09-26
Status: **ADOPTED — NOT AUTHORIZED FOR IMPLEMENTATION**
Authority: HPO scope adoption (`DECISION-PHASE7-SCOPE-ADOPTION-001`).
Binding inputs: `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026;
`reviews/PHASE7-ENTRY-REVIEW.md` §G (proposal baseline, adopted with D7
resolutions applied); `PHASE7-PLANNING.md` (superseded as the scope
authority; preserved as history).
Supersedes: `PHASE7-PLANNING.md` "PLANNING ONLY" status as the scope
authority. It does not supersede any ADR or frozen Phase 3/4/5/6 contract.

This contract defines what Phase 7 is. It authorizes no task, no batch, no
phase execution, and no code, schema, infrastructure, or deployment change.
No P7 task may move to IN_PROGRESS under this contract alone; Wave 1
contracts + per-task READY promotion + a separate explicit HPO execution
authorization are still required.

## A. Objective

Harden the Phase 1–6 system for production launch — durable data storage,
reliable background processing, secure and validated upload handling,
deployment/rollback safety, retention/backup posture, and
browser-support/performance validation — culminating in a single
Production Readiness Gate (P7-012). Phase 7 is hardening of the completed
system, not feature expansion.

## B. Adopted production posture (binding D7 resolutions)

| Decision | Adopted answer | Effect on scope |
|---|---|---|
| D7-01 | Self-hosted PostgreSQL (B) | P7-002 defines and verifies the SQLite/development → PostgreSQL migration; P6 invariants survive; backup tooling per D7-07 |
| D7-02 | Redis + systemd/supervisord, no Horizon (A) | P7-003 builds supervision, recovery, dead-letter, failed-job visibility on Redis; `sync` prohibited for transcription/translation queues; TD-003/TD-004 dispositions apply |
| D7-03 | Local private storage (A) | P7-004 hardens local storage + streaming (capacity, durability, permissions, topology validated); TD-011 direct tests against the local backend; object storage deferred, not rejected — migration not in scope |
| D7-04 | Self-hosted ClamAV (A) | P7-006 integrates scanning (pass/fail/timeout/unavailable/quarantine/recovery defined); signature updates + service health in ops contract; no third-party cloud scanning of user media |
| D7-05 | Chromium-only (A) | P7-010 aligns to Chromium-only; Firefox/Safari best-effort unless newly promoted; no non-Chromium support claims |
| D7-06 | Modified A — 30-day retention auto-purge | P7-011 implements the lifecycle contract (clock start, eligibility, audit records, failed/incomplete handling, retries, user-deletion interaction, P6 invariants, export behavior, idempotency/recovery, user disclosure); no orphaned revision records |
| D7-07 | Daily backups + drill, no strict RPO/RTO (A) | P7-007 delivers PostgreSQL-matched backup mechanism + documented procedures + at least one executed restore drill; backup without restore evidence fails the gate |
| D7-08 | Single-admin low-concurrency (A) | P7-009 delivers a concrete measurable capacity envelope for this model (primary workflow + worker/job/storage/database limits); no multi-tenant proof required; scale-up needs a fresh decision |

## C. In-scope capabilities (task decomposition, candidate until contracted)

P7-001 (production configuration/env validation); P7-002 (data-store
finalization + migration); P7-003 (queue/worker supervision + recovery);
P7-004 (storage strategy + streaming hardening); P7-005 (observability —
DONE, foundation only); P7-006 (security hardening baseline — upload
scanning, config hardening); P7-007 (backup/restore + DR); P7-008
(deployment/migration-safety/rollback tooling); P7-009
(performance/load/large-file validation); P7-010 (browser support matrix +
flake elimination); P7-011 (retention/derived-artifact/orphan cleanup);
P7-012 (terminal production-readiness gate, FINAL_GATE_ONLY).

Task IDs other than P7-005 are candidate decomposition until each has an
authored contract and an explicit HPO READY promotion. Wave plan:
`reviews/PHASE7-ENTRY-REVIEW.md` §H (proposal; waves/batches each need
explicit HPO authorization).

## D. Explicit non-scope

Tenancy/multi-actor authorization redesign (reserved for DC-02); distributed
tracing/external metrics backends beyond P7-005's structured logging
(explicitly deferred); any Phase 6 editing/domain feature work (Phase 6 is
closed); any UX/product-feature work; object-storage migration (deferred
per D7-03, not rejected); Horizon (excluded per D7-02); multi-tenant scale
proof (excluded per D7-08); third-party cloud malware scanning (excluded
per D7-04).

## E. Dependencies on previous phases

Phase 6 CLOSED (satisfied): P7-004/P7-011 must account for
revision/translation artifacts; P7-002's migration must reconcile Phase 5/6
schema state. P7-005's observability foundation (`OBSERVABILITY.md`,
correlation-field naming) is the logging/diagnostics base for P7-003/P7-008
runbooks.

## F. Cross-cutting implications

- Data-model: D7-01/D7-02/D7-03 outcomes may require schema or storage-path
  migrations; P6 revision-history model must remain intact.
- Migration: P7-002 (data store) requires a data-migration task with
  rollback plan, owned jointly with P7-008. P7-004 requires no migration
  under D7-03=A.
- User-facing: D7-05 (browser matrix) and D7-06 (30-day retention) need
  docs/support-copy updates, including user-facing disclosure of the
  retention policy.
- Security/privacy: D7-04 (ClamAV) and D7-06 (retention) are the most
  privacy/security-sensitive items and are HPO-gated at the production
  gate.
- Performance: D7-08 sets the target envelope P7-009 validates against.
- Deployment/operations: P7-008 + P7-003 define the operational runbook.
- Regression-sensitive: any P7-002 migration must be validated against the
  full Phase 6 editing/revision/export suite to avoid silently breaking
  revision-aware export.

## G. What adoption changes and does not change

- Changes: the scope authority moves from `PHASE7-PLANNING.md` (planning
  draft) to this contract; entry-gate criterion 5 is satisfied.
- Does not change: no task exists, is READY, or is authorized; no
  implementation, migration, or deployment act is permitted; entry-gate
  criteria 7 (Wave 1 contracts + READY) and 9 (execution authorization)
  remain outstanding and need separate HPO acts.

## H. Final state note

Adoption advances Phase 7 toward eligibility. It does not state
`PHASE 7 ELIGIBLE` or `PHASE 7 AUTHORIZED FOR EXECUTION`.
