# P7-003 — Queue / Worker Supervision + Recovery

## Status

DONE — closed by the Human Product Owner on 2026-09-26
(`DECISION-P7-003-CLOSURE-001`) on the independent VERIFIED verdict
(`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md` §B; no BLOCKER/HIGH).

History preserved: BACKLOG — CONTRACT_AUTHORED (2026-09-26) → READY (HPO
`DECISION-PHASE7-WAVE1-READY-PROMOTION-001`) → IN_PROGRESS (execution
authorized `DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`; work begun
2026-09-26) → REVIEW (builder report `reviews/P7-003-BUILDER-REPORT.md`) →
VERIFIED (`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md`§B; all 10 ACs
independently confirmed; LOW-1/INFO-1 non-blocking) → DONE (HPO
`DECISION-P7-003-CLOSURE-001`, 2026-09-26; LOW-1 stray file removed and
recorded). This review does not authorize any later Phase 7 wave.

## Ownership

Implementation Owner: (unassigned — HPO assigns on READY promotion)
Reviewer: Claude Code (independent review on REVIEW)

## Authorized Phase

Phase 7 — Production Hardening (`PHASE7-SCOPE-CONTRACT.md`, ADOPTED —
NOT AUTHORIZED FOR IMPLEMENTATION). This contract alone authorizes no
implementation.

## 1. Task Identity

- Task ID: P7-003
- Canonical title: Queue / Worker Supervision + Recovery
- Phase: 7 — Production Hardening
- Proposed state: READY (HPO promotion `DECISION-PHASE7-WAVE1-READY-PROMOTION-001`, 2026-09-26)

## 2. Objective

Move the production queue posture from the current unsupervised
`database`-default driver to the adopted supervised Redis topology
(D7-02/A): Redis-backed queues for transcription and translation, OS-level
worker supervision without Horizon, verified timeout/retry semantics,
failed-job visibility, and proven stale/orphan recovery — without changing
job payload semantics, claim fencing, or the P7-005 observability
contract.

## 3. Why This Task Exists

- Default queue connection is `database` with no supervisor, recovery,
  dead-letter, or failed-job visibility (`config/queue.php:16`;
  `.env.example: QUEUE_CONNECTION=database`); production would stall jobs
  silently (TD-003, HIGH/YES).
- The `sync` driver is exempt from the translation `retry_after` boot
  guard (`TranslationQueueConfig`; `config/queue.php:34-36`), so selecting
  `sync` silently voids the provider < job < retry_after invariant and
  runs long jobs inline.
- Only the translation stale-recovery schedule exists
  (`routes/console.php`: `translation:recover-stale-attempts` every
  minute); no transcription stale-recovery schedule exists although
  `App\Actions\StaleTranscriptionAttemptRecovery` exists.
- Only translation has a consistency boot guard
  (`App\Translation\TranslationQueueConfig`); transcription has no
  equivalent guard tying provider timeout < job timeout < retry_after.
- Production gate G-03/G-04 require a certified, supervised, recoverable
  queue topology with `sync` prohibited for transcription/translation
  queues.

## 4. Binding Decisions / ADRs

- D7-02 = OPTION A (Redis + systemd/supervisord, no Horizon) —
  `DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026. Do not reopen.
- D7-08 = OPTION A (single-admin low-concurrency) — worker sizing targets
  the adopted capacity model; no multi-tenant proof.
- D7-01/D7-03/D7-04/D7-06/D7-07 respected where they touch operations
  (failed-job store lives on the production datastore; no scope expansion).
- ADR-017 (provider-neutral worker boundary; minimum correlation fields);
  ADR-018 (stale-attempt hardening precedent); ADR-023 (early-hardening
  allowlist context); `PHASE7-SCOPE-CONTRACT.md` §§B–F.

## 5. Dependencies

- Hard prerequisites (all satisfied at policy level):
  - D7-02 resolved (OPTION A). No other owner decision gates this task.
  - P7-005 DONE — correlation-field naming (`http_request_id` /
    `request_id` / `queue_job_id`) and diagnostics conventions are frozen
    inputs; this task consumes them unchanged.
  - Phase 3/5 queue semantics frozen: `transcription` / `translation`
    queue names, small-identifier payloads, attempt-claim fencing,
    failure taxonomy as the single classification source.
- Soft dependencies (coordinate, do not block):
  - P7-008 (deployment topology installs the supervisor units this task
    specifies; see §8 boundary).
  - P7-001 (environment certification of the production Redis
    version/config; TD-004 posture split in §15).
- Downstream: P7-012 gate G-03/G-04; P7-009 final capacity run consumes
  the supervised topology.
- Parallel-safe: P7-010 (no shared files or semantics).

## 6. In Scope

1. Production queue configuration: `QUEUE_CONNECTION=redis` as the
   production default; explicit per-queue connections preserved
   (`RTFTT_TRANSCRIPTION_QUEUE_CONNECTION`,
   `RTFTT_TRANSLATION_QUEUE_CONNECTION`); queue names `transcription` /
   `translation` unchanged.
2. Redis connection parity: `retry_after` values that satisfy the
   provider < job < retry_after invariant for both queues (current 420s
   defaults retained unless evidence justifies change; any change must
   keep the invariant with margin).
3. Transcription consistency guard: extend the boot-guard pattern to the
   transcription queue (provider `transcription.timeout_seconds` < job
   timeout < connection `retry_after`), failing fast on violation, mirroring
   `TranslationQueueConfig` without duplicating its ownership.
4. `sync` prohibition: `sync` (and `null`) forbidden for the
   transcription/translation queues in any non-test environment; close the
   guard exemption by failing fast at boot (test overrides via existing
   fakes remain legal).
5. Worker supervision specification: the worker-supervision contract —
   which processes must run (at least one worker per queue:
   `transcription`, `translation`), restart policy (always, with backoff),
   graceful termination (SIGTERM drains the current job within the job
   timeout; no mid-inference kill), `--timeout` alignment with the job
   timeouts, `--tries=1` preserved (no silent retry-policy change),
   `--sleep`/`--max-jobs`/`--max-time` values with rationale.
6. Scheduler: add the transcription stale-recovery schedule (mirroring the
   translation every-minute, `withoutOverlapping` precedent); document why
   the cadence is safe against the stale thresholds.
7. Failed-job visibility: `failed_jobs` (database-uuids) retention +
   documented query/runbook procedure for listing, inspecting (with
   correlation fields), and retrying/forgiving failed jobs; no Horizon,
   no new external dependency.
8. Recovery verification: redelivery safety re-proven under the Redis
   driver (claim fencing + idempotent writers; duplicate-delivery no-op
   test), stale-recovery proven for both queues, worker-kill recovery
   (SIGKILL mid-job → redelivery → exactly-once effect via fencing).
9. Diagnostics extension within P7-005 conventions: report effective
   connection per queue, invariant status per queue, supervisor/worker
   reachability; no new public route.

## 7. Explicit Non-Scope

- Laravel Horizon (excluded by D7-02; must not be introduced).
- Datastore migration (P7-002); storage backend work (P7-004); security
  baseline beyond queue posture (P7-006); backup/restore (P7-007);
  retention purge (P7-011); browser work (P7-010).
- P7-001 environment certification (this task defines the required Redis
  posture; P7-001 certifies the deployed environment against it).
- P7-008 deployment artifacts: this task specifies the supervision
  contract; P7-008 owns installing/enabling units, server topology, and
  release layout. P7-003 must not write deployment topology.
- Retry-policy redesign (`tries=1` is frozen unless a new HPO decision
  changes it); provider-contract or timeout-value redesign without
  evidence.
- External metrics/tracing backends (deferred beyond P7-005).

## 8. Architecture / Domain Contract

- Dispatchers (`TranscriptionOrchestrator`, `TranslationDispatcher`) keep
  their small-identifier payloads, queue names, and optional explicit
  connections; only the effective default connection changes in the
  production environment contract.
- Jobs (`ProcessTranscription`, `ProcessTranslation`) keep `tries = 1`,
  claim fencing, failure taxonomy, and P7-005 correlation context; the
  translation job keeps its `timeout` from `TranslationQueueConfig`;
  transcription gains an equivalent derived job timeout satisfying the
  invariant (no behavior change to inference itself).
- Supervision layering: P7-003 owns worker-lifecycle semantics (what runs,
  restart/recovery behavior, health signals); P7-008 owns deployment
  mechanics (unit files installed/enabled, machine topology, release
  process). The handoff artifact is a supervision specification both tasks
  reference; P7-003 writes the specification, P7-008 implements the
  installation.
- Queue payloads remain server-controlled identifiers only; no media bytes
  on the queue.

## 9. Detailed Implementation Requirements

1. Production env contract (documented, e.g. `.env.example` updates +
   runbook): `QUEUE_CONNECTION=redis`; Redis host/port/password/TLS per
   TD-004 posture; `REDIS_QUEUE_RETRY_AFTER` / `DB_QUEUE_RETRY_AFTER`
   guidance; per-queue overrides.
2. Transcription guard + shared invariant helper: boot check
   provider < job < retry_after for the effective transcription
   connection; `sync`/`null` rejected for both queues outside testing.
3. Scheduler entry for transcription stale recovery with the same
   overlap protection as translation.
4. Supervision specification document (consumed by P7-008): unit/service
   definitions per queue worker + scheduler, restart policy, graceful-stop
   timeout ≥ job timeout + margin, log routing into the P7-005 structured
   channel, health-signal expectations (process presence + queue depth +
   failed-job growth).
5. Failed-job runbook section: list/inspect/retry/forget procedures with
   correlation-field examples.
6. Local-dev/test behavior: `database` (or fake) remains the dev/test
   default; Redis path exercised via explicit env; no developer-machine
   supervisor required.
7. Rollback: reverting to the prior driver must be a config-only change
   with drained queues; document the drain procedure (stop dispatch →
   drain → verify empty → switch → restart).

## 10. Failure / Recovery Semantics

- Worker crash mid-job: redelivery is safe via claim fencing
  (duplicate delivery observes non-`Queued` attempt and no-ops) and
  idempotent result writers; proven by test, not asserted.
- Supervisor restart: workers resume consumption with no manual
  intervention; in-flight-at-kill jobs are redelivered, never lost, never
  double-applied.
- Redis outage: jobs wait (no silent drop); on reconnect, consumption
  resumes; outage/triage steps in the runbook.
- Graceful termination: SIGTERM stops accepting new jobs and lets the
  current job finish within its timeout; SIGKILL path covered by
  redelivery safety.
- Retry semantics unchanged: `tries = 1`; retries happen only through the
  explicit domain retry actions (`TranscriptionRetry`, translation
  re-dispatch), never via framework redelivery.

## 11. Security / Privacy Requirements

- Redis posture per TD-004: password/auth required whenever Redis leaves
  loopback or is shared; loopback-only dev documented; no credentials in
  logs or the failed-job payloads.
- Worker tokens (`RTFTT_*_WORKER_TOKEN`) unchanged; this task must not
  weaken worker authentication.
- Failed-job records contain identifiers only (already the case); runbook
  must warn against pasting payloads with secrets.

## 12. Observability / Operations Requirements

- Consume P7-005 conventions unchanged (field names, JSON channel,
  diagnostics command patterns); extend diagnostics output per §6.9.
- Operational alerts defined (not implemented as backends): queue depth
  growth, failed-job growth, worker-process absence, retry_after
  violations at boot.
- Runbook additions: supervision spec, drain/switch procedure, Redis
  outage triage, failed-job handling. (Physical runbook location follows
  the P7-008 runbook structure; this task contributes the queue chapter.)

## 13. Acceptance Criteria

- AC1: Production env contract sets `QUEUE_CONNECTION=redis` with
  documented Redis settings; dev/test defaults unchanged.
- AC2: Boot fails fast when the effective transcription or translation
  connection violates provider < job < retry_after.
- AC3: Boot fails fast when `sync`/`null` is selected for either queue
  outside a test environment; existing test fakes still pass.
- AC4: Supervision specification exists and names every required process,
  restart policy, graceful-stop budget, and health signal.
- AC5: Transcription stale-recovery is scheduled with overlap protection
  at a cadence safe against the stale threshold.
- AC6: Killing a worker mid-job (SIGKILL) leads to redelivery with
  exactly-once effect (fencing + idempotency proven by test).
- AC7: Failed jobs are visible with correlation fields and the runbook
  list/inspect/retry/forget procedure works against a real Redis-backed
  run.
- AC8: SIGTERM drains the current job without mid-inference kill within
  the documented budget.
- AC9: Rollback drain procedure documented and dry-run verified
  (config-only switch, empty-queue verification).
- AC10: Full regression suite green; Pint clean; PHPStan 0 errors; no
  Horizon dependency introduced; no P6 editing/revision/export
  regression.

## 14. Test / Verification Requirements

- Automated: guard unit tests (invariant violation, `sync` rejection per
  queue); scheduler registration test; fencing/redelivery tests under the
  Redis driver; failed-job visibility tests.
- Integration: real-Redis run (dev Redis acceptable) proving
  dispatch → work → complete for both queues, plus kill-recovery and
  stale-recovery paths; translation queue reconciliation against P5-003
  semantics.
- Failure-path: retry_after violation, Redis-unreachable dispatch
  behavior, supervisor-absent degradation documented (not simulated beyond
  what is safe).
- Regression: full PHP suite; Phase 6 editing/revision/export suites
  explicitly green (queue change must not alter domain behavior).
- Manual/operational: runbook procedures executed once against the
  verification environment and results retained.

## 15. Technical Debt Mapping

- TD-003 (HIGH/YES): owner-policy direction set by D7-02/A; this task is
  the remediation owner — closes the supervision/recovery gap and the
  `sync` exemption. Status may move toward CLOSED only with
  implementation + independent VERIFIED evidence, by HPO closure.
- TD-004 (MEDIUM/CONDITIONAL): split boundary — P7-003 defines the
  required Redis posture (auth/network/TLS rules, loopback exception);
  P7-001 certifies the deployed environment. This task must not claim
  TD-004 closed for environments it does not certify.
- TD-001/TD-002: inform but are not remediated here (G-12 chain and 500
  MiB proof belong to P7-012/P7-009/P7-001).

## 16. Risks / Regression Concerns

- Changing the default driver can strand in-flight `database`-driver jobs
  during rollout → drain procedure (§9.7) is mandatory, not optional.
- `retry_after` misconfiguration causes duplicate inference (expensive
  large-v3 runs) → boot guard + margin rationale required.
- Transcription guard addition must not break existing tests that rely on
  fake/sync drivers → exemption scoped to test environments only.
- Supervisor spec without P7-008 coordination could drift from deployed
  reality → shared specification artifact is the single source both tasks
  reference.

## 17. Completion Evidence Required

- Contract diff (this file) + implementation diff + test results
  (targeted, integration incl. real-Redis run, full suite counts) + Pint
  + PHPStan + supervision spec + runbook chapter + retained operational
  verification notes, all referenced from the builder report.

## 18. Reviewer Checklist

- [ ] No Horizon introduced; no new queue external dependency.
- [ ] Invariant holds for both queues with margin; rationale recorded.
- [ ] `sync`/`null` rejected outside tests; test fakes unaffected.
- [ ] Redelivery safety proven by test, not assertion.
- [ ] P7-003/P7-008 boundary respected (spec here, installation in P7-008).
- [ ] P7-005 field names and diagnostics conventions untouched.
- [ ] No P7-001/P7-002 scope absorbed; no domain-behavior change.
- [ ] Full suite + targeted + integration evidence independently
  reproducible.

## 19. State Transition Rule

- `BACKLOG / CONTRACT_REQUIRED → READY`: requires (a) this canonical
  contract, (b) dependency reconciliation recorded (D7-02 resolved;
  P7-005 DONE consumed), and (c) an explicit HPO READY promotion
  (`DECISION-P7-003-READY-001` or batch equivalent). Contract authoring
  alone does not promote.
- `READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`: per
  `.ai/guidelines/orchestration-policy.md` — HPO assigns (or authorizes
  self-assignment); OpenCode implements; Claude Code reviews (VERIFIED /
  CHANGES_REQUESTED, max 3 cycles then BLOCKED); HPO closes VERIFIED as
  DONE. OpenCode must not self-verify or self-close. READY does not equal
  execution authorization: IN_PROGRESS additionally requires the separate
  HPO Wave 1 execution authorization.
