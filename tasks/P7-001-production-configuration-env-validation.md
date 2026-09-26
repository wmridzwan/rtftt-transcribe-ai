# P7-001 — Production Configuration + Env Validation

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-001-CLOSURE-001`) on the independent VERIFIED verdict
below, under environmental exception
`DECISION-P7-001-AC8-DISPOSITION-001` (Option A; AC8 NOT PASS, carried
forward pre-P7-012).

VERIFIED — independent review (`reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md`
§1) 2026-09-26: AC1–AC7 independently reproduced PASS; AC8 recorded
BLOCKED-ENVIRONMENT (genuine target-host gap, no code defect — mirrors
`DECISION-P7-008-AC2-DISPOSITION-001`). No BLOCKER/HIGH finding. Not DONE;
DONE requires HPO closure, which should also issue an environmental
disposition for AC8 analogous to the P7-008 precedent before this task
closes.

History preserved: BACKLOG — CONTRACT_AUTHORED → reconciled → READY (HPO
`DECISION-PHASE7-WAVE2-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-001-BUILDER-REPORT.md`;
AC8 real-host execution recorded TARGET-PENDING with mechanism +
procedure, per contract) → VERIFIED with AC8 flagged BLOCKED-ENVIRONMENT
(`reviews/PHASE7-WAVE2-INDEPENDENT-REVIEW.md` §1, 2026-09-26) → DONE (HPO
`DECISION-P7-001-CLOSURE-001`, 2026-09-26, under
`DECISION-P7-001-AC8-DISPOSITION-001`).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26, Wave 2
preparation while Wave 1 was under independent review; planning only,
no implementation) → reconciled against final Wave 1 DONE interfaces
(P7-003 DONE `DECISION-P7-003-CLOSURE-001`; P7-008 DONE
`DECISION-P7-008-CLOSURE-001` under environmental exception
`DECISION-P7-008-AC2-DISPOSITION-001`; P7-010 DONE
`DECISION-P7-010-CLOSURE-001`) → READY. Not authorized for
implementation; READY != EXECUTION AUTHORIZATION. No code written
under this contract.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-001
- Canonical title: Production Configuration + Env Validation
- Phase: 7 — Production Hardening
- Proposed state: READY (HPO promotion
  `DECISION-PHASE7-WAVE2-READY-PROMOTION-001`, 2026-09-26; reconciled
  against final Wave 1 DONE interfaces — see §5)

## 2. Objective

Make production misconfiguration fail loudly at boot and make the
deployed environment provably capable of the approved product
boundaries: complete the environment/config validation story that
P7-008 started (its `ProductionConfigGuard` covers the deployment
matrix), certify the Redis posture left conditional by Wave 1 (TD-004 /
HPO-04), deliver the application-layer configuration for the 500 MiB
receiving contract (TD-002), and own the central production-env key
registry that later tasks (P7-006, P7-007) extend — without duplicating
P7-008's guard, without owning deployment mechanics, and without
claiming the retained 500 MiB proof that belongs to P7-009.

## 3. Why This Task Exists

- Default PHP/web-server receiving limits cannot accept the approved
  500 MiB (524,288,000-byte) product boundary (TD-002, HIGH/YES).
  P7-008 delivered deployment-layer directives only; the
  application-layer config (fail-fast validation that the effective
  limits satisfy the boundary, plus capacity notes) is unowned.
- P7-003 defined the required Redis posture and P7-008 installed the
  topology, but TD-004 (Redis passwordless-by-default, MEDIUM /
  CONDITIONAL) stays OPEN: no per-environment auth/network/TLS posture
  has been decided and certified (HPO-04). The P7-003 builder report
  states explicitly: "TD-004: posture defined; certification belongs to
  P7-001."
- `REDIS_PASSWORD=null` ships by default (`.env.example:57`); loopback
  usage is acceptable for dev but the production posture is undecided.
- Production gate G-01 (500 MiB receiving) and G-04 (Redis posture)
  both name P7-001 as an owner. Neither can close without this task.

## 4. Binding Decisions / ADRs

- D7-02 = OPTION A (Redis + systemd/supervisord, no Horizon) —
  `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026. The env contract
  requires Redis; there is no database-queue production path.
- D7-01 = OPTION B (self-hosted PostgreSQL) — config validation must
  carry the Postgres-target variables as advisory until P7-002 lands;
  they must not fail a pre-migration deployment, and must fail a
  post-migration deployment when absent. Do not implement the migration.
- D7-03 = OPTION A (local private storage) — capacity variables
  (disk free, temp capacity) are validated, not redesigned.
- D7-08 = OPTION A (single-admin) — capacity notes target this model.
- ADR-026 implementation constraints (no non-Chromium claims, no
  third-party upload-data egress, backup/restore evidence rules) apply
  where config touches them.
- `PHASE7-SCOPE-CONTRACT.md` §§B–F; `docs/PRODUCTION_READINESS_GATE.md`
  G-01/G-04. Do not reopen any D7 decision.

## 5. Dependencies

- Hard prerequisites (satisfied at policy level):
  - D7-01/D7-02/D7-03 resolved; scope contract adopted; Phase 6 CLOSED.
  - P7-005 DONE — diagnostics conventions consumed unchanged.
- Wave 1 final interfaces (reconciled 2026-09-26; all DONE):
  - P7-008 DONE (`DECISION-P7-008-CLOSURE-001`) under environmental
    exception `DECISION-P7-008-AC2-DISPOSITION-001` (AC2 explicitly NOT
    PASS). This task extends `ProductionConfigGuard::violations()` /
    `assertValid()` and the `deployment:verify` sub-checks
    (`checkQueueGuards` composes the queue guards via
    `*QueueConfig::consistencyViolation()` — no duplication); it must
    not duplicate or contradict them.
  - P7-003 DONE (`DECISION-P7-003-CLOSURE-001`) — supervision spec
    (`docs/QUEUE-WORKER-SUPERVISION.md`, incl. §8 Redis posture:
    "TD-004 definition; P7-001 certifies") and queue-key ownership.
    P7-001 appends only non-queue production keys and references
    (never redefines) P7-003 keys.
  - P7-010 DONE — no interface consumed.
  - Carry-forward obligation (binding, from the AC2 disposition): the
    real-host reboot-cycle verification (P7-008 AC2) plus the real-host
    SIGTERM-drain re-confirmation (P7-003 AC8, review INFO-1) MUST be
    performed during P7-001 environment certification on the Linux
    production/staging target, before P7-012. This obligation is scoped
    in §6.7 / §9.8 / AC8. No further P7-008 implementation is implied
    unless that verification reveals a defect.
- Soft dependencies (coordinate, do not block):
  - P7-006 / P7-007 — own their service key names (ClamAV, backup);
    P7-001 owns the registry mechanism they register into.
- Downstream: P7-009 consumes the validated config for the G-01 proof;
  P7-012 gates G-01/G-04 on this task DONE.

## 6. In Scope

1. Central production-env key registry: one authoritative list of
   required/advisory/forbidden production keys with per-key shape rules
   (presence, format, minimum entropy where applicable), extension
   points for P7-006/P7-007 keys, and a test that fails when code reads
   an unregistered production key.
2. Fail-fast boot validation completion: extend (not replace)
   P7-008's `ProductionConfigGuard` with the keys P7-008 deferred —
   Redis auth/TLS posture per environment, upload-limit adequacy
   (`upload_max_filesize`/`post_max_size` vs the 500 MiB boundary plus
   multipart overhead), storage capacity signals, worker URL/token
   presence — enforced in production only, advisory elsewhere.
3. TD-004 certification: record the per-environment Redis posture
   (loopback-dev vs authenticated/TLS production), verify the deployed
   Redis version/config against it, and prove no passwordless Redis is
   reachable outside loopback isolation (HPO-04 evidence).
4. TD-002 application share: effective-limit audit (PHP SAPI, web
   server/proxy, timeouts, temp + durable capacity checklist) with a
   machine-checkable report command; application-level rejection above
   the boundary stays exact (524,288,000 accepted / 524,288,001
   rejected).
5. Secrets-handling rules: no secret in the repo, no secret in logs
   (P7-005 channel audit), `.env.example` stays a shape-only template;
   rotation guidance documented.
6. `deployment:verify` extension within P7-008 conventions: add the
   §6.2–6.4 checks as new sub-checks alongside the final
   `checkMigrations` / `checkQueueGuards` / `checkProductionMatrix` /
   `checkSchedules` / `checkStorage`, reusing its output format and
   `--strict` advisory-vs-enforcing semantics.
7. Real-host carry-forward (binding per
   `DECISION-P7-008-AC2-DISPOSITION-001`): during P7-001 certification
   on the Linux production/staging target, execute and record (a) the
   P7-008 AC2 reboot-cycle verification (supervised units enabled at
   boot, workers resume, scheduler timer active) and (b) the P7-003
   AC8 SIGTERM-drain re-confirmation against a real supervised worker.
   Both must pass before P7-012; AC2 is not rewritten as PASS by this
   task under any circumstance. If either reveals a defect, ownership
   returns to the originating task via governance (no silent fix here).

## 7. Explicit Non-Scope

- The retained real-500 MiB-multipart-through-the-production-stack
  proof (G-01) — owned by P7-009; this task delivers the config that
  makes it possible, not the proof.
- Deployment mechanics, topology, unit files, release layout (P7-008);
  queue internals, supervision spec, retry_after values (P7-003);
  datastore migration (P7-002); ClamAV service integration (P7-006);
  backup mechanism (P7-007); browser work (P7-010); retention purge
  (P7-011).
- Redefining any P7-003 queue key or P7-008 deployment-matrix entry.
- Horizon, object storage, multi-instance orchestration, external
  metrics/tracing backends.

## 8. Architecture / Domain Contract

- Guard layering: P7-008 owns the guard class and boot hook;
  P7-001 contributes additive rule sets through
  `ProductionConfigGuard::violations()` (the final extension point;
  verified additive alongside the P7-003 guard registrations in
  `AppServiceProvider::boot()` with no overwrite). No forked
  validation paths: exactly one production boot verdict.
- Registry pattern: a single `ProductionEnvRegistry` (or equivalent
  config-shaped source) maps key → {required|advisory, shape, owner
  task}. P7-006/P7-007 register keys; P7-001 validates shapes. Unknown
  production keys fail the registry test, forcing explicit ownership.
- Environment layering: dev/test defaults unchanged
  (`QUEUE_CONNECTION=database`, loopback Redis, SQLite); production
  requires Redis + Postgres-target vars (advisory until P7-002, then
  required — the flip is a P7-002-owned contract change, recorded here
  as an interface note).
- No new public route; diagnostics surface through the P7-005
  diagnostics command conventions.

## 9. Detailed Implementation Requirements

1. Registry + shape validation with per-key error messages naming the
   key, the expected shape, and the owning task.
2. Boot-guard extension: production boot refuses (non-zero, logged,
   actionable message) on missing/invalid required keys, inadequate
   effective upload limits, passwordless non-loopback Redis, and
   unwritable storage paths; non-production stays advisory.
3. Limit-adequacy audit command (e.g. `env:audit-limits` or a
   `deployment:verify` sub-check): reports effective PHP SAPI limits,
   proxy/timeout notes, temp + durable capacity, and PASS/FAIL against
   500 MiB + overhead.
4. TD-004 evidence: Redis `INFO server` version capture, auth/TLS
   posture record per environment, loopback-isolation proof (connection
   refused/unauthenticated from a non-loopback interface where the
   topology allows the test safely).
5. `.env.example` additions limited to P7-001-owned keys; P7-003 queue
   keys referenced, never redefined; shape-only values, no secrets.
6. Secrets audit: grep-based test proving no secret-shaped value in
   tracked files and no secret leakage into the structured log channel
   in the covered paths.
7. Docs: env matrix deltas + rotation guidance appended to the
   deployment runbook by reference (P7-008 owns the runbook file;
   coordinate to avoid edit collision — see §16).
8. Carry-forward execution (§6.7): run the AC2 reboot-cycle and AC8
   SIGTERM-drain verifications on the Linux target, retain
   machine-readable evidence (unit states, boot logs, drain timing vs
   the 330s job / 420s stop budget), and record binary PASS/FAIL per
   item. A FAIL is reported as a finding against the originating task
   through governance; this task does not remediate Wave 1 code.

## 10. Failure / Recovery Semantics

- Invalid production config fails closed at boot (refuse to serve),
  never degrades to an insecure default (no silent database-queue
  fallback, no passwordless-Redis fallback).
- Advisory (non-production) failures warn through the structured
  channel without blocking boot or tests.
- Registry test failure (unregistered production key) blocks CI, not
  production boot, to avoid coupling deploys to test-only keys.
- Rollback: config-only revert restores the prior guard behavior;
  document which keys are safe to change without a worker restart vs
  requiring drain/restart (queue keys per P7-003 drain procedure).

## 11. Security / Privacy Requirements

- No secret material in the repo, in tests, in logs, or in retained
  evidence artifacts (redact before committing verification output).
- Redis auth/TLS posture must not rely on network isolation alone
  where the topology leaves loopback; record the residual risk where
  it does (dev-only) and prohibit it in production.
- Upload-limit changes must be paired with P7-006 abuse limits; this
  task records the pairing requirement, P7-006 implements it.
- User media is never used as config-test fixture content beyond the
  existing seeded fixtures; no new PII surface.

## 12. Observability / Operations Requirements

- All boot refusals emit structured log lines with the P7-005
  correlation fields where available, the failing key names (never
  values), and remediation pointers.
- The limit-audit output is machine-parseable (JSON option) for the
  P7-009 harness and human-readable by default.
- Diagnostics command gains the env/registry section within P7-005
  field naming; no P7-005 line format is altered.

## 13. Acceptance Criteria

- AC1: Production boot with a complete valid env succeeds; each
  required key removed (one at a time) fails fast with a message
  naming the key and remedy. (Tests.)
- AC2: Effective upload limits below 500 MiB + overhead fail the
  audit; limits at/above pass; boundary math documented. (Command +
  tests.)
- AC3: TD-004 certified per environment: posture recorded, Redis
  version/config captured, non-loopback passwordless access proven
  absent in production topology. (Evidence + tests.)
- AC4: Registry test fails CI on any unregistered production key read;
  P7-003 keys and P7-008 matrix entries remain single-owned (no
  redefinition). (Tests + diff audit.)
- AC5: No secret in tracked files, logs, or retained evidence.
  (Grep test + reviewer artifact inspection.)
- AC6: `deployment:verify` extended checks green; pre-existing P7-008
  checks unbroken. (Tests.)
- AC7: Full suite green, Pint clean, PHPStan 0, no P6 regression, no
  Horizon/object-storage/cluster content. (Standard gate.)
- AC8: Real-host carry-forward executed on the Linux target before
  P7-012: P7-008 AC2 reboot-cycle PASS with retained evidence
  (enabled units, post-reboot worker/scheduler states) and P7-003 AC8
  SIGTERM-drain re-confirmation PASS with retained drain timing;
  neither AC is rewritten as PASS without this evidence; any FAIL is
  reported through governance, not silently fixed. (Target-host
  evidence + §9.8 procedure.)

## 14. Test / Verification Requirements

- Feature tests: registry completeness/violation, guard production vs
  advisory behavior (env-gated), limit-audit PASS/FAIL boundaries,
  secrets-absence grep, `deployment:verify` extension.
- Target-host verification (§6.7/AC8): executed on the Linux
  production/staging target; where a sub-check cannot execute, mark it
  target-pending with substitute reasoning per the P7-008 AC2
  precedent — but AC8 as a whole cannot PASS until the real-host
  evidence exists (disposition requirement, not a precedent loophole).
- No live-production mutation: all Redis/TLS probes run against the
  verification environment; production-topology claims that cannot be
  executed here are marked target-only with substitute evidence, as
  P7-008 AC2 precedent requires.
- Full PHP suite + Pint (`--dirty --format agent` after any PHP change)
  + PHPStan level 7; Phase 6 editing/revision/export suites included
  in the full run.

## 15. Technical-Debt Mapping

- TD-002: P7-001 application share delivered (config + audit); G-01
  proof NOT claimed (P7-009 owns it). Status change follows the HPO
  closure lifecycle — not marked closed here.
- TD-004: certification delivered (HPO-04 evidence); posture decision
  recorded per environment. NOT marked closed here.
- TD-001/TD-003: informed/consumed only. Nothing marked closed.

## 16. Risks / Regression Concerns

- P7-008 guard-surface collision is the top risk: two tasks owning
  boot validation can fork the verdict. Mitigation: additive rules
  through P7-008's extension point, reviewed against the VERIFIED
  P7-008 guard; runbook edits coordinated (P7-008 owns the file).
- Postgres-vars advisory/required flip must not brick pre-P7-002
  deployments: default advisory, flip owned by P7-002 contract.
- Over-strict production guards can block legitimate single-admin
  setups: every refusal must carry a documented override-or-remedy
  path reviewed by the HPO.

## 17. Completion Evidence

- Builder report listing files changed, AC verdicts with evidence
  pointers, test/verification matrix (incl. full-suite counts, Pint,
  PHPStan), TD mapping, and known limitations (target-only items
  flagged as P7-008 AC2 precedent).
- Retained artifacts: limit-audit JSON, Redis posture record
  (redacted), registry snapshot, AC2/AC8 real-host carry-forward
  evidence (unit states, boot logs, drain timing).

## 18. Reviewer Checklist

- [ ] No P7-003 queue key redefined; no P7-008 matrix entry duplicated
      or contradicted (diff audit on `.env.example`, guard, verify).
- [ ] Production boot fails closed on every required-key removal; no
      silent insecure fallback exists on any path.
- [ ] TD-004 evidence is per-environment and redacted; no passwordless
      non-loopback Redis is reachable in the production topology.
- [ ] G-01 proof is not claimed; the P7-009 handoff (config + audit
      output) is explicit.
- [ ] No secret in repo/logs/evidence; registry test genuinely fails
      on an unregistered key (negative test present).
- [ ] Full suite + Pint + PHPStan reproduced; no P6 regression.
- [ ] AC8 carry-forward evidence present and binary (PASS per item or
  FAIL reported through governance); AC2 nowhere rewritten as PASS
  without real-host evidence.

## 19. State Transition Rule

READY (HPO promotion `DECISION-PHASE7-WAVE2-READY-PROMOTION-001`,
2026-09-26; reconciled against final Wave 1 DONE interfaces per §5).
READY → IN_PROGRESS only when work begins under an explicit HPO Wave 2
execution authorization (not granted). REVIEW → VERIFIED/
CHANGES_REQUESTED by the independent reviewer; VERIFIED → DONE only by
HPO closure. `READY != EXECUTION AUTHORIZATION.`
