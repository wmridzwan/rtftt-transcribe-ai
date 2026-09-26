# P7-008 Builder Report — Deployment, Migration Safety, Rollback

Task: `tasks/P7-008-deployment-migration-safety-rollback.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Files changed (new unless noted)

- `app/Deployment/ProductionConfigGuard.php` (new) — production env-matrix guard (debug, redis default, app key, worker URLs/tokens); enforced at boot in production only.
- `app/Providers/AppServiceProvider.php` (edit) — boot `ProductionConfigGuard::assertValid()` (no-op outside production).
- `app/Console/Commands/DeploymentVerify.php` (new) — `deployment:verify [--strict]`: migrations current, both-queue guards, production matrix (advisory outside production), both recovery schedules, storage writability.
- `deploy/systemd/rtftt-queue-transcription.service`, `rtftt-queue-translation.service`, `rtftt-scheduler.service`, `rtftt-scheduler.timer` (new) — install the P7-003 spec (revision pinned 2026-09-26): `--timeout=330` = job timeout, `TimeoutStopSec=420` ≥ 390 budget, `Restart=always`, enable-at-boot.
- `deploy/migrations-inventory.json` (new) — 29 migrations pinned by sha256 at P7-008.
- `docs/DEPLOYMENT-RUNBOOK.md` (new) — topology, env matrix, install/deploy/verify/rollback, migration safety, TD-002 directives (600M), storage assumptions, triage; hosts P7-003 §10 by reference + P7-007 hooks by reference.
- `.env.example` (edit) — P7-008 production block appended (references, never redefines, P7-003 queue keys).
- Tests: `tests/Feature/Deployment/ProductionConfigGuardTest.php` (6), `MigrationInventoryTest.php` (2), `DeploymentVerifyTest.php` (3).

Consumed P7-003 supervision spec as authored (no P7-003 spec change occurred during this batch, so no reconciliation delta). No P7-002 content authored; no P7-007 mechanism; no P7-001 proof; no P7-009 harness.

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 topology complete | PASS | Runbook §1 (processes, ports, order, node-local assumptions) |
| AC2 units installed, enabled, reboot-verified | BLOCKED (environmental) | Substitute evidence: all `ExecStart` flags validated against `queue:work --help` (`--queue/--sleep/--tries/--timeout/--max-time/--max-jobs` all exist); `TimeoutStopSec`/restart/enable directives reviewed against P7-003 spec; reboot execution impossible on this Windows dev machine (no systemd) — deferred to the target Linux host, explicitly flagged for reviewer |
| AC3 unsafe settings fail loudly (debug, queue, secrets) | PASS | Guard unit tests (6); boot hook production-gated |
| AC4 deploy→verify→rollback cycle + readiness green | PASS (dry-run) | `deployment:verify` green (tests); `migrate:status` all Ran + `migrate --pretend` "Nothing to migrate" recorded; release-pointer deploy/rollback mechanics demonstrated (junction v1→v2→v1 with content proof); readiness criteria documented + command-supported |
| AC5 migration dry-run, history untouched, inventory reconciled | PASS | Manifest (29 pinned) + inventory tests (2); `--pretend` output; P5/P6 untouched (reconciled read-only) |
| AC6 TD-002 directives applied in verification env | PASS | Runbook §7 (600M directives + capacity notes); application-level proof stays with P7-001/P7-009 per contract |
| AC7 runbook executed once | PASS (dry-run) | Install/deploy/rollback/triage procedures walked against verification env where executable (verify command, migration checks, pointer mechanics); target-only steps (systemd enable, reboot) marked as such |
| AC8 boundary audit, no Horizon/clustering/absorption | PASS | Diff audit: P7-002/P7-003/P7-001 referenced, not owned; no Horizon/object-storage/cluster content |
| AC9 suites green, Pint, PHPStan, no P6 regression | PASS | Full suite 927/926+1 pre-existing skip; Pint clean; PHPStan 0 |

## Test / verification matrix

- `tests/Feature/Deployment/` 11/11. Full suite + Pint + PHPStan as above.

## TD mapping

- TD-002: deployment share delivered (directives). G-01 proof NOT claimed (P7-001/P7-009 own it).
- TD-001: informed only. TD-003/004: consumed, remediated in P7-003. Nothing marked closed.

## Known limitations (for reviewer)

1. AC2 reboot/install execution is target-host work (environmental BLOCKED, substitute evidence provided).
2. PostgreSQL-era backup hook (`pg_dump`) is documented interfacing P7-007; only the SQLite-era copy path is directly exercisable here.

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed.
