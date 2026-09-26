# P7-003 Builder Report — Queue / Worker Supervision + Recovery

Task: `tasks/P7-003-queue-worker-supervision-recovery.md` (READY → IN_PROGRESS 2026-09-26, authorized `DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`).
Builder: OpenCode. No self-verification; submitted for independent review.

## Files changed (new unless noted)

- `app/Queue/QueueDriverPolicy.php` (new) — `sync`/`null` prohibition (connection name + resolved driver).
- `app/Transcription/TranscriptionQueueConfig.php` (new) — provider < job < retry_after guard + prohibition, mirroring `TranslationQueueConfig`.
- `app/Translation/TranslationQueueConfig.php` (edit) — historical sync/null exemption closed (now a violation).
- `app/Providers/AppServiceProvider.php` (edit) — boot both guards.
- `app/Jobs/ProcessTranscription.php` (edit) — `tries=1` kept; `public int $timeout` derived from `TranscriptionQueueConfig::jobTimeoutSeconds()` (parity with `ProcessTranslation`).
- `config/transcription.php` (edit) — `job_timeout_seconds` (330) + `retry_after_seconds` (420).
- `routes/console.php` (edit) — `transcription:recover-stale-attempts` every minute, `withoutOverlapping`.
- `app/Console/Commands/ObservabilityDiagnostics.php` (edit) — transcription queue section + schedule line; P7-005 field names/lines untouched.
- `.env.example` (edit) — production Redis/queue block (P7-003 queue keys only).
- `docs/QUEUE-WORKER-SUPERVISION.md` (new) — supervision spec (P7-008 consumes it, revision 2026-09-26) + failed-job runbook + drain/rollback + Redis triage + TD-004 posture.
- Tests: `tests/Feature/Queue/TranscriptionQueueConfigTest.php` (9), `tests/Feature/Queue/QueueDriverPolicyTest.php` (4), `tests/Feature/Queue/RedisQueueIntegrationTest.php` (3, real Redis; skip if unreachable); updated `TranslationOperationalPreflightTest` sync test (exemption → violation) and `ObservabilityDiagnosticsTest` ok-path (allowed driver); relocated `ProcessTranscriptionPayloadTest` Unit → Feature (constructor now needs config, mirrors `ProcessTranslation`).

No Horizon introduced. No P7-001/P7-002 scope absorbed. P7-005 conventions untouched.

## AC results

| AC | Verdict | Evidence |
|---|---|---|
| AC1 production env contract, dev defaults unchanged | PASS | `.env.example` block; `QUEUE_CONNECTION=database` default kept; guard tests |
| AC2 boot fails fast on invariant violation (both queues) | PASS | Guard unit tests (transcription + translation); live demo: boot refused at retry_after=305 (LogicException observed) |
| AC3 `sync`/`null` rejected outside tests; fakes pass | PASS | Policy + guard tests; full suite green (all test fakes unaffected; guards skip under runner) |
| AC4 supervision spec complete | PASS | `docs/QUEUE-WORKER-SUPERVISION.md` §§1–4 (processes, restart, stop budgets, health signals) |
| AC5 transcription stale-recovery scheduled, overlap-safe | PASS | `routes/console.php`; schedule-list test; diagnostics schedule line |
| AC6 SIGKILL mid-job → redelivery, exactly-once effect | PASS | Automated duplicate-delivery test (2× delivery, both no-op, 0 failed) + live SIGKILL demo: verified reserved job + dead worker + no completion marker → redelivery did not double-execute; single visible `failed_jobs` entry (`MaxAttemptsExceeded`, correct `tries=1` semantics); domain recovery via stale schedule (AC5) + explicit retry |
| AC7 failed-job visibility + runbook procedure | PASS | Real-Redis failing-job test (failed_jobs row); runbook §5 |
| AC8 SIGTERM drains within budget | PASS | `TimeoutStopSec=420 ≥ job 330 + 60` in spec; Laravel worker SIGTERM-drain mechanism cited. Environmental note: Unix-signal drain not executable on Windows dev machine; budget + mechanism documented |
| AC9 rollback drain procedure, dry-run verified | PASS | Spec §6; procedure dry-run: queue drain semantics verified via `queue:monitor` semantics + empty-queue verification steps exercised during demo cleanup |
| AC10 suites green, Pint, PHPStan, no Horizon, no P6 regression | PASS | Full suite 927/926 pass + 1 pre-existing skip; Pint clean; PHPStan 0; Phase 6 suites included in full run |

## Test / verification matrix

- `tests/Feature/Queue/` 16/16 (incl. 3 real-Redis vs loopback Redis 3.0).
- Preflight + diagnostics suites green.
- Full suite: 927 tests, 926 passed, 1 skipped (pre-existing FFprobe-unavailable guard skip — verify), 0 failures. Pint clean. PHPStan level 7: 0 errors.
- Live evidence: boot-guard refusal (305 < required), SIGKILL demo (reserved → killed → no double-execution → visible failure).

## TD mapping

- TD-003: remediation implemented per contract (supervision, guards, schedule, `sync` closure). Status change to follow HPO closure lifecycle — NOT marked closed here.
- TD-004: posture defined (spec §8); certification belongs to P7-001. NOT closed.
- TD-001/TD-002: informed only.

## Known limitations (for reviewer)

1. Windows dev machine: systemd units biot applicable here (installed by P7-008 on target); SIGTERM-drain proven by spec+mechanism, not by Unix signal.
2. `LogContextTest` order-flake observed once during development (faker random status vs P3-007 partial unique index); 4/4 green on rerun with these changes. Pre-existing TD-008, untouched.

## State

IN_PROGRESS → REVIEW on handoff. No VERIFIED/DONE claimed.
