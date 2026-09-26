# Production Deployment Runbook (P7-008)

Status: AUTHORITATIVE for deployment mechanics. Binding posture: D7-01
self-hosted PostgreSQL (target, migrated by P7-002) · D7-02 Redis +
systemd, no Horizon · D7-03 local private storage · D7-08 single node,
single-admin. Hosts the P7-003 queue chapter by reference
(`docs/QUEUE-WORKER-SUPERVISION.md`) and the P7-007 backup hooks by
reference (mechanism owned by P7-007).

## 1. Topology (single node)

| Layer | Component | Notes |
|---|---|---|
| Web | PHP 8.4 + web server serving `public/` | Operator's web server; app requires `public/` docroot, never repo root |
| App | Laravel application at `/srv/rtftt-transcribe-ai/current` (release pointer, §5) | `APP_ENV=production`, `APP_DEBUG=false` |
| Queue | 2 supervised workers (transcription, translation) + scheduler timer | Units in `deploy/systemd/`; behavior specified by P7-003 |
| Cache/queue | Local Redis (loopback; §7 for posture) | No passwordless Redis outside loopback |
| Data | Self-hosted PostgreSQL (target; migrated by P7-002) | Pre-migration dev state is SQLite — never deploy SQLite to production |
| Files | Node-local private disk (`storage/app`, §6) | No object storage (D7-03 deferred) |

Startup order: PostgreSQL → Redis → web → workers → scheduler. Shutdown
is the reverse with worker drain first (P7-003 §3/§6).

## 2. Environment matrix (production)

| Variable | Required value | Enforced by |
|---|---|---|
| `APP_ENV` | `production` | `ProductionConfigGuard` (boot, production only) |
| `APP_DEBUG` | `false` | `ProductionConfigGuard` |
| `APP_KEY` | generated key | `ProductionConfigGuard` |
| `QUEUE_CONNECTION` | `redis` | `ProductionConfigGuard` + queue guards |
| `RTFTT_TRANSCRIPTION_QUEUE_CONNECTION` | `redis` (or empty → default) | `TranscriptionQueueConfig` |
| `RTFTT_TRANSLATION_QUEUE_CONNECTION` | `redis` (or empty → default) | `TranslationQueueConfig` |
| `REDIS_PASSWORD` | secret iff Redis off-loopback/shared (TD-004) | runbook checklist (§7) |
| `RTFTT_TRANSCRIPTION_WORKER_URL/TOKEN`, `RTFTT_TRANSLATION_WORKER_URL/TOKEN` | production worker endpoint + token (no `localhost` default) | `ProductionConfigGuard` |
| `DB_*` | production PostgreSQL DSN + credentials | deploy checklist |
| `LOG_CHANNEL` | `structured` (P7-005 JSON) recommended | ops preference |

`.env` files are permission-restricted (`600`, owner `rtftt`); secrets are
never committed, never logged, never pasted into tickets. Missing/unsafe
settings fail loudly at boot before traffic is served
(`ProductionConfigGuard::assertValid` + queue guards).

## 3. Install (first deploy)

1. Provision node: PHP 8.4 + required extensions, web server, Redis,
   PostgreSQL, supervisor (systemd).
2. Create `rtftt` user; clone release to `/srv/rtftt-transcribe-ai/releases/<timestamp>`; point `current` at it (§5).
3. `composer install --no-dev --optimize-autoloader`; `php artisan config:cache route:cache view:cache` (after env is final).
4. Set restrictive `.env` per §2; `storage/` owned by `rtftt`, web-writable.
5. Run `php artisan deployment:verify --strict` — must pass before traffic.
6. Install + enable units: `deploy/systemd/rtftt-queue-*.service`,
   `rtftt-scheduler.service/.timer` (adjust paths per file headers).
7. Smoke: upload → transcription → translation → workspace playback →
   export; `observability:diagnostics --probe-worker`.

## 4. Deploy (release) and verify

1. Build new release dir; point `current` at it only after steps 2–4 pass.
2. **Backup before migrate** (hook to P7-007 mechanism): SQLite-era —
   file copy of the database; PostgreSQL-era — `pg_dump`. Record the
   backup identity in the deploy log. Never migrate without it.
3. `php artisan migrate --force`; confirm `deployment:verify --strict`.
4. Swap `current`; restart workers (drain-aware: `queue:restart` after
   in-flight jobs finish within their timeout); reload web.
5. Readiness criteria (deploy is good iff ALL hold): migrations current;
   `deployment:verify --strict` green; both workers consuming (queue
   depth drains on a smoke job); scheduler ticking (schedule log shows
   both recovery commands); smoke upload→export path green.

## 5. Release layout and rollback

```
/srv/rtftt-transcribe-ai/
  releases/<timestamp>/   # immutable release dirs (keep last 3)
  current -> releases/<timestamp>/   # live pointer (symlink/junction)
  storage -> <persistent node-local dir>/   # survives releases
  .env    # single production env, outside releases
```

Rollback (inverse of §4, always available):
1. `php artisan down`; drain workers (P7-003 §6).
2. Repoint `current` at the prior release.
3. Database: if the deploy migrated, restore the pre-migrate backup
   (SQLite file swap) or run the P7-002-authored `migrate:rollback` plan
   — only via the documented plan, never hand-edited production data.
   If writes were served on the new schema, the pre-migrate backup +
   P7-002 rollback plan decision point applies (data mechanics owned by
   P7-002; this runbook documents the gate, not the SQL).
4. Restart workers + web; `deployment:verify --strict`; smoke; `up`.

## 6. Migration safety

- Historical migrations are immutable: `deploy/migrations-inventory.json`
  pins every migration's sha256 at P7-008; `MigrationInventoryTest`
  fails the suite if a pinned migration changes. New migrations (e.g.
  P7-002) are appended by their owning task with inventory updated in the
  same change.
- P5/P6 inventory reconciled read-only: 29 migrations at P7-008; no
  P5/P6 schema semantics altered by deployment.
- Dry-run evidence: `php artisan migrate --pretend` output retained per
  deploy (see P7-008 builder report for the verification-environment
  dry-run).
- Partial-deploy guard: boot refuses traffic while migrations are
  pending (`deployment:verify` migrations check; deploy order §4).

## 7. Upload reception — TD-002 deployment share (P7-008 scope)

Configure (values admit the 500 MiB product boundary plus overhead; the
retained 500 MiB multipart proof belongs to P7-001/P7-009, not this task):

- PHP: `upload_max_filesize=600M`, `post_max_size=600M`,
  `max_execution_time=600`, `max_input_time=600`,
  `upload_tmp_dir` on a volume with ≥2 GiB free.
- Proxy/web: `client_max_body_size 600M` (nginx) or equivalent;
  request timeout ≥600s on the upload route.
- Temp + durable capacity: `upload_tmp_dir` and `storage/app` sized for
  concurrent 500 MiB receptions within the D7-08 single-admin envelope.

## 8. Private storage assumptions (D7-03)

`storage/app` (media, prepared audio, derived artifacts) is node-local:
owner `rtftt`, mode `750` dirs / `640` files, deny other system users;
capacity planned per §7 + retention purge (P7-011 implements; this task
documents the mount). Backup interaction: files are captured by the
P7-007 mechanism (hook, not implementation, here).

## 9. Failure triage

| Signal | First action |
|---|---|
| `deployment:verify --strict` red | Read the failing check; §4 inverse if mid-deploy |
| Workers absent | `systemctl status rtftt-queue-*`; journal; restart; failed-job review (P7-003 §5) |
| Queue depth grows, no completions | Worker logs → provider reachability (`--probe-worker`) → Redis (`PING`) → P7-003 §7 |
| Migrations pending at boot | Finish or roll back the deploy (§5); never serve traffic half-migrated |
| 500 MiB uploads rejected | §7 directives audit; proxy vs PHP limit isolation; escalate proof to P7-001/P7-009 |

## 10. P7-003 chapter (by reference)

Queue supervision, restart/recovery, failed-job handling, drain/rollback,
and Redis outage triage: `docs/QUEUE-WORKER-SUPERVISION.md`
(spec revision consumed by this runbook's units: 2026-09-26). P7-008
installs what that spec specifies; behavior questions belong to P7-003.

## 11. P7-001 environment certification (P7-001-owned delta)

Single boot verdict, two layers: P7-008 `ProductionConfigGuard`
(deployment matrix) + P7-001 `ProductionPostureChecks` (Redis posture,
upload-limit adequacy, storage capacity). Exactly one refusal path;
P7-001 never forks the verdict.

| Check | Command | Production behavior |
|---|---|---|
| Limit adequacy | `env:audit-limits [--json]` | FAIL below 600 MiB floor (500 MiB boundary + overhead); JSON feeds the P7-009 harness |
| Full deploy gate | `deployment:verify --strict` | Non-zero on any violation, incl. posture and (in production) pending target evidence |
| Target evidence | `deployment:record-target-evidence <file>` | Validates + retains AC2/AC8 real-host evidence JSON |

Boundary math: 500 MiB product boundary + ~100 MiB multipart/proxy
headroom = 600 MiB floor (`UploadLimitAudit::REQUIRED_BYTES`).

TD-004 posture: loopback Redis without a password is acceptable for
dev only; any non-loopback Redis without a password fails the posture
check in every environment. Redis version is captured live during
certification (`RedisPosture::captureVersion`) and recorded redacted.

Secrets: no secret in the repo, logs, or retained evidence; rotation =
rotate at the source, update the host env, restart workers drained
(per P7-003 �6), re-run `deployment:verify --strict`.

Real-host carry-forward (binding, pre-P7-012): on the Linux target,
execute the AC2 reboot-cycle verification (units enabled at boot,
workers resume, scheduler timer active) and the AC8 SIGTERM-drain
re-confirmation against a real supervised worker; record both via
`deployment:record-target-evidence`. AC2 stays NOT PASS until that
evidence exists. A FAIL routes through governance to the originating
task � P7-001 never silently fixes Wave 1 code.

## 12. P7-006 security operations (by reference)

Baseline owned by P7-006: `docs/SECURITY-BASELINE.md` (headers, CSP
rollout, throttles, abuse limits, ClamAV scanning/quarantine/recovery,
dependency audit, secrets hygiene, audit logging). This runbook
references it; behavior questions belong to P7-006. Host duties:
ClamAV daemon install/enable, freshclam scheduling, socket
permissions, signature-age monitoring via scheduled `clamav:health`.

## 13. P7-007 backup hooks (by reference)

Mechanism owned by P7-007: `docs/BACKUP-RESTORE-PROCEDURES.md`
(daily `backup:run --driver=sqlite`, integrity verification,
pruning, disaster ordering, dormant pg path, retention interaction).
This runbook references it; procedure text lives once. "Backup before
migrate" invokes `backup:pre-migrate` (exit 0 = proceed, exit 1 =
stop). The executed restore drill stays deferred to Wave 3
(post-P7-002); no drill step here satisfies G-08.

## 14. P7-002 datastore cutover (by reference)

Datastore mechanics owned by P7-002: docs/DATASTORE-MIGRATION.md (ordering, reconciliation, resume/rollback, cutover checklist, stale-authority discipline). Audit: verification/p7-002/MIGRATION-AUDIT.md. Pre-migrate gate is backup:pre-migrate (exit 0 = proceed, exit 1 = stop; see section 13). Post-cutover DB_CONNECTION=pgsql makes the pg variables required (DatastorePosture fail-closed); sqlite stays legal only pre-cutover. The pg parity migration 2026_09_26_130000 is pinned in deploy/migrations-inventory.json. The final restore drill is NOT part of cutover (P7-007 Wave 3 follow-on, separate HPO authorization; G-08 unclaimed).


## 15. P7-004 local storage topology (by reference)

Topology truth owned by P7-004: docs/LOCAL-STORAGE-TOPOLOGY.md (local private disk, opaque identities, streaming/range contract, zero-byte disposition, durability limits, capacity floor, degraded behavior, P7-009 handoff). Validate with storage:validate-topology (exit 0/1); deployment:verify carries additive Topology lines; diagnostics carries the Storage disk/root section. Quarantine stays out of the servable and backup trees. No object storage exists anywhere in this deployment (D7-03); S3/MinIO/R2 content in a future diff would contradict the adopted posture.


## 16. P7-011 retention purge (by reference)

Retention mechanics owned by P7-011: docs/RETENTION-POLICY.md (user-facing disclosure), task contract tasks/P7-011-retention-cleanup.md (clock table, purge boundary, failure semantics). Daily retention:purge --dry-run available for operator review; live run tombstones rows and deletes bytes, never hard-deletes history. Verify via deployment:verify Retention lines and observability:diagnostics retention ledger. Quarantine and backup generations are never purge targets.

