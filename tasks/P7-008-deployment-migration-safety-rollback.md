# P7-008 — Deployment, Migration Safety, Rollback

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-008-CLOSURE-001`, under environmental exception
`DECISION-P7-008-AC2-DISPOSITION-001`) on the independent VERIFIED verdict
(`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md` §C; 8/9 ACs independently
satisfied; AC2 environmentally blocked, no defect).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26) → READY (HPO
`DECISION-PHASE7-WAVE1-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-008-BUILDER-REPORT.md`;
one environmental BLOCKED AC recorded: AC2 reboot execution — substitute
evidence provided) → VERIFIED with AC2 flagged
(`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md`§C) → DONE (HPO
`DECISION-P7-008-CLOSURE-001`, 2026-09-26, under environmental exception
`DECISION-P7-008-AC2-DISPOSITION-001`; AC2 remains NOT PASS, carried
forward to P7-001 certification). This review does not authorize any later
Phase 7 wave.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-008
- Canonical title: Deployment, Migration Safety, Rollback
- Phase: 7 — Production Hardening
- Proposed state: READY (HPO promotion `DECISION-PHASE7-WAVE1-READY-PROMOTION-001`, 2026-09-26)

## 2. Objective

Define and deliver the production deployment contract: runtime topology,
service supervision installation, environment/configuration safety,
migration-safety and rollback procedures, and the operations runbook —
for the adopted single-server posture (local private storage, Redis
queue, PostgreSQL target via P7-002) — without implementing the
datastore migration, queue internals, or environment certification owned
by other P7 tasks.

## 3. Why This Task Exists

- No production deployment procedure, topology, or rollback path exists;
  the app has only ever run in dev/test environments (SQLite, database
  queue, local disk by convention, not by contract).
- D7-01/D7-02/D7-03 fix the production topology (PostgreSQL / Redis /
  local disk) but no task owns installing, supervising, configuring, or
  rolling it back — that is this task.
- Production gate G-08 requires release/rollback procedure evidence;
  G-02/G-03 require migration + supervised-queue evidence that this task
  hosts operationally.
- TD-002 (PHP upload limits below the 500 MiB boundary) must be
  configured at the deployment layer even though its proof belongs to
  P7-001/P7-009.

## 4. Binding Decisions / ADRs

- D7-01 = OPTION B (self-hosted PostgreSQL): deployment must target a
  Postgres-backed runtime; the migration itself belongs to P7-002.
- D7-02 = OPTION A (Redis + systemd/supervisord, no Horizon): deployment
  installs/enables supervision per the P7-003 specification; no Horizon.
- D7-03 = OPTION A (local private storage): deployment assumes node-local
  private disk (permissions, capacity, backup interaction documented);
  no object-storage path.
- D7-08 = OPTION A (single-admin): single-node topology; no clustering,
  no multi-instance orchestration. Do not reopen any D7 decision.
- `PHASE7-SCOPE-CONTRACT.md` §§B–F; ADR-026; P7-005 observability
  conventions (frozen inputs).

## 5. Dependencies

- Hard prerequisites (satisfied at policy level):
  - D7-01/D7-02/D7-03 resolved; scope contract adopted.
  - P7-005 DONE (logging channel, correlation, diagnostics command
    patterns consumed unchanged).
- Soft dependencies (coordinate, do not block):
  - P7-003 supervision specification — P7-008 installs what P7-003
    specifies. If P7-003's spec is not yet VERIFIED, P7-008 implements
    against the authored spec revision and records the spec revision
    consumed; any later spec change is reconciled explicitly, not
    silently absorbed.
  - P7-002 (migration scripts this deployment must be able to run and
    roll back; migration content owned by P7-002).
  - P7-001 (environment validation certifies what this task deploys).
- Downstream: P7-007 (backup/restore operates on this topology),
  P7-009 (capacity tests run against a deployment built by this
  contract), P7-012 gate G-08.
- Parallel-safe: P7-010; parallel with P7-003 subject to the spec-version
  rule above.

## 6. In Scope

1. Production runtime topology document: single node running web server
   + PHP application + queue workers (per P7-003 spec) + scheduler +
   local Redis + PostgreSQL (target) + node-local private storage; port/
   process inventory; startup order.
2. Service supervision installation: systemd/supervisord units for web,
   queue workers (from the P7-003 spec revision consumed), and scheduler;
   enable-at-boot; restart policy; graceful-stop budgets; log routing
   into the P7-005 structured channel.
3. Environment/configuration contract: required production env matrix
   (APP_ENV/DEBUG, QUEUE_CONNECTION=redis, DB/Redis connection + secrets,
   storage paths, worker tokens, mail/session/cache drivers, logging
   channel); production-safe defaults; fail-fast validation of missing or
   unsafe production settings at boot/deploy time (compose from existing
   guards, do not duplicate their ownership).
4. Production-safe defaults in config: debug off, informative error
   pages safe, secure session/cookie flags, scheduler enabled,
   maintenance-mode procedure.
5. Secrets/config boundaries: what lives in env vs. committed config;
   secret-provisioning checklist (APP_KEY, DB/Redis passwords, worker
   tokens); explicit prohibition on committing secrets; log-safety rules.
6. Filesystem/private-storage assumptions (D7-03): storage root,
   ownership/permissions, capacity planning note, backup interaction
   point (hands off to P7-007 for mechanism).
7. PHP/web-server upload reception config (TD-002 deployment share):
   `upload_max_filesize`/`post_max_size`/proxy timeout/temp+capacity
   settings that admit the 500 MiB boundary plus overhead. The retained
   500 MiB proof itself belongs to P7-001/P7-009, not this task.
8. Migration safety: migrate/rollback procedure (backup-before-migrate
   hook to P7-007; `migrate --force` discipline; never edit historical
   migrations; P5/P6 migration inventory reconciled read-only);
   release layout supporting rollback (versioned releases with a
   current-pointer or documented equivalent; rollback = repoint +
   migrate:rollback plan + verify).
9. Operational startup/restart behavior: ordered start/stop/restart
   procedure (web → workers → scheduler and reverse), drain-before-stop
   for workers (reference P7-003 drain procedure).
10. Health/readiness expectations: operational checks built on existing
    signals (`/up`, P7-005 diagnostics command, queue depth, supervisor
    state); readiness criteria for declaring a deploy good; no new public
    route unless justified and reviewed.
11. Logging/observability integration: all services log through P7-005
    conventions; log retention note (hands retention policy to D7-06 as
    adopted).
12. Documentation/runbook: deployment runbook (install, configure,
    deploy, verify, rollback, common failures) as the home for the
    P7-003 queue chapter and the P7-007 backup hooks.

## 7. Explicit Non-Scope

- P7-002 internals: migration scripts, dual-driver verification, and the
  SQLite → PostgreSQL data migration itself. P7-008 runs and rolls back
  migrations; it does not author datastore migration content.
- P7-003 internals: queue semantics, guards, recovery logic. P7-008
  installs supervision per the P7-003 spec; it does not redefine worker
  behavior.
- P7-001 environment certification and the retained 500 MiB proof (G-01);
  P7-009 capacity testing; P7-007 backup mechanism and restore drill;
  P7-006 security baseline; P7-011 retention implementation.
- Multi-node/cluster orchestration, containers-as-requirement,
  object storage, Horizon, external metrics backends.
- Historical migration edits (forbidden); seeded production data.

## 8. Architecture / Domain Contract

- Single-node deployment is the architecture; every procedure in this
  task must work on one node and must not assume a second machine, shared
  filesystem, or load balancer.
- Layering: P7-008 owns deployment mechanics (units installed, topology,
  release layout, procedures); P7-003 owns worker-lifecycle semantics;
  P7-002 owns migration content; P7-001 owns environment proof. The
  handoff artifacts are the P7-003 supervision spec (revision-pinned) and
  the P7-002 migration scripts; P7-008 references both without owning
  either.
- Rollback is a first-class path, not an afterthought: every deploy
  procedure has a verified inverse documented beside it.

## 9. Detailed Implementation Requirements

1. Topology document + process inventory + startup order.
2. Supervisor unit files (or supervisord programs) for web/workers/
   scheduler with restart, stop budgets, log routing; enabled at boot.
3. Production env matrix + boot/deploy-time validation (missing/unsafe
   settings fail loudly before serving traffic).
4. Release layout + deploy/verify/rollback procedures, each with its
   inverse; backup-before-migrate hook referencing P7-007.
5. Migration-safety checklist: historical migrations untouched (verified
   by diff), P5/P6 inventory reconciled, migrate/rollback dry-run
   recorded.
6. TD-002 deployment share: PHP/web/proxy upload-reception settings for
   500 MiB + overhead, documented with the exact directives and files.
7. Runbook: install/configure/deploy/verify/rollback/failure-triage
   chapters; hosts the P7-003 queue chapter by reference.
8. Readiness criteria: the explicit checks that declare a deploy good
   (migrations current, workers consuming, scheduler ticking,
   diagnostics clean, smoke upload + playback path).

## 10. Failure / Recovery Semantics

- Failed deploy: halt, diagnose via readiness checks, roll back to the
  prior release pointer; database rollback only via the documented
  migrate:rollback plan with pre-migrate backup; never hand-edit
  production data to "fix forward" without a reviewed procedure.
- Worker/supervisor failure at runtime: covered by P7-003 semantics;
  this task's share is detection (health checks) + restart via
  supervision + runbook triage.
- Partial deploy (code updated, migrations not applied): boot validation
  refuses traffic until migrations are current; procedure covers this
  ordering explicitly.
- Rollback of a migration that already served writes: requires the
  pre-migrate backup + P7-002's rollback plan; this task documents the
  decision point, P7-002 owns the data mechanics.

## 11. Security / Privacy Requirements

- Secrets never committed; production env files permission-restricted;
  secret-provisioning checklist completed per deploy.
- Debug/introspection endpoints off in production; error output safe.
- File permissions on private storage deny other system users; backup
  interaction does not expose media outside the node except through the
  P7-007 mechanism.
- No weakening of worker authentication or ownership/authorization
  boundaries; tenancy redesign stays out (DC-02).

## 12. Observability / Operations Requirements

- All services emit through P7-005 conventions (JSON channel selectable;
  correlation IDs end to end); diagnostics command is part of readiness.
- Operational signals defined: deploy version marker in logs, readiness
  check results retained per deploy, supervisor state queryable.
- Alerting hooks defined (not backend-implemented): deploy failure,
  readiness-check failure, worker absence, queue growth.

## 13. Acceptance Criteria

- AC1: Topology document complete (processes, ports, order, node-local
  assumptions all explicit).
- AC2: Supervisor units installed, enabled at boot, and verified across
  a reboot cycle (workers + scheduler resume; evidence retained).
- AC3: Missing/unsafe production settings fail loudly before traffic is
  served (demonstrated for at least: debug flag, queue connection,
  secrets absent).
- AC4: Deploy → verify → rollback → re-deploy cycle executed in the
  verification environment with readiness checks green at each good
  state.
- AC5: Migration dry-run recorded: historical migrations untouched,
  P5/P6 inventory reconciled, backup-before-migrate hook exercised.
- AC6: TD-002 deployment share: upload-reception directives documented
  and applied in the verification environment (proof of 500 MiB itself
  stays with P7-001/P7-009).
- AC7: Runbook complete and its procedures executed once (install,
  deploy, rollback, triage) with retained notes.
- AC8: No P7-002/P7-003/P7-001 scope absorbed (boundary audit against
  §7); no Horizon; no multi-node assumption.
- AC9: Full regression suite green; Pint clean; PHPStan 0 errors; no P6
  editing/revision/export regression.

## 14. Test / Verification Requirements

- Automated: config/env validation tests (unsafe combinations rejected);
  release-layout and procedure tests where scriptable; migration
  inventory test (historical migrations byte-identical).
- Integration: deploy/rollback cycle in a verification environment
  (VM/container/second directory acceptable — production node itself is
  not required); reboot-resumption verification.
- Failure-path: failed deploy, partial deploy (migrations pending),
  missing-secret boot, worker-absent detection — each demonstrated or
  dry-run with retained notes.
- Regression: full PHP suite; Phase 6 editing/revision/export suites
  green (deployment change must not alter domain behavior).
- Manual/operational: runbook executed end to end once; readiness
  criteria applied to a real deploy cycle.

## 15. Technical Debt Mapping

- TD-002 (HIGH/YES): deployment-share owner — this task configures
  upload-reception limits; certification/proof stays with P7-001/P7-009.
  This task must not claim G-01 satisfied.
- TD-001: informed (deployed-stack evidence accumulates here) but owned
  by P7-012.
- TD-003/TD-004: consumed (supervised topology hosts the P7-003
  remediation) but remediated in P7-003, not here.

## 16. Risks / Regression Concerns

- P7-003 spec drift: mitigated by revision-pinning the consumed spec and
  explicit reconciliation on change.
- Rollback with served writes is the highest-risk path → backup hook +
  P7-002 plan mandatory; dry-run required.
- Single-node backup interaction: P7-007 mechanism must match this
  topology; flag mismatch early rather than adapting silently.
- Boot validation that is too strict can block legitimate deploys;
  too lax admits unsafe production → each check needs a stated rationale
  and a test.

## 17. Completion Evidence Required

- Contract diff + implementation diff + test results (targeted, full
  suite counts) + Pint + PHPStan + topology doc + unit files + env
  matrix + deploy/rollback cycle notes + migration dry-run record +
  runbook, referenced from the builder report.

## 18. Reviewer Checklist

- [ ] Single-node assumption holds everywhere; no hidden second-machine
  dependency.
- [ ] P7-002/P7-003/P7-001 boundaries respected (runs/uses, does not own).
- [ ] Every deploy procedure has a documented, exercised inverse.
- [ ] Historical migrations untouched; inventory reconciled.
- [ ] Secrets handling reviewed (none committed, permissions set).
- [ ] Readiness criteria objective and demonstrated.
- [ ] No Horizon, no object storage, no clustering introduced.
- [ ] Evidence independently reproducible.

## 19. State Transition Rule

- `BACKLOG / CONTRACT_REQUIRED → READY`: requires (a) this canonical
  contract, (b) dependency reconciliation recorded (D7-01/02/03 resolved;
  scope adopted; P7-005 DONE consumed; P7-003 spec revision consumed or
  explicitly awaited), and (c) an explicit HPO READY promotion
  (`DECISION-P7-008-READY-001` or batch equivalent). Contract authoring
  alone does not promote.
- `READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`: per
  `.ai/guidelines/orchestration-policy.md` — HPO assigns (or authorizes
  self-assignment); OpenCode implements; Claude Code reviews (VERIFIED /
  CHANGES_REQUESTED, max 3 cycles then BLOCKED); HPO closes VERIFIED as
  DONE. OpenCode must not self-verify or self-close. READY does not equal
  execution authorization: IN_PROGRESS additionally requires the separate
  HPO Wave 1 execution authorization.
