# PostgreSQL Data Migration — Procedure, Cutover & Rollback (P7-002)

Binding: D7-01 self-hosted PostgreSQL. This document owns the data
mechanics; P7-008 owns deployment mechanics; P7-007 owns the backup
mechanism. The executed restore drill is NOT here (P7-007 Wave 3
follow-on, separately HPO-gated after P7-002 DONE).

## 1. Migration ordering

1. Provision the PostgreSQL instance (version recorded in the rehearsal
   log); create role + database; apply `DB_SSLMODE`.
2. Run `backup:pre-migrate` — exit 0 (`PRE-MIGRATE BACKUP OK`) proceeds;
   exit 1 (`PRE-MIGRATE BACKUP REFUSED`) stops. No bypass.
3. Run `php artisan migrate --database=pgsql` (new migrations only;
   historical migrations frozen; the pg parity migration
   `2026_09_26_130000` creates the two partial unique indexes).
4. Data load in dependency order: users → media_files → transcriptions
   (+ segments, processing_jobs) → translations (+ segments) →
   transcript_revisions → transcript_revision_segments. Preserve primary
   keys (uuid and integer ids are carried verbatim so FK/ancestry links
   survive); sequences are not reset behind the load.
5. Run `RevisionGraphIntegrity::reconcile('pgsql')` (counts +
   orphan/duplicate/ancestry checks) and compare against the
   pre-migration sqlite reconciliation. Any violation → stop;
   resume-or-rollback decision, never silent continuation.
6. Cutover: set `DB_CONNECTION=pgsql` (DatastorePosture flips the pg
   variables from advisory to required; boot fails closed when they are
   invalid). Verify with `deployment:verify --strict` (datastore
   sub-check green) and `observability:diagnostics` (Datastore driver:
   pgsql, posture ok).
7. Stale-authority discipline: after cutover no writer may target the
   old sqlite file. The sqlite file is retained read-only as the
   rollback source until the rollback window closes, then archived per
   the retention policy (P7-011 owns purge; this task never deletes it).

## 2. Reconciliation queries (also CI-executed on sqlite)

`App\Deployment\RevisionGraphIntegrity::reconcile($connection)`:
table counts + orphan revisions + orphan parents + bad
`active_revision_id` + duplicate `(transcription_id, version)` +
orphan segments. Driver-agnostic; identical logic on both stores.

## 3. Failure / partial-migration handling

- Every phase checkpoints (backup set → schema migrated → data loaded
  → reconciled → cutover). A failed phase leaves the previous store
  authoritative; the new store is either resumed (safe re-probe:
  re-run reconciliation first) or discarded and rebuilt.
- A failed reconciliation blocks cutover absolutely.
- Rollback rehearsal: cutover → `DB_CONNECTION=sqlite` restore →
  integrity re-check → resume-or-escalate. Rollback is a decision
  point owned here, executed through P7-008's rollback procedure.

## 4. Cutover checklist

- [ ] `backup:pre-migrate` exit 0 (golden `PRE-MIGRATE BACKUP OK`)
- [ ] `migrate --database=pgsql` clean (log retained)
- [ ] Data load complete in §1 order (row counts recorded)
- [ ] `reconcile('pgsql')` zero violations; counts match sqlite baseline
- [ ] `deployment:verify --strict` green on the pg configuration
- [ ] Phase 6 revision/export regression green on pgsql (AC5)
- [ ] Two-process claim-fencing suites green on pgsql (AC6)
- [ ] Cutover (`DB_CONNECTION=pgsql`) + stale-authority guard recorded
- [ ] Rollback rehearsal log retained (AC4)

## 5. Environment note

Live pg rehearsal/rollback require the production-version PostgreSQL
target host, unavailable in CI/dev (recorded BLOCKED-ENVIRONMENT in
the builder report). The procedure above is executed there before
P7-012 (G-02); nothing here substitutes the gate run.

## 6. Isolated local-PG verification runbook (HIGH-D prepared path)

For the first host with a real PostgreSQL server (Linux target, or
a dev box with user-space binaries): disposable database only, never
production/customer data.

1. Record `SELECT version();`, host/port, role, app commit hash.
2. `CREATE DATABASE rtftt_pg_verify OWNER <role>;` (drop it at the end).
3. Point a shell at it (`DB_CONNECTION=pgsql DB_HOST=… DB_PORT=…
   DB_DATABASE=rtftt_pg_verify DB_USERNAME=… DB_PASSWORD=…` —
   secrets in env only, never in retained logs):
   - AC1: `php artisan migrate --database=pgsql` clean; full Pest
     suite with `DB_CONNECTION=pgsql` green (record counts).
   - AC2: migration-rehearsal reconciliation per §1 steps 2–5
     (pre-migrate hook exit 0, data load, `reconcile('pgsql')`
     zero violations vs sqlite baseline).
   - AC5: Phase 6 revision/export regression files green on pgsql
     (`tests/Feature/Editing`, `TranscriptRevisionAwareExportTest`).
   - AC6: two-process claim-fencing suites green on pgsql
     (transcription/translation/revision claim + retry race tests;
     race workers use `RaceConnectionPolicy` — no PRAGMA on pgsql).
   - Partial indexes: `\d processing_jobs` /
     `\d translations` show the two `*_active_*_unique` partial
     unique indexes from migration `2026_09_26_130000`.
   - Locking: concurrent duplicate-claim attempts collapse to one
     winner (AC6 evidence covers this; record the sentinel JSON).
4. PG backup E2E (BLOCKER-B live proof): seed representative rows,
   `backup:run --driver=pgsql` exit 0, `database.dump` present with
   `PGDMP` magic, manifest `driver: pgsql`, `verifySet` ok;
   corrupt one byte → verify fails; prune keeps generations +
   last-good.
5. Cleanup: `DROP DATABASE rtftt_pg_verify;` remove sets created
   under the disposable target. Retain version/config (no
   secrets)/commands/results in the verification log; do NOT claim
   the P7-007 drill (separately HPO-authorized, G-08).
