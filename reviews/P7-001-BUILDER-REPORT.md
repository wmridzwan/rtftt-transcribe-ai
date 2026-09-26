# P7-001 Builder Report — Production Configuration + Env Validation

Task: `tasks/P7-001-production-configuration-env-validation.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Files changed (new unless noted)

- `app/Deployment/ProductionEnvRegistry.php` (new) — 71-key registry (requirement/shape/owner/description); ClamAV keys owned P7-006, backup keys P7-007; `validateValue()` shapes; `unregisteredExampleKeys()` CI gate.
- `app/Deployment/UploadLimitAudit.php` (new) — 600 MiB floor (500 MiB boundary + overhead), ini parsing (`-1` = unlimited), pure `evaluate()` with seams.
- `app/Deployment/RedisPosture.php` (new) — per-environment posture record; loopback-without-password recorded-not-violation; non-loopback passwordless fails closed; live `INFO server` version capture (assoc + line shapes).
- `app/Deployment/ProductionPostureChecks.php` (new) — additive `violations()` (upload adequacy + redis posture + storage capacity with `deployment.min_free_bytes` default 1 GiB) + production-only `assertValid()`.
- `app/Deployment/TargetHostEvidence.php` (new) — AC2/AC8 evidence schema validation, retention under `storage/app/target-evidence/`, `pending` until recorded.
- `app/Console/Commands/EnvAuditLimits.php` (new) — `env:audit-limits [--json]`; exit matches verdict.
- `app/Console/Commands/RecordTargetEvidence.php` (new) — `deployment:record-target-evidence {file}`; exit 0/1 pinned.
- `config/deployment.php` (new) — `min_free_bytes` (env `RTFTT_MIN_FREE_BYTES`, default 1 GiB; registered).
- `app/Providers/AppServiceProvider.php` (edit, +1 line) — boot `ProductionPostureChecks::assertValid()` alongside P7-008 guard; no verdict fork.
- `app/Console/Commands/DeploymentVerify.php` (edit, additive) — `checkPosture()` + `checkTargetHost()` sub-checks (advisory outside production, enforcing under production+`--strict`, mirroring `checkProductionMatrix`).
- `app/Console/Commands/ObservabilityDiagnostics.php` (edit, additive lines) — env/registry section (registry count, limits verdict, redis posture, target-evidence states); no P7-005 format altered.
- `app/Deployment/ProductionConfigGuard.php` (edit, +2 lines) — audit the refusal (key count only) before throwing; no rule changed.
- `docs/DEPLOYMENT-RUNBOOK.md` (edit, append §11) — P7-001-owned env-certification delta: boundary math, TD-004 table, rotation, AC2/AC8 target procedure.
- `.env.example` — untouched by P7-001 (registry covers existing keys; zero new P7-001 keys by design).
- Tests: `tests/Feature/Deployment/` 7 new files, 31 tests — registry (4), limits (5), redis posture (3+1 live, passes against loopback Redis 3.0), posture guards (6, incl. production-throw via temporary env switch), target evidence (7, incl. golden command outputs + JSON mechanics), secrets hygiene (3), verify posture extension (2).

No Horizon/object-storage/cluster content. No P7-003 queue key redefined; no P7-008 matrix entry duplicated. P7-006/P7-007 key names registered, owned by their tasks.

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 fail-fast boot per key | PASS | Posture tests (safe set clean; each violation class isolated; production throw; non-production no-op) |
| AC2 limit audit vs 600 MiB + overhead | PASS | Audit unit tests (512M/520M dev limits correctly FAIL; 700M/1G pass; `-1` passes; floor pinned 629145600); live `env:audit-limits --json` exits 1 on this dev box as designed |
| AC3 TD-004 certified per environment | PASS | Posture record tests (loopback recorded; non-loopback passwordless violation; authenticated accepted); live Redis 3.0 version captured in test (not skipped) |
| AC4 registry single-ownership | PASS | `.env.example` fully registered (caught real gap: CLAMAV_ENABLED added to registry during build); negative unregistered-key test |
| AC5 no secrets | PASS | `.env.example` secret-shaped lines unprovisioned; no base64/keys; app+config tree scanned for key material |
| AC6 verify extension, P7-008 checks unbroken | PASS | Posture/target sub-check tests; pre-existing DeploymentVerify tests green |
| AC7 standard gate | PASS | Full suite 1006/1005+1 pre-existing skip; Pint clean; PHPStan 0 |
| AC8 real-host carry-forward | **TARGET-PENDING (honest, per contract)** | Mechanism + procedure implemented and tested (schema, record/load, golden outputs, verify sub-check); no Linux/systemd host on this Windows dev box, so no real-host run exists — AC2 stays NOT PASS, AC8 unproven; both must pass before P7-012 |

## Test / verification matrix

- New P7-001 suites: 30/30 (incl. 1 live-Redis version capture).
- Full suite: 1006 tests, 1005 passed, 1 skipped (pre-existing FFprobe-unavailable guard), 0 failures, 3831 assertions; 4 warnings are pre-existing (none from new suites — verified by isolated runs).
- Note: 1 transient single-test error observed in 1 of 4 full runs, unreproduced across 3 reruns; consistent with carried TD-008 order-dependence; flagged, not hidden.
- Pint clean; PHPStan level 7: 0 errors.
- Live evidence: `env:audit-limits --json` (FAIL verdict on dev ini, exit 1), `deployment:verify` (posture/target advisory lines, gate passes), `observability:diagnostics` (env section renders).

## TD mapping

- TD-002: application share delivered (config + audit + directives); G-01 proof NOT claimed (P7-009 owns it).
- TD-004: certification mechanism + loopback-dev record delivered; production-topology certification awaits the Linux target (with AC8). NOT marked closed.
- Nothing marked closed.

## Known limitations (for reviewer)

1. AC8 real-host execution is target-host work (environmental TARGET-PENDING, substitute mechanism + procedure provided).
2. Redis version captured is the loopback dev server (3.0.504); production-server certification belongs to the target run.
3. `database/p7-006.sqlite` + `verification/p7-006*` residue on disk belongs to the P7-006 browser run (untracked verification material, per P7-010 precedent).

## HPO Accuracy Correction (closure round, 2026-09-26)

Independent review Finding F1: the registry holds **71** keys, not 70
as stated above (confirmed by live diagnostics output). Corrected
here; substance unaffected. No code changed by this correction.

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed. Task closed
DONE by HPO (`DECISION-P7-001-CLOSURE-001`, 2026-09-26, under
`DECISION-P7-001-AC8-DISPOSITION-001`).
