# Queue / Worker Supervision Specification (P7-003)

Status: AUTHORITATIVE SPECIFICATION for worker-lifecycle semantics.
Owner: P7-003 (specification). Consumer: P7-008 (installation of
supervision per this spec; P7-008 must revision-pin the spec it
implements). Binding decision: D7-02 = Redis + systemd/supervisord,
no Horizon.

## 1. Required processes

| Process | Command | Queues | Minimum instances (single-admin) |
|---|---|---|---|
| Transcription worker | `php artisan queue:work redis --queue=transcription --tries=1 --timeout=<job-timeout> --sleep=3 --max-time=3600` | `transcription` | 1 |
| Translation worker | `php artisan queue:work redis --queue=translation --tries=1 --timeout=<job-timeout> --sleep=3 --max-time=3600` | `translation` | 1 |
| Scheduler | `php artisan schedule:run` every minute (systemd timer or supervisord cron-equivalent) | — | 1 |

`<job-timeout>` is the per-queue `jobTimeoutSeconds()` (330s default for
both queues: translation via `TranslationQueueConfig`, transcription via
`TranscriptionQueueConfig`). `--timeout` must equal the job timeout so the
worker SIGTERMs an overrunning job exactly when the domain considers it
timed out; `--tries=1` is frozen (P3-007; retries happen only through the
explicit domain retry actions). `--max-time=3600` recycles workers hourly
against memory growth; `--sleep=3` bounds idle Redis polling.

Horizon is not installed, not configured, not referenced. No new queue
external dependency exists.

## 2. Restart policy

- `Restart=always` (systemd) / `autorestart=true` (supervisord) with
  `RestartSec=5` / `startretries` bounded backoff: failed processes
  resume consumption with no manual intervention.
- `StartLimitBurst` (or supervisord equivalent) trips on repeated fast
  crashes; tripping pages the operator to the failed-job runbook (§5)
  instead of hot-looping.
- Enable at boot (`WantedBy=multi-user.target` / supervisord
  `autostart=true`).

## 3. Graceful termination

- `TimeoutStopSec` (or `stopsignal=TERM` + `stopwaitsecs`) must be **>=
  job timeout + 60s margin** (390s at defaults): SIGTERM stops accepting
  new jobs and lets the current job finish; the worker never kills a
  mid-inference job inside its timeout.
- SIGKILL path is covered by redelivery safety: the attempt-claim fence
  plus idempotent result writers make redelivery exactly-once in effect
  (duplicate delivery observes a non-`Queued` attempt and no-ops).
- Scheduler stops are dependency-free (overlap protection via
  `withoutOverlapping`).

## 4. Health signals (consumed by P7-008 readiness)

1. Process presence: both worker processes + scheduler running.
2. Queue depth: `transcription` / `translation` Redis list lengths stable
   (growth without completions = stuck consumption).
3. Failed-job growth: `failed_jobs` count not increasing week over week.
4. Boot-guard clean: `observability:diagnostics --strict` exits 0
   (both-queue invariant + both schedules present).
5. Worker reachability: `observability:diagnostics --probe-worker`
   reachable for both worker URLs.

## 5. Failed-job runbook

Failed jobs land in `failed_jobs` (database-uuids driver):

```bash
# list recent failures (identifiers only — never paste secrets)
php artisan queue:failed
# inspect one (correlation fields: http_request_id / request_id /
# queue_job_id per P7-005 conventions)
php artisan queue:failed <uuid>
# retry selected failures after fixing the cause
php artisan queue:retry <uuid> [<uuid> ...]
# retry all, or forget (only when the effect is proven applied elsewhere)
php artisan queue:retry all
php artisan queue:forget <uuid>
```

Triage order: read the failure record → reproduce the cause in logs via
the correlation id → fix the cause → retry → confirm completion in
`processing_jobs` / domain state. Never retry blindly on an unexamined
inference failure: `tries=1` plus claim fencing means a blind retry is a
second expensive model run, not a free operation.

## 6. Drain-based rollback (driver switch)

Reverting the queue driver is config-only and requires drained queues:

1. Stop dispatch: enable maintenance mode (`php artisan down`).
2. Drain: let workers consume to empty — verify with
   `php artisan queue:monitor redis:transcription,redis:translation`
   (or Redis `LLEN`) showing 0 on both queues plus no `reserved` jobs.
3. Switch: set the prior `QUEUE_CONNECTION` / per-queue overrides.
4. Restart workers + scheduler; run `observability:diagnostics --strict`.
5. Smoke: dispatch one transcription and one translation; confirm
   completion; lift maintenance mode.

Dry-run this procedure in the verification environment before relying on
it (P7-003 AC9).

## 7. Redis outage triage

1. Jobs wait — Redis-backed queues drop nothing on disconnect; do not
   re-dispatch manually (duplicates would fence harmlessly but waste
   inference on retry).
2. Confirm Redis process + port + auth (`redis-cli -p 6379 ping`;
   `REDIS_PASSWORD` when off-loopback).
3. Restart Redis; workers reconnect and resume automatically
   (supervision restarts them if they exited).
4. Verify depth drains and completions resume; inspect `failed_jobs` for
   anything the outage converted to a failure.

## 8. Redis security posture (TD-004 definition; P7-001 certifies)

- Loopback-only single-node Redis: password optional but recommended;
  document the choice.
- Redis leaving loopback or shared between services/hosts: `REDIS_PASSWORD`
  (strong secret) + bind-interface restriction + firewall rule required;
  TLS where the platform provides it. No passwordless Redis outside
  loopback isolation.
- `REDIS_PASSWORD` lives in env only, never in logs, runbook examples, or
  failed-job payloads.

## 9. Local dev/test behavior (unchanged)

`database` (or fakes) remain the dev/test default; no developer-machine
supervisor required. The Redis path is exercised via explicit env
(`QUEUE_CONNECTION=redis`). Boot guards skip under the test runner.
