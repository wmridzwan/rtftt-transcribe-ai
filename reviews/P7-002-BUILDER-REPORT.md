# P7-002 Builder Report — Production Data Store Finalization + Migration

Task: `tasks/P7-002-production-datastore-migration.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Baseline

- HEAD: `4d90d5b`; branch `main`; 121 dirty paths at start (prior-wave + Phase 6 residue, preserved).
- No live PostgreSQL in this environment (no service, binaries, or Docker; PDO `pgsql` driver present but no server). Live-pg checks are BLOCKED-ENVIRONMENT per the contract's environment rule — no PASS manufactured.

## Files changed (new unless noted)

- `database/migrations/2026_09_26_130000_add_pg_partial_unique_indexes.php` (new) — pgsql-only parity for the two sqlite-gated partial unique indexes (`processing_jobs_active_attempt_unique`, `translations_active_target_unique`); no-op on other drivers; historical migrations untouched.
- `app/Deployment/DatastorePosture.php` (new) — the D7-01 flip: sqlite legal pre-cutover; pgsql requires host/port/database/username/password; other drivers fail closed. Pure logic, config-resolved (PHPStan-clean, no `env()` outside config).
- `app/Deployment/RevisionGraphIntegrity.php` (new) — driver-agnostic pre/post-migration reconciliation (counts + orphan revisions/parents/segments + bad active pointers + duplicate versions).
- `app/Deployment/ProductionPostureChecks.php` (edit, additive) — one block appending `DatastorePosture::evaluate(null)`; P7-001 rules untouched.
- `app/Console/Commands/DeploymentVerify.php` (edit, additive) — `checkDatastore()` sub-check (advisory outside production, enforcing under production+`--strict`); P7-008 checks unbroken.
- `app/Console/Commands/ObservabilityDiagnostics.php` (edit, additive) — `Datastore driver:` line; no P7-005 format altered.
- `app/Deployment/ProductionEnvRegistry.php` (edit, additive) — registered `DB_SSLMODE` (advisory, owner P7-002; required because config + `.env.example` reference it).
- `.env.example` (edit, P7-002-owned DB section) — pg-target guidance block + commented `DB_SSLMODE`; no provisioned secret; registry-integrity gate stays green.
- `deploy/migrations-inventory.json` (edit, append-only) — new migration pinned by hash + note update; no recorded entry changed.
- `docs/DATASTORE-MIGRATION.md` (new) — ordering, reconciliation, resume/rollback, cutover checklist, stale-authority discipline.
- `docs/DEPLOYMENT-RUNBOOK.md` (edit, append §14 by reference; P7-008 owns file).
- `verification/p7-002/MIGRATION-AUDIT.md` (new) + retained `p7-002-verify-output.txt`, `p7-002-audit-limits.json`.
- Tests: `tests/Feature/Database/` 5 files, 31 tests — posture flip (7), reconciliation incl. FK-free drift connection (6), registry flip (5), verify/hook/secrets (4), CI migration audit (4).
- `tests/Feature/Editing/RevisionMigrationRollbackTest.php` (edit) — `--step` 6→7 (newest migration is P7-002's; its sqlite `down()` is a deliberate no-op) + P7-002 note; P6/P7-006 assertions unchanged (P7-006 precedent).

No Horizon/object-storage/cluster content. No P7-004 storage change. No P7-009/P7-011/P7-007-drill/P7-012 content.

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 dual-driver suite green | **BLOCKED-ENVIRONMENT (pg half)** / PASS (sqlite half) | sqlite: full suite green (runs 2–3); pg: no server exists — config/flip/hook/audit machinery tested, live pg run owed pre-P7-012 |
| AC2 migration rehearsal + reconciliation | **BLOCKED-ENVIRONMENT (pg rehearsal)** / PASS (machinery) | `RevisionGraphIntegrity` green on sqlite incl. 5 corruption classes; procedure + checklist documented |
| AC3 pre-migrate OK gate | PASS | REFUSED path asserted (no fresh set); OK path owned by P7-007 suites |
| AC4 rollback rehearsal | **BLOCKED-ENVIRONMENT (pg)** / PASS (mechanism) | sqlite rollback `--step 7` green; pg rollback rehearsal owed on target |
| AC5 Phase 6 revision/export on pgsql | **BLOCKED-ENVIRONMENT** | sqlite revision/export suites green in full runs; pg run owed on target |
| AC6 claim-fencing on pgsql | **BLOCKED-ENVIRONMENT** | CAS suites green on sqlite; pg run owed on target |
| AC7 registry flip, no fork | PASS | Flip tests (advisory static + enforced-via-posture); pre-cutover sqlite legal |
| AC8 standard gate | PASS | Full suite 1050/1049+1 pre-existing skip (runs 2–3); Pint clean; PHPStan 0; Wave 2 files only additively touched |
| AC9 no G-02 claim | PASS | No gate verdict anywhere in diff/docs |

## Test / verification matrix

- New P7-002 suites: 31/31 (incl. drift-connection corruption matrix).
- Full suite run 1: 1050 tests, 1048 passed, 1 failed (rollback step — deterministic Wave 3A interaction, fixed in-scope, re-green), 1 pre-existing skip, 4 pre-existing warnings. Runs 2–3: 1050/1049+1 skip, 0 failures, 3972 assertions. No TD-008 flake observed across 3 runs (honest: TD-008 stays OPEN regardless).
- Pint clean; PHPStan level 7: 0 errors (one genuine `env()`-outside-config finding fixed properly via resolved config).
- Live evidence: `deployment:verify` (Datastore ok sqlite), `env:audit-limits --json`, `observability:diagnostics` (Datastore line) — retained.

## TD mapping

- TD-003/TD-004: consumed only (queue posture unchanged; fencing re-proof on pg owed). TD-007/Option D: untouched. TD-011: untouched (P7-004).
- TD-008: encountered zero flakes in 3 runs; stays OPEN/MEDIUM/pre-P7-012; nothing resolved by this task.
- Nothing marked closed.

## Known limitations (for reviewer)

1. Every live-pg check is TARGET-PENDING (environmental BLOCKED-ENVIRONMENT with substitute mechanism + procedure, mirroring the Wave 1/2 precedent).
2. Migration-audit `down()` on pg is reviewed, not executed (no server).
3. `database/p7-006.sqlite` residue on disk belongs to the P7-006 browser run (untracked, per precedent).

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed.
