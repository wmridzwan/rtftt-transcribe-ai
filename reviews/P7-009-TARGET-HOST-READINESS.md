# P7-009 Target-Host Readiness Confirmation — READINESS REVIEW

Role: designated readiness reviewer (not builder, not HPO). Scope: confirm
whether the presented host is sufficiently production-shaped for the final
P7-009 Phase B capacity run. No capacity run executed. No capacity results
claimed. No Phase B authorization given (authorization is an HPO act).

Date: 2026-09-26. All checks below were executed live on the presented host
during this review; nothing is accepted on Phase A memory alone.

## 1. Target-host identity (verified live)

- Host class: developer workstation (Windows 11 Pro, 64-bit, build 26200).
- CPU: Intel i5-10210U mobile, 4 cores / 8 threads @ 2.11 GHz.
- Memory: ~24 GB visible (~11.3 GB free at review time).
- Storage: single C: volume, 254 GB total, ~7.6 GB free (pressure — see §7).
- Filesystem: NTFS (Windows volume; not the deployed Linux filesystem).
- Network: loopback-only service posture (127.0.0.1 Redis PING-ok; pg/model
  ports refused — see §3). No production network characteristics evidenced.
- Application commit: `4d90d5b` (matches Phase A baseline; 155 changed/
  untracked entries in working tree — rehearsal/dev residue, not a target
  image).
- PHP/runtime: 8.4.24 NTS (Windows build), memory_limit 128M,
  max_execution_time 0 (CLI), upload_max_filesize 512M, post_max_size 520M.
- PostgreSQL: none (no service, no registry key, TCP 5432 refused).
- Redis: loopback `redis-server` process (PID 3220), PING-ok; unsupervised,
  and NOT the configured queue driver.
- Queue driver: `database` (configured + live: jobs 0, failed_jobs 0).
- Worker/supervisor: none (no php.exe worker processes; no supervisord;
  P7-003 supervised posture absent).
- Scheduler: no Laravel cron-equivalent (only Windows built-in backup tasks).
- App env: `APP_ENV=local`, `APP_DEBUG=true`.
- Upload/runtime limits: 512M/520M — below the 600M floor (deployment:verify
  flags this live, as in Phase A).
- FFmpeg/ffprobe: ffprobe 9.0.1 Windows (Gyan) build present and functional
  (Phase A probe extracted real metadata). Tool present, wrong platform for
  target evidence.
- Transcription model/runtime: configured `large-v3` @ 127.0.0.1:8000 —
  TCP refused (worker not running). Translation: self-hosted defaults —
  TCP refused on 8000/8001 (workers not running).
- Deployment topology: Herd Windows dev stack (dev DB file, local private
  disk `storage/app/private`, writable). `deployment:verify` passes with
  standing advisories (limits floor, APP_DEBUG, target evidence PENDING
  for ac2-reboot-cycle and ac8-sigterm-drain, no backup sets).

## 2. Production-shaped equivalence

Intended production (D7-01/B, D7-02/A, D7-03/A + P7-001 floor): Linux target,
pg production store, supervised Redis as `QUEUE_CONNECTION`, local private
storage, 600M receiving floor, supervised workers + scheduler, reachable
large-v3/NLLB workers.

DifferencesSiehe §1 — classified:

- Substitute-only limitation / Phase-B blocker: Windows workstation OS/filesystem;
  SQLite (D7-01/B requires pg); database queue + unsupervised loopback Redis
  (D7-02/A requires supervised Redis); 512M/520M limits below floor (G-01
  impossible); model workers unreachable (AC3 impossible); no worker
  supervision or scheduler daemon; APP_ENV=local/DEBUG=true.
- Acceptable production-shaped variance: none claimed — the host does not
  reach the threshold where variance analysis applies. (ffprobe presence and
  the local-private-disk topology rhyme with production but do not make the
  host production-shaped.)
- Each blocker independently disqualifies final capacity evidence; together
  they describe the Phase A substitute host, unchanged.

## 3. Service readiness (operational, not config-only)

- PostgreSQL: NOT READY (absent).
- Redis: loopback PING-ok but unsupervised and off the queue path — NOT READY
  as a production broker.
- Queue workers: NOT READY (none running; drain behavior unprovable).
- Scheduler: NOT READY (no daemon).
- Local/private storage: ready as substitute (writable, topology ok).
- FFmpeg/ffprobe: ready as a tool (9.0.1); not target evidence.
- Transcription/translation workers and models: NOT READY (unreachable).
- Streaming/download paths: code-verified (P7-004) and rehearsal-timed; live
  target behavior unproven.
- Observability/diagnostics: command-complete; target-wired alerting unproven.

## 4. Harness portability

The Phase A harness (`app/Capacity/*`, `CapacityProbeJob`, `capacity:validate`)
is methodology-portable: workload categories, hrtime timer semantics,
completion ledger, failure preservation, repeat/variance handling, evidence
structure (9 files/run), correctness checks, and the non-verdict boundary are
all code-fixed and configuration-driven (queue driver, media disk, DB,
`--repeat/--concurrency/--seed/--blob-bytes/--segments/--output`). No
methodology change is required to run on a production-shaped host.
Precondition (environment configuration, not methodology): the target host
must have dev dependencies installed — the transcription workload uses
`Transcription::factory()`, and `fakerphp/faker` is require-dev. Recorded as
MEDIUM finding M-1; confirm `faker` present (or vendor installed without
`--no-dev`) before the Phase B run.

## 5. Corpus readiness

Seeded synthetic corpus reproducible (run 2 reproduced run 1; per-item
SHA-256 verified in self-tests). Sizes/durations/formats/languages recorded
in every `corpus-manifest.json`. No customer/private data required or used.
READY (portable to any host).

## 6. Evidence storage

Harness artifacts are KiB-range per run (largest: 465 KB corpus manifest);
evidence survives in `verification/p7-009/<run_id>/`. Host free space ~7.6 GB
is sufficient for harness artifacts but is a pressure condition for any
500 MiB-scale G-01 attempt (see HIGH finding). Retention location is
version-controlled and reviewable. READY for rehearsal scale; G-01 scale
requires the target host's capacity.

## 7. Ambient conditions (at review)

CPU: mobile i5-U (shared dev host; load query unavailable — treated as
unknown, not idle). Memory: ~11.3/24 GB free. Disk: 7.6 GB free of 254 GB
(pressure disclosed). Queue depth 0, failed_jobs 0, DB healthy (sqlite).
Loopback Redis healthy. Competing workload: Herd dev stack + desktop session
on the same host. No unrealistically-idle claim is made; conditions are
immaterial to the verdict because the host is disqualified on shape, not on
ambient noise.

## 8. Reboot/drain prerequisites

Reboot persistence, worker restart, queue drain, scheduler recovery, and
service-restart behavior cannot be meaningfully proven here (nothing
supervised to restart; database queue drains trivially but that proves
nothing about the target broker). The standing `deployment:verify` items
(ac2-reboot-cycle, ac8-sigterm-drain PENDING) remain PENDING. No destructive
drill work performed (P7-007 remains deferred and unauthorized).

## 9. Safety

Synthetic corpus only — no customer data at risk; no external systems
touched; no paid APIs/models consumed (workers unreachable; harness invokes
none); saturation logic is observe-and-record with rehearsal-safe bounds.
Running the harness here is safe; INTERPRETING any run here as capacity
evidence would be unsafe — which is exactly what the withheld Phase B
prevents. No persistent state corrupted (transaction rollback + file/marker
cleanup verified).

## 10. Phase A LOW finding

Completion accounting remains resolved and is portable: the ledger,
`reconcile()`, exit-code wiring, and per-report accounting are code, not
environment. Both rehearsal runs reconciled 27/27 with 0 failures. Target
execution will record dispatched/completed/failed/skipped per workload by
construction (`CapacityValidateCommandTest` pins the 9-file bundle and the
reconciliation gate).

## 11. Environment classification

This environment is NOT classified as a production-shaped target. It remains:

**REHEARSAL / SUBSTITUTE — NOT TARGET EVIDENCE**

## 12. Findings

- B-1 (BLOCKER, blocks): No PostgreSQL; sqlite cannot evidence AC2/G-01/pg behavior.
- B-2 (BLOCKER, blocks): No supervised Redis queue posture; database driver + no workers + no scheduler.
- B-3 (BLOCKER, blocks): Windows developer workstation, not the deployed Linux topology.
- B-4 (BLOCKER, blocks): Receiving limits 512M/520M below the 600M floor; 500 MiB G-01 proof impossible.
- B-5 (BLOCKER, blocks): Model workers unreachable; AC3 real-model measurements impossible.
- H-1 (HIGH, blocks in combination): 7.6 GB free disk pressure on a dev volume.
- M-1 (MEDIUM, must close pre-run, non-blocking to authorization): faker/require-dev must be present on the target host for the factory-backed transcription workload.
- M-2 (MEDIUM, informational here): no scheduler daemon — target must evidence P7-003 schedules live.
- L-1 (LOW): Phase A accounting LOW stays resolved (§10).
- I-1 (INFO): loopback Redis healthy but substitute; ffprobe present but wrong platform for target evidence; working tree has 155 dev-residue entries — the target run needs a clean, pinned image, not this tree.

## Verdict

### `TARGET_HOST_NOT_READY`

Final capacity evidence produced here would be misleading (wrong OS, wrong
datastore, wrong broker, unreachable models, below-floor limits) and
dependent on substitute infrastructure throughout.

## Next legal action

**Remediate only the identified environment gaps and repeat target-host readiness confirmation. Phase B remains unauthorized.**

Concretely: provision a production-shaped Linux target (pg + supervised
Redis + supervised workers + scheduler + 600M floor + reachable large-v3/NLLB
+ adequate disk + pinned clean image + dev deps for the harness), then repeat
this confirmation. After `TARGET_HOST_READY`, still obtain explicit HPO Phase B
execution authorization before any final run. P7-009 stays IN_PROGRESS; no
state transition, no drill, no debt change, no P7-012 implication.
