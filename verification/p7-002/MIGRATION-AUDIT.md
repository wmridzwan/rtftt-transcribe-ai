# P7-002 Migration Audit — PostgreSQL Compatibility (2026-09-26)

Scope: all 30 migrations in `database/migrations/` reviewed for the
D7-01 self-hosted PostgreSQL target. Method: source inspection of every
migration file (patterns: raw statements, SQLite-only DML, enums,
renames, JSON columns, decimal/uuid/FK usage, index-name lengths).
Enforced in CI by `tests/Feature/Database/MigrationPgAuditTest.php`.

## Result: COMPATIBLE with one parity gap (closed by this task)

- Schema builder throughout; no `insertOrIgnore`/`orIgnore`, no
  `renameColumn`, no `->enum(` anywhere.
- `->json('logs')` (`2026_09_09_000005`, processing_jobs): Laravel maps
  `json` to PostgreSQL `json` — compatible.
- `decimal(12,3)` revision timing, `uuid` PKs/FKs, `foreignId`
  bigints, `unique`/`index` names (longest 38 chars, limit 63) —
  all compatible.
- `dropColumn` appears in `down()` methods only; PostgreSQL drops
  columns natively. Rollback path valid on both drivers.
- `DB::statement` appears ONLY in the two partial-index migrations
  below, both driver-gated.

## Parity gap (the single finding)

1. `2026_09_19_000002_add_active_attempt_unique_index_to_processing_jobs_table.php`
   creates `processing_jobs_active_attempt_unique`
   (`... WHERE status IN ('queued','running')`) for sqlite ONLY
   (early return for any other driver).
2. `2026_09_21_000003_add_active_target_unique_index_to_translations_table.php`
   creates `translations_active_target_unique`
   (`... WHERE status IN ('pending','queued','translating','completed')`)
   for sqlite ONLY.

Effect pre-P7-002: on PostgreSQL both defense-in-depth invariants lose
their database-level backstop (the CAS application guards remain
primary and unaffected).

Resolution: `2026_09_26_130000_add_pg_partial_unique_indexes.php`
(this task, pgsql-only, additive, historical migrations untouched)
creates both partial unique indexes on PostgreSQL. PostgreSQL
supports partial unique indexes and `IF NOT EXISTS` on
`CREATE UNIQUE INDEX`; `DROP INDEX IF EXISTS` is valid in `down()`.
Pinned in `deploy/migrations-inventory.json` by the owning task
(P7-008 test semantics honored).

## pg-specific notes

- `search_path=public`, `sslmode=prefer` default (env-overridable via
  `DB_SSLMODE`, registered advisory in the P7-001 registry, owner
  P7-002).
- `DB_PORT` default in `config/database.php` pgsql section is 5432;
  the commented `.env.example` mysql-era `3306` line is retained for
  the mysql driver only; the P7-002 pg guidance block documents the
  5432 target.

## Live-driver status (honest)

No PostgreSQL server exists in this environment (no service, no
binaries, no Docker); `migrate`/`rehearsal`/`rollback` against a live
pg instance are BLOCKED-ENVIRONMENT and recorded as such in
`reviews/P7-002-BUILDER-REPORT.md`. Substitute evidence supplied:
this audit + CI audit test + sqlite-executed reconciliation machinery
(`RevisionGraphIntegrity`, driver-agnostic, runs identically on pg)
+ config/flip/hook machinery tests. The target-host pg rehearsal
remains owed before P7-012 (G-02).
