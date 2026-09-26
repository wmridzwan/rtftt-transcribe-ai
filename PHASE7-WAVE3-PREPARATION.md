# Phase 7 Wave 3 — Preparation Report (PREPARATION ONLY, NOT AUTHORIZED)

Date: 2026-09-26
Status: `WAVE 3 PREPARATION ONLY` — planning artifact. Authorizes no
implementation, no READY promotion, no execution, no state transition.
Wave 2 (P7-001/P7-006/P7-007) is in REVIEW; its independent verdict is
pending. All new artifacts below are BACKLOG/CONTRACT_AUTHORED planning
material, clearly distinguishable from Wave 2 review evidence.

New files created by this preparation (only these):

- `tasks/P7-002-production-datastore-migration.md`
- `tasks/P7-004-storage-streaming-hardening.md`
- `tasks/P7-009-performance-load-validation.md`
- `tasks/P7-011-retention-cleanup.md`
- `PHASE7-WAVE3-PREPARATION.md` (this file)

No existing file was modified by this preparation. No Wave 2
implementation file, builder report, or review artifact was touched.

---

## A. Baseline

- Wave 1 = CLOSED (P7-003/P7-008/P7-010 DONE; P7-008 AC2 NOT PASS under
  `DECISION-P7-008-AC2-DISPOSITION-001`, carried to P7-001 certification).
- Wave 2 = REVIEW (P7-001/P7-006/P7-007 implementation complete 2026-09-26
  under `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`; builder
  reports `reviews/P7-001-BUILDER-REPORT.md`,
  `reviews/P7-006-BUILDER-REPORT.md`, `reviews/P7-007-BUILDER-REPORT.md`;
  independent verdict pending; no closure).
- Wave 3 execution authorization does not exist. Prohibited scope under
  the Wave 2 authorization explicitly included P7-002/P7-004/P7-009/P7-011.
- Binding policy: D7-01..D7-08 RESOLVED (ADR-026); scope contract ADOPTED
  (`PHASE7-SCOPE-CONTRACT.md`); Phase 6 CLOSED (satisfied prerequisite
  for all MUST_WAIT tasks).

## B. Canonical Wave 3 Candidates

| Task | Title | Current State | Contract Exists? | Wave 2 Dependency |
|---|---|---|---|---|
| P7-002 | Production Data Store Finalization + Migration | BACKLOG (CONTRACT_AUTHORED, this prep) | Yes (authored now) | REQUIRES_WAVE2_DONE (P7-001, P7-007) |
| P7-004 | Storage Strategy + Streaming Hardening | BACKLOG (CONTRACT_AUTHORED, this prep) | Yes (authored now) | REQUIRES_WAVE2_DONE (P7-001, P7-006, P7-007) |
| P7-009 | Performance / Load / Large-File Validation | BACKLOG (CONTRACT_AUTHORED, this prep) | Yes (authored now) | CONTRACT_DEPENDENCY_ONLY (P7-001 interface; P7-003 posture) |
| P7-011 | Retention, Derived-Artifact + Orphan Cleanup | BACKLOG (CONTRACT_AUTHORED, this prep) | Yes (authored now) | CONTRACT_DEPENDENCY_ONLY (P7-007 + P7-001 interfaces); Wave 3 gate P7-004 VERIFIED |
| P7-007 drill | Final PostgreSQL restore drill (deferred P7-007 scope) | DEFERRED (inside P7-007 contract §7) | Yes (P7-007 contract; no new task) | Requires P7-007 DONE + P7-002 DONE |
| P7-012 | Production Readiness Gate | FINAL_GATE_ONLY (no contract now) | No (correct — terminal gate only) | Requires ALL P7 DONE |

The earlier 3-task Wave 3 suggestion (P7-002/P7-004/P7-011) is corrected:
P7-009 is equally Wave 3-scoped (uncontracted, D7-08 resolved, required
before P7-012) and must not be forgotten. Wave 3 = FOUR implementers +
the P7-007 drill follow-on. P7-012 stays FINAL_GATE_ONLY (no contract
authored now, per §12 gate discipline).

No candidate was previously prohibited except by the Wave 2 execution
authorization's non-Wave-2 prohibition (now recorded as the reason
implementation must wait, not as a permanent bar).

## C. Dependency Classification

- P7-002 → `REQUIRES_WAVE2_DONE`. Upstream: P7-001 DONE (registry
  advisory→required flip mechanism), P7-007 DONE (backup-before-migrate
  hook). Also consumes DONE P7-008 (rollback mechanics) by reference.
  (Contract-authored now; implementation requires P7-001 + P7-007 DONE.)
- P7-004 → `REQUIRES_WAVE2_DONE`. Upstream: P7-001 DONE (capacity-posture
  interface), P7-006 DONE (owns `MediaIngestionService::gateMalwareScan`;
  no fork), P7-007 DONE (media-manifest semantics; compatibility only).
- P7-009 → `CONTRACT_DEPENDENCY_ONLY`. Upstream: P7-001 (config/audit
  interface for G-01), P7-003 posture (exercised, not changed). Hard
  Wave 3-internal prerequisites: P7-002 DONE + P7-004 DONE (final run
  validity). Harness scaffolding is separable from the final run.
- P7-011 → `CONTRACT_DEPENDENCY_ONLY` on Wave 2 (P7-007 retention-math
  interface; P7-001 registry) + Wave 3-internal hard gate P7-004
  VERIFIED (binding — §E). No Wave 2 implementation dependency.
- P7-007 drill → `REQUIRES_WAVE2_DONE` transitively (P7-007 foundation
  DONE) + `REQUIRES P7-002 DONE`. No new task (see §E).

No candidate is classified BLOCKED_BY_WAVE2_FINDING at this time (no
Wave 2 finding exists yet — contingencies in §I).

## D. Contracts Authored / Updated

Authored (all 19 required sections; BACKLOG/CONTRACT_AUTHORED; no
duplicates — no prior files existed for these IDs):

1. `tasks/P7-002-production-datastore-migration.md` — D7-01/B migration,
   schema compatibility, ordering, driver behavior, preservation,
   revision/history invariants, rollback, backup-before-migrate (P7-007),
   P7-008 interaction, dual-driver tests, failure handling, env
   boundaries, regression.
2. `tasks/P7-004-storage-streaming-hardening.md` — D7-03/A topology,
   paths, streaming/range, durability, capacity/health, revision/export
   compatibility, TD-011 verification, non-migration boundary, P7-009
   interaction, S3 non-scope.
3. `tasks/P7-009-performance-load-validation.md` — D7-08/A envelope,
   G-01/G-10/G-12 evidence, harness-vs-final-run split, canonical-model
   measurement discipline.
4. `tasks/P7-011-retention-cleanup.md` — D7-06 30-day purge, P6
   invariants, eligibility, clock-start, failure behavior,
   retry/idempotency, ADR-005 interaction, audit, export behavior,
   disclosure, TD-007 ownership.

Updated: none (no canonical contracts pre-existed for these IDs).

## E. P7-007 Final Drill Path

Legal path (no new task; no Wave 2 contract rewrite):

```
P7-007 foundation (Wave 2 REVIEW → VERIFIED → DONE)
  → P7-002 DONE (pg production store exists; dormant pg path activates)
  → HPO drill authorization (separate explicit act; Wave 2 auth covered
     foundation only — drill needs its own authorization)
  → P7-007 resumes IN_PROGRESS under its existing contract's deferred
     §7 scope (executed restore drill against PostgreSQL)
  → drill evidence retained (RPO/RTO per D7-07/A)
  → P7-012 consumes G-08 (P7-012 decides; drill does not self-claim it)
```

- The drill is P7-007 resumed/follow-up work after P7-002 (not a new
  task ID; P7-007 contract §7 + procedures already name Wave 3 as the
  drill window with prerequisites P7-002 DONE + foundation DONE).
- It belongs inside Wave 3 execution (sequenced after P7-002 DONE).
- It is also terminal-gate evidence (G-08 consumed at P7-012).
- It must occur before P7-012 (G-08 entry).

## F. READY Eligibility

| Task | Contract Complete | Dependencies | READY Eligibility | Current State |
|---|---|---|---|---|
| P7-002 | Yes (19 sections) | P7-001 DONE ✗, P7-007 DONE ✗ (Wave 2 REVIEW) | READY-ELIGIBLE ONLY AFTER WAVE2 DONE | BACKLOG |
| P7-004 | Yes (19 sections) | P7-001/006/007 DONE ✗ (Wave 2 REVIEW) | READY-ELIGIBLE ONLY AFTER WAVE2 DONE | BACKLOG |
| P7-009 | Yes (19 sections) | P7-002 DONE ✗, P7-004 DONE ✗ (Wave 3-internal) | NOT READY — DEPENDENCY | BACKLOG |
| P7-011 | Yes (19 sections) | P7-004 VERIFIED ✗ (binding; Wave 3-internal) | NOT READY — DEPENDENCY | BACKLOG |
| P7-007 drill | Yes (P7-007 §7) | P7-007 DONE ✗, P7-002 DONE ✗ | NOT READY — DEPENDENCY | DEFERRED |
| P7-012 | No (correct) | All P7 DONE ✗ | NOT READY — GOVERNANCE (FINAL_GATE_ONLY) | BACKLOG |

Preparation ≠ READY. No promotion is effected or requested here.

## G. Wave 2 / Wave 3 File-Overlap Matrix

Wave 2 implementation surfaces per builder reports (evidence, not
modified here):

| File / Surface | Wave 2 Owner | Wave 3 Candidate | Overlap Risk | Required Action |
|---|---|---|---|---|
| `app/Deployment/*` (registry, posture, audit, target evidence) | P7-001 | P7-002 (pg key flip) | HIGH | Deltas through P7-001 extension point only, post-DONE |
| `app/Console/Commands/DeploymentVerify.php` | P7-008 (+P7-001/P7-007 additive) | P7-002, P7-004, P7-011 (sub-checks) | HIGH | Additive sub-checks only; P7-008 conventions; post-DONE |
| `app/Console/Commands/ObservabilityDiagnostics.php` | P7-005 (+Wave 1/2 sections) | P7-002, P7-004, P7-011 (sections) | MEDIUM | Additive sections; field naming untouched; post-DONE |
| `docs/DEPLOYMENT-RUNBOOK.md` (§11 P7-001, §12 P7-006, §13 P7-007) | P7-008 (owns file) | P7-002, P7-004, P7-009, P7-011, drill | HIGH | Reference/append deltas only; P7-008 owns file; post-DONE |
| `.env.example` (ClamAV block P7-006; backup keys P7-007) | P7-006 / P7-007 | P7-002 (DB vars), P7-011 (retention keys) | MEDIUM | Append-only distinct blocks; zero key redefinition; post-DONE |
| `app/Actions/MediaIngestionService.php` (`gateMalwareScan`) | P7-006 | P7-004 (streaming compat tests) | MEDIUM | Tests only against scan path; no behavior change; post-DONE |
| `app/Backup/BackupManager.php` + manifest semantics | P7-007 | P7-002 (pg activation handoff), P7-004 (compat), P7-011 (generation respect) | MEDIUM | Consume, never redefine; dormant-test supersession recorded explicitly (P7-002) |
| `config/backup.php` (target/generations/stale) | P7-007 | P7-011 (respect retention math) | LOW | No redefinition; compatibility tests |
| `routes/console.php` (backup schedule + P7-006 schedules) | P7-007 / P7-006 | P7-011 (purge schedule) | MEDIUM | Coordinate overlap protection; post-DONE |
| `config/deployment.php` (`min_free_bytes`) | P7-001 | P7-004 (capacity floor wiring) | LOW | Consume mechanism; P7-001 owns guard |
| `config/security.php` + `app/Security/*` + middleware | P7-006 | P7-009 (load runs with guards armed) | LOW | No change; armed-guard rule in P7-009 contract |
| `database/migrations/*scan_verdict*` + inventory | P7-006 (+P7-008 inventory) | P7-002 (pg audit + append) | LOW | Audit only; new migrations appended |
| `app/Models/MediaFile.php` (scan columns) | P7-006 | P7-002 (migrate as ordinary columns), P7-011 (purge traversal) | LOW | No schema redefinition outside P7-002's additive rule |
| `deploy/migrations-inventory.json` | P7-008 | P7-002 (append), drill (reference) | LOW | Append-only; P7-008 semantics honored |
| Ingestion/media paths (`MediaUploadController`, `routes/web.php`, `bootstrap/app.php`) | P7-006 | P7-004 | LOW | No behavior change; regression cover |
| `docs/BACKUP-RESTORE-PROCEDURES.md` | P7-007 | Drill (executes), P7-002 (pg handoff) | LOW | Single-source preserved; drill follows it |
| Production diagnostics (`clamav:health`, `env:audit-limits`, `backup:*`) | P7-006/001/007 | P7-009 (consumes), P7-011 (adds purge signal) | LOW | Consume; additive only |

Rule: any Wave 3 implementation touching a HIGH/MEDIUM surface above
remains prohibited until Wave 2 review closure (DONE). Contract
authoring (done here) disturbs no evidence.

## H. Technical Debt Mapping

| TD | Status | Owner Decision | Implementation Owner | Verification Owner | Upstream Dependency | Terminal-Gate Consequence |
|---|---|---|---|---|---|---|
| TD-001 (HIGH/YES full-chain E2E) | OPEN (C/C2 partial evidence retained) | — (evidence rule, gate §Full chain) | P7-009 (deployed-stack run) | P7-012 G-12 | TD-002 + TD-003 resolved in deployed stack | G-12 fails without the P7-009 run |
| TD-002 (HIGH/YES 500 MiB limits) | OPEN | — | P7-001 (config/audit app share) + P7-009 (G-01 proof) | P7-012 G-01 | Deployed-stack topology (P7-008 DONE) | G-01 fails without retained multipart proof |
| TD-004 (CONDITIONAL Redis posture) | OPEN | HPO-04 posture still to be certified on target | P7-001 mechanism DONE-pending-review; certification at target run | P7-012 G-04 | Linux target availability | G-04 fails if passwordless outside loopback |
| TD-007 (MEDIUM/HPO_DECISION_REQUIRED staging cleanup) | OPEN (owner-policy RESOLVED via D7-06) | D7-06 DECIDED (30-day purge) | P7-011 (this Wave 3) | P7-012 G-09 | P7-004 VERIFIED (binding) | G-09 fails without purge + Option D outcome |
| TD-011 (LOW streaming gaps) | OPEN | D7-03 DECIDED (local backend = test target) | P7-004 (this Wave 3) | P7-012 G-05 | P7-006/P7-007 DONE (compat surfaces) | G-05 weak without direct tests |
| TD-005/006/013 (P7-010 DONE) | OPEN (evidence recorded; gate consumption pending) | D7-05 DECIDED | — (Wave 1 DONE) | P7-012 G-11 | — | Unchanged by Wave 3 |
| TD-003 (queue supervision) | OPEN (Wave 1 DONE; gate proof pending) | D7-02 DECIDED | — (Wave 1 DONE) | P7-012 G-03 | P7-001 target re-confirmation | P7-002 must re-prove fencing on pgsql |
| TD-008/009/010/012 | OPEN/ACCEPTED/MITIGATED as registered | — | — (out of Wave 3) | — | — | None (non-blocking) |

No debt item is closed, remediated, or marked VERIFIED by this preparation.

## I. Wave 2 Review Outcome Contingencies

### Branch A — Wave 2 fully VERIFIED (→ DONE)

Legally possible after HPO closure: reconcile Wave 3 contracts against
final Wave 2 DONE interfaces (same pattern as Wave 2's own reconciliation);
promote P7-002 + P7-004 to READY (explicit HPO act); confirm readiness;
authorize Wave 3 execution (possibly phased: P7-002/P7-004 first,
P7-011 after P7-004 VERIFIED, P7-009 run last, drill after P7-002 DONE).
P7-009 harness preparation may start under the same authorization; its
final run waits on P7-002 + P7-004 DONE.

### Branch B — Wave 2 CHANGES_REQUESTED

- Contracts remain valid as planning artifacts (all four assume only
  DONE interfaces, never Wave 2 internals), but §§5/9/16 of each must be
  reconciled against the corrected Wave 2 diff before any READY promotion.
- P7-004 (§9.4 quarantine/identity compat, §16 manifest-drift) is the
  most correction-sensitive (depends on P7-006/P7-007 exact surfaces).
- P7-002 (§13.AC7 registry flip, §9.7 dormant-path handoff) is sensitive
  to P7-001/P7-007 corrections.
- No candidate may progress to READY until the affected Wave 2 task is
  re-reviewed VERIFIED and closed DONE. P7-009/P7-011 (interface-only
  Wave 2 deps) are the most insulated but still wait for governance.

### Branch C — Wave 2 partial pass

- P7-001 VERIFIED, P7-006 fails, P7-007 VERIFIED: P7-002 is promotable
  (its Wave 2 deps satisfied); P7-004 is NOT (requires P7-006 DONE);
  P7-011/P7-009 unaffected at contract level but still gated on Wave 3
  internals; P7-006 corrective runs its own cycle (max 3, then BLOCKED).
- P7-001 fails, P7-006/P7-007 pass: P7-002 NOT promotable (registry flip
  mechanism unproven); P7-004 NOT promotable (capacity interface
  unproven); nothing Wave 3 moves to READY — P7-001 corrective is on the
  critical path for the entire wave.
- P7-007 fails, P7-001/P7-006 pass: P7-002 NOT promotable
  (backup-before-migrate hook unproven — fail-closed rule); P7-004
  promotable in principle (P7-006 + P7-001 DONE) subject to HPO
  subset-promotion decision; drill path waits regardless (needs P7-007 DONE).
- General rule: subset READY promotion is possible (Wave 1 precedent:
  per-task verdicts) but requires an explicit HPO subset decision; no
  automatic partial promotion.

No branch is authorized here.

## J. Proposed Wave 3 Execution Shape

`PREPARED EXECUTION PLAN — NOT AUTHORIZED` (no authority; HPO acts required):

- Candidate batch: P7-002 + P7-004 (first implementers) → P7-011 (after
  P7-004 VERIFIED) → P7-009 final run (after P7-002 + P7-004 DONE) →
  P7-007 drill (after P7-002 DONE) → P7-012 (separate terminal gate).
- Start gates: Wave 2 DONE (all three) for P7-002/P7-004; contract
  reconciliation against final Wave 2 DONE interfaces; per-task HPO READY
  promotion; readiness confirmation; explicit HPO Wave 3 execution
  authorization.
- Allowed parallelism: P7-002 ∥ P7-004 (disjoint primary surfaces;
  shared-surface sequencing on DeploymentVerify/runbook/`.env.example`
  — append-only, distinct blocks); P7-009 harness prep ∥ both.
- Sequencing: P7-002 and P7-004 may start together; P7-011 starts only
  after P7-004 VERIFIED (binding); P7-009 final run last; drill after
  P7-002 DONE (may overlap P7-011/P7-009-tail with HPO approval).
- Finish-order preference (not start gates): P7-004 before P7-011;
  P7-002 before drill; everything before P7-009 final run.
- Reviewer boundaries: per-task independent review (Claude Code) before
  any downstream promotion that names the task VERIFIED; reviewer sees
  stable Wave 3 diffs (same isolation rule as Wave 2).
- Required evidence: per-contract §17 bundles + retained
  `verification/p7-00X/` artifacts; dual-driver (P7-002), range-proof
  (P7-004), envelope report (P7-009), resume rehearsal (P7-011), drill
  log (P7-007 follow-on).
- Downstream gate: P7-012 (FINAL_GATE_ONLY; separate authorization; entry
  criteria per `docs/PRODUCTION_READINESS_GATE.md`).

## K. Findings

- INFO-1: Wave 3 is four implementers, not three — P7-009 corrected into
  scope. Non-blocking (caught in preparation, no rework needed).
- INFO-2: P7-011→P7-004-VERIFIED rule verified still binding (TD-007
  register entry + Wave 2 READY promotion basis). No new owner decision
  needed on this point.
- INFO-3: P7-007 drill needs no new task (contract §7 already defers it
  to Wave 3 with prerequisites); it DOES need a separate HPO drill
  authorization (Wave 2 auth was foundation-only).
- LOW-1: HIGH-overlap surfaces (DeploymentVerify, runbook, Deployment
  registry) will be edited by three Wave 3 tasks — append-only discipline
  + finish-order coordination required at execution time.
- LOW-2: P7-009 premature-run risk (final run before P7-002/P7-004 DONE
  presented as evidence) — prohibited in-contract (§5/§16), flagged for
  reviewer attention at execution.
- No BLOCKER / HIGH / MEDIUM findings in this preparation. No Wave 2
  review finding exists yet to reconcile.

## L. Authorization Boundary

`No Wave 3 implementation authorization was granted.`

`No Wave 3 production implementation was performed.`

No task was promoted to READY or IN_PROGRESS. No production code,
migration, configuration, or deployment artifact was created or modified
by this preparation (five new planning files only). No Wave 2 evidence
was altered. No debt item was closed.

## M. Exact Next Legal Action

**Wait for Wave 2 independent review verdict; reconcile any findings; close Wave 2 through HPO where legally permitted; then perform the required Wave 3 contract reconciliation / READY promotion / readiness confirmation / explicit execution authorization before any Wave 3 implementation begins.**
