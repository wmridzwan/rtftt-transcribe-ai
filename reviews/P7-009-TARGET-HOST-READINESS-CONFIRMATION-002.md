# P7-009 Target-Host Readiness Confirmation — Review 002 (Linux VPS)

Role: independent readiness reviewer (Claude Code). READ-ONLY. No code, config,
migration, or production change; no P7-009 Phase B authorization; no P7-012
action; no task-state promotion; no production-readiness claim.

Date: 2026-09-30 (host clock 16:14 UTC). Repo source of truth: local checkout,
HEAD `83b666d`. Host evidence: `ssh rtftt-vps` as `deploy` (non-root, sudo needs
a password → not usable), read-only commands only. Supersedes nothing;
follows `reviews/P7-009-TARGET-HOST-READINESS.md` (2026-09-26,
`TARGET_HOST_NOT_READY`, Windows substitute host).

Evidence legend: [H] = command I ran on the host this session; [R] = repo
file read this session; [C] = claimed by an operator artifact, NOT reproduced by me.

## 1. Canonical governance baseline [R]

- P7-009: `IN_PROGRESS — Phase A only` (`tasks/P7-009-performance-load-validation.md`
  Status; `CURRENT_STATE.md:26,334-341`; `AGENTS.md:222`). Phase A executed under
  `DECISION-P7-009-PHASE-A-EXECUTION-AUTHORIZATION-001` (`DECISION_QUEUE.md:6165+`).
- Phase B: **NOT AUTHORIZED** (`CURRENT_STATE.md:29`, `:334-341`). Preconditions
  (`DECISION_QUEUE.md:6223-6226`): (1) production-shaped host exists,
  (2) characteristics recorded, (3) separate target-host readiness confirmation,
  (4) explicit HPO Phase B authorization. Contract §5/§19 additionally: P7-002,
  P7-004 DONE (both DONE per `CURRENT_STATE.md`), READY→execution only via HPO.
- P7-012: `FINAL_GATE_ONLY, NOT AUTHORIZED`; no `tasks/P7-012*` file exists
  (`ls tasks | grep 012` empty); gate inventory is `docs/PRODUCTION_READINESS_GATE.md`
  (G-01..G-13; G-01/G-10/G-12 consume P7-009).
- Other open items: P7-007 restore drill needs separate HPO authorization;
  TD-008/TD-014 BACKLOG, NOT AUTHORIZED; project posture "release-ready = NO".
- Prior readiness (2026-09-26) was `TARGET_HOST_NOT_READY` on a Windows host; this
  is the first review of the Linux VPS.

## 2. Host baseline [H]

| Area | Observed |
|---|---|
| OS/res | Ubuntu 24.04.5, kernel 6.8.0-142, 6 vCPU, 11 GiB RAM (10 GiB avail), `/` 290G, 275G free; load 0.14 |
| PHP | 8.4.26 NTS; ext incl. pdo_pgsql, pgsql, redis, pcntl, gd, intl, mbstring, zip, OPcache |
| FPM limits | `mods-available/rtftt.ini` linked in fpm+cli `conf.d/20-rtftt.ini`: upload_max_filesize=600M, post_max_size=600M (php.ini default 2M/8M overridden). Pool `rtftt`: user rtftt, pm.max_children=8, request_terminate_timeout=600s, `open_basedir` set, clear_env |
| Nginx | `client_max_body_size 600M`, `client_body_timeout 600s`, `fastcgi_read_timeout 600s`, `server_tokens off`, dotfile/`storage/`/`vendor`/`.git` denied, only `/index.php` executes PHP |
| TLS | Let's Encrypt (YE1) for transcribe.ridzwanismail.com, 2026-09-30 → 2026-12-29; HTTP→HTTPS redirect |
| PostgreSQL | 16.15, cluster 16-main online, 127.0.0.1/::1:5432 only |
| Redis | 7.0.15, loopback only; `PING`→PONG **without auth** (loopback isolation; G-04 permits) |
| FFmpeg | ffmpeg/ffprobe 6.1.1 (Ubuntu) |
| Python worker | `rtftt-worker.service` (uvicorn, 127.0.0.1:8000, 1 worker, venv in `shared/worker-venv`); `GET /health` → 200 `{"status":"ok"}`; models: `faster-whisper-large-v3`, `nllb-200-distilled-600M` present in `shared/models/hub` (5.2 GiB blobs). Model *integrity/revision* not verified by me |
| systemd | Running+enabled: `rtftt-queue-transcription`, `rtftt-queue-translation` (`queue:work redis --tries=1 --timeout=330 --max-time=3600`, User=rtftt, TimeoutStopSec=420, Restart=always), `rtftt-worker`, `rtftt-scheduler.timer` (every minute → `schedule:run`), nginx, php8.4-fpm, postgres, redis, fail2ban, clamav-daemon. `systemctl --failed` = 0. NRestarts=6 on queue units is consistent with `--max-time=3600` on 6 h uptime |
| Storage | Layout `/srv/rtftt-transcribe-ai/{releases,current,shared}`; `shared/{.env 0600 rtftt, storage, models, backups 0700, tmp, worker-venv, worker.env 0600}`; `current/storage` → `shared/storage`; media dir `shared/storage/app/private/media` mode 0700 rtftt (not readable by deploy — consistent with private-disk posture) |
| Network | Listening externally only 22, 80, 443; 5432/6379/8000 loopback |
| SSH | `sshd_config.d/00-rtftt-hardening.conf`: PasswordAuthentication no, KbdInteractive no, PermitRootLogin no (sorts before main file's `PermitRootLogin yes`; first-match-wins → effective `no`, but `sshd -T` not runnable by me). `ubuntu` shell = nologin. `deploy` is in `sudo` group but passwordless sudo is absent (`sudo -n` → password required) |
| Firewall/Fail2Ban | `ufw`, `fail2ban`, `clamav-daemon` report `active` [H]; rule sets NOT inspectable without root → [C] only |
| Scanner | ClamAV active (D7-04) |
| Target evidence | `shared/storage/app/target-evidence/latest.json` (recorded 2026-09-30T10:12Z): `ac2-reboot-cycle` pass, `ac8-sigterm-drain` pass — operator-authored [C], not reproduced |

## 3. Application deployment baseline

- Deployed commit [H]: `current` → `releases/20260930081747`; `git rev-parse` = `83b666d` (= local HEAD). Working tree diff vs commit: only 10 deleted `storage/**/.gitignore` + untracked `storage` symlink (artifact of `storage`→`shared/storage`); **no tracked source differs** (`git ls-files -m | grep -v gitignore` empty).
- Release layout: single release retained; no prior release to roll back to (`ls releases` = 1). INFO — relevant to P7-008 rollback rehearsal, not to Phase B.
- Deployed release includes `node_modules/`, `tests/`, `.claude/`, `.mcp.json`, `.ai/`, `CLAUDE.md`, all governance docs. Not web-exposed (Nginx routes only `index.php`; `/\.` and listed paths 404) → INFO hygiene.
- Dev deps: `vendor/fakerphp` **absent** (`--no-dev` install). The Phase A harness workload builds `Transcription::factory()` (`app/Capacity/WorkloadRunner.php:234`). This is the M-1 finding from the 2026-09-26 review, still open.
- **NOT OBSERVABLE with my access** (`.env` is rtftt-only 0600; no sudo; DB peer-auth): `deployment:verify --strict`, `migrate:status`, effective `APP_ENV/APP_DEBUG/QUEUE_CONNECTION/DB_CONNECTION`, MediaFile/Transcription/ProcessingJob row counts, `redis` queue depth/failed_jobs, `clamav:health`, `storage:validate-topology`, `observability:diagnostics`, UFW/Fail2Ban rules. Every "app is healthy" statement in the task brief for these items is unverified by me.
- Log [H] (`shared/storage/logs/structured-2026-09-30.log`): two ERRORs at 10:08 UTC (serializable-closure `bindTo() on null`; `--format` unknown option) — operator drill/ad-hoc commands during hardening, not application faults on the request path. Burst of ~25 `security.denial` 419 on `POST /login` from 93.123.109.167 at 13:03 (CSRF failures; bot/scanner — controls working). No transcription/dispatch log line exists in the file.

## 4. P7-009 Phase B prerequisites vs canonical contract

| # | Prerequisite (source) | Status |
|---|---|---|
| 1 | Production-shaped host exists (`DECISION_QUEUE.md:6223`; contract §8) | MET in shape: Linux, pg16, Redis7, supervised workers+scheduler, 600M limits, models present [H] |
| 2 | Target-host characteristics recorded | PARTIAL — this document records what I could observe; effective app posture unrecorded (§3 unobservable list) |
| 3 | Separate readiness confirmation | THIS review; see verdict |
| 4 | Explicit HPO Phase B authorization | NOT GRANTED |
| 5 | P7-002 + P7-004 DONE (contract §5) | MET per `CURRENT_STATE.md` [R] |
| 6 | pg store in use, supervised Redis as `QUEUE_CONNECTION` (D7-01/B, D7-02/A) | Services present [H]; the *configured* drivers not verifiable (`.env` unreadable) — units pass `queue:work redis`, which is supportive but not proof of `QUEUE_CONNECTION`/`DB_CONNECTION` |
| 7 | Receiving floor 600M through PHP/web-server/proxy (G-01) | Config chain MET [H] (FPM ini, Nginx, timeouts). Actual 500 MiB deployed-stack proof = Phase B AC2, not yet run |
| 8 | Reachable large-v3/NLLB workers (AC3) | Worker `/health` ok, model weights present [H]; RTF not measured (correct — Phase B) |
| 9 | Harness runnable on host (Phase A review M-1: dev deps) | **NOT MET** — no `fakerphp/faker`; also requires HPO approval path for any dependency change (CLAUDE.md: no dependency changes without approval) or an out-of-tree harness environment |
| 10 | Clean, pinned image | MET — deployed tree = `83b666d`, no source drift [H] |
| 11 | AC2 reboot / AC8 SIGTERM real-host evidence (carry-forward, pre-P7-012) | Recorded as pass [C]; reviewer reproduction pending. AC8 partly corroborated by unit config (`TimeoutStopSec=420` > `--timeout=330`) |
| 12 | Abuse limits armed during load (contract §11) | Cannot confirm effective `security.*` config without env access |
| 13 | Synthetic-only corpus, no customer data (contract §11) | Harness design [R]; **but a real user media file now exists on the host** (uploaded through UI; media dir 0700, size/content not inspected). Phase B must not touch it |

## 5. Real-host finding — MediaFile `Uploaded`, no Transcription, no ProcessingJob

**Conclusion: expected behaviour of the shipped code. There is no automatic
processing after upload and no user-facing action that initiates real
transcription of an uploaded MediaFile. Not a deployment/configuration fault.
It is a product-contract gap that the repository never closed.**

Trace [R]:

1. `POST media.store` → `MediaUploadController::store`
   (`app/Http/Controllers/MediaUploadController.php:36-105`): validates,
   `UploadAbuseGuard::check`, calls `$ingestion->ingest(...)`, returns redirect/JSON to
   `media.show`. No transcription reference anywhere in the controller.
2. `MediaIngestionService::ingest` (`app/Actions/MediaIngestionService.php:87-…`)
   stages → validates → promotes → persists `MediaFile` with
   `status => MediaStatus::Uploaded` (line 148). `grep` of the file for
   `Transcription|dispatch|Orchestrator` finds no initiation. P2-002
   (`tasks/P2-002-define-ingestion-lifecycle-contract.md:11`) states upload completion
   uses `uploaded`, while `processing`/`ready` are "reserved for later processing
   phases".
3. `TranscriptionOrchestrator::request()` (`app/Actions/TranscriptionOrchestrator.php:32`)
   is the only creator of a queued `ProcessingJob` + dispatcher of
   `ProcessTranscription` (line 99). P3-006 describes it as "server-side request
   entry point" (`tasks/P3-006-redis-queue-orchestration.md:89-91`). Its callers in
   `app/`: `TranscriptionRetry` (retry of an already-failed transcription only) and
   the test commands `Phase3IntegrationVerification.php:98`. **No controller, Livewire
   component, listener, observer, or scheduled command calls it for a new upload**
   (`grep -rn "->request(" app` shows only translation actions and verification
   commands; `app/Livewire/Media/Show.php` has no transcribe action; the media show
   view (`resources/views/livewire/media/show.blade.php:68-70`) only lists *existing*
   transcriptions: "No transcriptions found for this media file.").
4. The only "create transcription" UI path is `POST transcriptions.store`
   (`routes/web.php:33`) → `DemoTranscriptionController::store`
   (`app/Http/Controllers/DemoTranscriptionController.php:16-`), which **ignores uploaded
   media**, fabricates a fake `MediaFile` (random size/duration, `media/demo/...` path),
   a `Completed` transcription with hard-coded text, a fake segment and a fake
   `Completed` ProcessingJob — no queue, no worker. ADR-006 (`DECISIONS.md:263-279`)
   scopes this as a Phase 1 prototype form.
5. Real-model E2E evidence (`verification/LARGE-V3-FULL-CHAIN-E2E.md`,
   `REAL-MODEL-FULL-CHAIN-E2E.md`) drove the chain by scripted/`verification`
   entry points and states `DemoTranscriptionController` "was NOT used"
   (`REAL-MODEL-FULL-CHAIN-E2E.md:49`). No accepted contract describes a browser
   "start transcription" action. `docs/PRODUCTION_READINESS_GATE.md` "Full chain"
   requires "real media upload (HTTP endpoint) → ingestion → real transcription
   (live queue → authenticated worker …)" with no seeded shortcuts — but names no
   initiation mechanism.

Canonical answer to "what does the contract require?": automatic-on-upload — **no
contract requires it**; explicit user initiation — **no contract defines it**;
alternative workflow — **none defined**. The contract stack defines domain
(P3-001), orchestrator (P3-006), retry (P3-007), workspace (P4), translation (P5),
editing (P6) but never the upload→transcription bridge. This must be raised as an
HPO product decision; I do not select the behaviour.

Deployment/config causes ruled out as far as observable [H]: queue workers and
Python worker are up and healthy; ingestion itself worked (row exists);
`ProcessingJob`/`Transcription` were never attempted, so no queue/worker fault could
be involved. (Row-level confirmation of counts is a listed post-review check.)

## 6. Findings (repository severity)

- **H-1 (HIGH)** — No product path from uploaded MediaFile to real transcription
  (§5). Blocks G-12 ("real media upload (HTTP) → … → transcription", no doubles) and
  any Phase B workload that must exercise the *user* path (contract §6.3 RTF
  "canonical large-v3"; AC3). Routes to HPO as a decision: new implementation task
  (owner OpenCode) vs. accept operator-invoked initiation for Phase B/G-12 evidence
  only. Not fixable under P7-009 (contract §7 non-scope: no redesign).
- **M-1 (MEDIUM, carried from 2026-09-26)** — `fakerphp/faker` absent on host; the
  capacity harness cannot build its transcription workload as deployed. Needs an
  explicit HPO-approved method (dev-deps install or separate harness runtime)
  before Phase B.
- **M-2 (MEDIUM)** — `DemoTranscriptionController` (fake completed transcriptions,
  fake media) is live in production at `POST /transcriptions` and reachable from
  the sidebar "create" form. Integrity/confusion risk for a real single admin and
  for P7-009/G-12 counts (would inflate Transcription rows).
- **M-3 (MEDIUM, evidence gap)** — Effective application posture unverified by an
  independent reader: `deployment:verify --strict`, migrations, `.env` posture,
  queue/DB drivers, ClamAV health, UFW/Fail2Ban rulesets. My access cannot read
  them; operator [C] claims only.
- **L-1 (LOW)** — Redis has no `requirepass` (loopback-only; acceptable under
  G-04 wording, must be recorded as decision evidence at P7-012).
- **L-2 (LOW)** — One retained release; no rollback target (P7-008 rehearsal
  evidence must include a second release).
- **I-1 (INFO)** — Dev/agent/governance files and `node_modules` in the release tree
  (not web-served).
- **I-2 (INFO)** — A real user media file is present in production storage; keep
  Phase B synthetic-only and isolated from it (contract §11).
- **I-3 (INFO)** — AC2/AC8 target evidence recorded as pass by operator; reviewer has
  not reproduced (no reboot/drain performed — read-only boundary).
- **I-4 (INFO)** — 419 login-POST burst from 93.123.109.167 at 13:03 UTC.

No BLOCKER on host shape was found. H-1 is a HIGH governance/product finding, and
M-1/M-3 are pre-Phase-B closables.

## 7. Verdict (repository vocabulary: `TARGET_HOST_READY` / `TARGET_HOST_NOT_READY`)

### `TARGET_HOST_NOT_READY`

Reason: the host *shape* is production-shaped and no host defect was found, but
readiness cannot be confirmed on independent evidence: effective app posture,
`deployment:verify --strict`, and migrations are unobservable to me (M-3), the
harness precondition is unmet (M-1), and H-1 leaves the canonical upload→real
transcription chain without an entry point, so Phase B/G-12 scope is undefined.
This is an evidence/decision gap, not a substrate failure; one round of read-only
operator output plus two HPO decisions should convert it. It authorizes nothing.

## 8. Exact next governance actions

1. **Operator (read-only, no repo change)** — run as the runtime user and paste
   output into a repo artifact, so I can re-confirm without inferring:
   `sudo -u rtftt php artisan deployment:verify --strict`,
   `migrate:status`, `config:show` for `app.env app.debug queue.default
   database.default transcription.queue_connection filesystems.default`,
   `observability:diagnostics`, `storage:validate-topology`, `clamav:health`,
   `sudo ufw status verbose`, `sudo fail2ban-client status sshd`,
   `sudo sshd -T | grep -E "permitrootlogin|passwordauth"`, and row counts
   (`media_files`, `transcriptions`, `processing_jobs`, `failed_jobs`, Redis queue
   depth). No changes.
2. **HPO decision → `DECISION_QUEUE.md`** on H-1: how is real transcription
   initiated for an uploaded file (product feature via new task vs operator
   invocation for evidence only), and disposition of `DemoTranscriptionController`
   (M-2). Agents must not choose.
3. **HPO decision** on M-1: how the harness obtains factory dependencies on the
   host.
4. **Re-run this confirmation** (Review 003) after 1–3. Only if it returns
   `TARGET_HOST_READY` may the HPO issue an explicit
   `DECISION-P7-009-PHASE-B-EXECUTION-AUTHORIZATION-…`. Until then P7-009 stays
   IN_PROGRESS Phase A only, restore drill (P7-007) unauthorized, P7-012 untouched.

## 9. Boundaries observed

Read-only host commands only (`ls`, `cat`, `systemctl`, `ss`, `curl` to loopback
`/health`, `redis-cli ping`, `git status/diff/rev-parse`, `openssl s_client`). No
files written on the host, no service touched, no secrets read (`.env`,
`worker.env` unreadable and not attempted via elevation). One repo artifact
written (this file); no application code changed.
