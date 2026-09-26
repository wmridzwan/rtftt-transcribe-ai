# Backup / Restore Procedures (P7-007 Foundation)

Binding posture: D7-07 daily backups + documented procedures + one
executed restore drill (drill deferred to Wave 3, post-P7-002); D7-01
PostgreSQL target; D7-03 node-local storage. This document is the
single source for backup/restore procedure text; the deployment
runbook references it (never a forked copy).

## 1. Daily operation

`backup:run --driver=sqlite` (scheduled daily, `withoutOverlapping`):
snapshots the SQLite store after a quiesce probe, mirrors the media
tree (quarantine excluded), writes `manifest.json` (timestamp, driver,
tool version, database sha256, media manifest + root, migration
inventory pin), then runs scratch-restore integrity verification
(manifest chain + inventory pin + database-copy open + migrations
table). Exit 0 = manifest-valid set; exit 1 = quarantined partial
set, never presented as valid.

## 2. Pre-migration hook

`backup:pre-migrate` (the exact command the runbook's "Backup before
migrate" step invokes): pinned outputs `PRE-MIGRATE BACKUP OK` (exit
0, migration may proceed) or `PRE-MIGRATE BACKUP REFUSED` (exit 1,
migration must not proceed).

## 3. Integrity-failure triage

| Signal | First action |
|---|---|
| `backup FAILED` / quarantined set | Read manifest `failure` list; fix cause (lock, target, checksum); re-run; pruning stays blocked until a good set lands |
| Manifest root mismatch | Treat the set as tampered; do not restore; investigate host integrity |
| Inventory diverged | Owning migration task must reconcile `deploy/migrations-inventory.json`; backups refuse until then |
| Stale set in `deployment:verify` | Re-run `backup:run`; missed daily runs page the operator path |

## 4. Disaster-restore ordering (Wave 3 executes; documented now)

Datastore → media → verify (`verifySet` semantics) → supervise
(workers/scheduler per P7-003 spec) → readiness (`deployment:verify
--strict`). Partial-set restores are refused by manifest design
(all-or-nothing per set). Restore invocation is access-controlled
(single-admin operator) and audit-logged.

## 5. PostgreSQL-native path (dormant until P7-002)

`backup:run --driver=pgsql` refuses with the P7-002 activation
message (test-asserted, permanent until P7-002). Post-migration shape:
`pg_dump` with role-based credentials (never in repo/logs), restore
via `pg_restore`/`psql`, same manifest discipline. PITR/RPO
tightening (D7-07 options B/C) is deferred, not precluded.

## 6. Retention-of-backups vs D7-06 purge

Backup generations (default 7 daily sets) age out by pruning with
manifest updates; pruning never orphans the chain and never deletes
the last good set. Backups of purged user data age out per the
backup-retention window — backups are not a retention bypass, and
purge itself is owned by P7-011. Capacity math (single-admin):
generations × (database + media tree) must fit node-local disk with
margin; re-check after any 10× media growth.

## 7. Data protection posture

Backup sets contain full user data. Minimum posture: target
directory mode `750`, files `640`, operator-only access list
documented per host. At-rest encryption is adopted only by a
follow-on HPO decision — the residual risk is recorded here
explicitly, not silently accepted. Credentials for backup targets
never appear in repo, logs, manifests, or retained evidence
(redaction-tested).
