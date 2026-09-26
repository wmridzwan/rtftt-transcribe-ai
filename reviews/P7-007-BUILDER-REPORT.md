# P7-007 Builder Report — Backup / Restore Foundation

Task: `tasks/P7-007-backup-restore-foundation.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Files changed (new unless noted)

- `app/Backup/BackupManager.php` (new) — SQLite-era mechanism: quiesce-probed snapshot, media-tree mirror (quarantine excluded), manifest (timestamp/driver/tool/db-sha/media-manifest+root/inventory pin) + root hash, scratch-restore integrity (`verifySet`: manifest chain + inventory pin + database-copy open + migrations table), inventory reconciliation with temp-path seams, generation pruning with last-good protection, `latestManifest`/`listSets`/`setStatus`.
- `app/Console/Commands/BackupRun.php` (new) — `backup:run --driver=` (required, explicit); sqlite executes, pgsql refuses with P7-002 message, unknown refused; exit 0/1.
- `app/Console/Commands/BackupPreMigrate.php` (new) — `backup:pre-migrate` hook with pinned goldens (`PRE-MIGRATE BACKUP OK` / `PRE-MIGRATE BACKUP REFUSED`).
- `config/backup.php` (new) — target (env `RTFTT_BACKUP_TARGET`, empty-safe fallback), generations (7), stale threshold (26h).
- `app/Providers/AppServiceProvider.php` (edit) — `BackupManager` container binding (media-disk adapter not auto-wirable).
- `routes/console.php` (edit) — daily `backup:run --driver=sqlite` with overlap protection.
- `app/Console/Commands/DeploymentVerify.php` (edit, additive) — `checkBackups()` stale-manifest signal (advisory outside production, enforcing under production+`--strict` via shared `productionOnlyFail` helper).
- `app/Console/Commands/ObservabilityDiagnostics.php` (edit, additive) — backup section (target writability, schedule presence, last-good name/status).
- `.env.example` (edit, append-only) — `RTFTT_BACKUP_TARGET=` + `RTFTT_BACKUP_GENERATIONS=7`; no P7-003/P7-008/P7-006 key touched.
- `docs/BACKUP-RESTORE-PROCEDURES.md` (new) — single-source procedures (operation, hook, triage, disaster ordering, dormant pg path, retention interaction, data-protection posture with explicit unencrypted-at-rest residual risk).
- docs runbook §13 reference append (P7-008 owns file; no forked copy).
- Tests: `tests/Feature/Backup/` 2 files, 14 tests — manifest-valid success + integrity, quarantine exclusion, non-file-source refusal, unknown/pgsql-dormant refusal, explicit-driver requirement, tamper detection, pruning generations, newer-failed-set retention, never-prune-without-good, live inventory reconciliation + fabricated divergence/unpinned (temp copies; repo files untouched), hook goldens both paths, verify/diagnostics presence, credential redaction.

No Horizon/object-storage/cluster content. No P7-002 migration content; no pg mechanism executes (dormant-refusal test permanent in CI).

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 manifest-valid set + integrity, loud failures | PASS | Success test (manifest + 1-file media + latestManifest ok); failure modes covered by test: non-file source refusal, unknown/dormant-driver refusal, missing-driver refusal, tampered-manifest detection. [HPO-corrected 2026-09-26 per review F6: the "unwritable target via seam" test claimed here does not exist; the `ensureDirectory()` error path is real but untested — follow-up recorded, non-blocking; no code changed by this correction.] |
| AC2 pg dormant with P7-002 refusal | PASS | `run('pgsql')` refuses with activation message (test-asserted) |
| AC3 hooks with pinned exits + goldens | PASS | pre-migrate OK (exit 0) and REFUSED (exit 1) tests; wording matches final runbook hooks |
| AC4 retention math + pruning + last-good | PASS | Generation pruning, newer-failed retention, never-prune-without-good tests; capacity math documented |
| AC5 inventory reconciliation enforced | PASS | Live-tree reconciled test + fabricated divergence/unpinned tests; backup refuses on divergence |
| AC6 single-sourced procedures | PASS | `BACKUP-RESTORE-PROCEDURES.md` + runbook reference-only append (diff audit) |
| AC7 drill NOT executed/claimed | PASS | No drill code path exists beyond the dormant refusal; Wave 3 prerequisites documented in contract + procedures |
| AC8 standard gate | PASS | Full suite 1006/1005+1 pre-existing skip; Pint clean; PHPStan 0; no Wave 1 file semantically altered |

## Test / verification matrix

- New P7-007 suites: 14/14 (temp-file sqlite sources; RefreshDatabase untouched — manager uses raw PDO + files only).
- Scratch-restore integrity runs for real in-test (temp fixture sets), not mocked.
- Full suite + Pint + PHPStan shared with Wave 2 batch numbers above.

## TD mapping

- No TD item closes under foundation scope; G-08 drill evidence is the Wave 3 close-out. Consumes supervision conventions + P7-008 inventory; owns no remediation. Nothing marked closed.

## Known limitations (for reviewer)

1. SQLite quiesce is a best-effort `BEGIN IMMEDIATE` probe, not a lock manager; adequate for the single-admin model, documented.
2. Media manifest hashes every file per run (correctness over speed for single-admin scale; P7-009 may revisit for large trees).
3. At-rest encryption deferred by contract (residual risk recorded in procedures, not silently accepted).

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed.
