# P7-005 — Observability Foundation

## Status

DONE — closed by the Human Product Owner on 2026-09-23
(`DECISION-P7-005-CLOSURE-001`) on the basis of the fresh independent corrective
re-review VERIFIED verdict
(`reviews/P6-006-P7-005-corrective-independent-re-review.md`; no
BLOCKER/HIGH/MEDIUM). All original review findings and corrective artifacts are
preserved unchanged. Residual INFO debt is carried forward non-blocking
(`DECISION-PHASE6-7-DEBT-CARRYFORWARD-001`); P7-005 is not reopened for it.

- Corrective cycle: H-1 (translation failure log enrichment), M-1 (best-effort
  log context), M-2 (separate correlation fields), M-3 (correlation header
  coverage) and the LOW items were addressed. See "Corrective Cycle" below.
- Original implementation: structured logging, request correlation, job
  observability, health diagnostics, and a runbook added.

## Review

Review Files:
`reviews/P7-005-independent-review.md` (CHANGES_REQUESTED, original);
`reviews/P6-006-P7-005-corrective-independent-re-review.md` (VERIFIED,
corrective re-review).

Review Status: VERIFIED (2026-09-23). No BLOCKER/HIGH/MEDIUM. Non-blocking
carry-forward (INFO): ADR-017 `device` field is absent across the whole app (not
a P7-005 defect; out of scope — P7-005 emits the minimum fields "where
available"); pre-existing full-suite order-dependent flakiness.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 7 — Production Hardening (early-hardening only,
`DECISION-P7-005-AUTHORIZATION-001`; ADR-023)

## Objective

Establish a product-semantic-neutral observability foundation: structured
application logging, request/correlation identifiers, transcription/translation
job observability, queue/runtime telemetry, health/operational diagnostics,
error-classification visibility, and documentation/runbook conventions.

## Scope

1. **Structured logging channel:** add a process-agnostic structured channel
   (single-line JSON formatter) in `config/logging.php`, selectable via env,
   without changing the default channel behavior.
2. **Correlation middleware:** assign a per-HTTP-request correlation id
   (`X-Request-Id` honored when supplied, otherwise generated) and expose it to
   logs and to the response header. Domain-level `request_id` values (invocation
   transport) remain authoritative for worker calls and are not replaced.
3. **Job observability:** enrich existing transcription/translation job log
   records with the ADR-017 minimum correlation fields where they are available
   (`request_id`, `transcription_id`, `processing_job_id`, `media_file_id`,
   `stage`, `attempt_number`, `model`, `duration_ms`, `failure_code`), without
   changing job behavior.
4. **Health / operational diagnostics:** add an Artisan diagnostics command
   reporting queue connection/name, timeout invariant, stale-recovery schedule,
   worker reachability, and logging channel state; no new public route is added.
5. **Error classification visibility:** ensure the existing failure-taxonomy
   classes remain the single classification source; expose safe classification
   in the diagnostics/log records (no provider-contract change).
6. **Runbook:** add an `OBSERVABILITY.md` runbook documenting log channels,
   correlation fields, diagnostics command, and conventions.

## Non-Scope

- datastore / storage architecture; tenancy; authorization; provider contracts;
  retention semantics (must not be redesigned);
- external metrics/tracing backends (Prometheus/OTel/Sentry/Horizon);
- changing job execution, retry, or queue semantics;
- changing the worker runtime contract or model identity;
- public health routes or production deployment.

## Dependencies

- None on Phase 6; independent of final P5/P6 schemas.
- ADR-017 minimum correlation fields; P3 logging/job contracts.

## Acceptance Criteria

1. A structured (JSON) log channel exists and is selectable without changing the
   default channel.
2. Each HTTP request carries a correlation id (honored/echoed in
   `X-Request-Id`), visible in log context.
3. Transcription/translation job logs include the available ADR-017 correlation
   fields without altering job behavior.
4. A diagnostics Artisan command reports queue/runtime/health state and exits
   non-zero only on an explicitly configured failure condition.
5. Failure taxonomy remains the single classification source.
6. An observability runbook exists.
7. No datastore/storage/tenancy/authorization/provider/retention change.
8. Pint, PHPStan, and the full regression suite pass.

## Verification Requirements

Feature tests (middleware correlation, logging channel config) + console command
test. Independent review. No real-service requirement.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

No.

## Owner Decision Dependencies

DC-01 (browser governance default; not applicable). ADR-017; ADR-025.

## Corrective Cycle (2026-09-23)

Responds to `reviews/P7-005-independent-review.md` (CHANGES_REQUESTED).

- **H-1 — translation failure enrichment.** `ProcessTranslation` failure records
  (`Translation job failed.`, `Translation failure ignored for a superseded
  attempt.`) and the unexpected-error records now carry `LogContext` fields
  (`translation_id`, `transcription_id`, `target_language`, `model`) and
  `failure_code`; transcription unexpected-error records were enriched the same
  way. Added `JobLogRecordTest` that inspects the actual emitted log records.
- **M-1 — best-effort log context.** `LogContext` never throws: model access and
  the attempt-ordinal lookup are defensive and degrade to a partial context. The
  transcription job computes the ordinal once after the claim and passes it, so
  logging paths no longer repeat the query. Added failure-path tests (including
  an injected ordinal-lookup failure) proving a job still completes.
- **M-2 — distinct correlation fields.** `http_request_id` (HTTP),
  `request_id` (ADR-017 worker transport id), and `queue_job_id` (framework
  queue id) are now separate. The dispatchers propagate `http_request_id` into
  the queued job payload explicitly. No distributed tracing introduced.
- **M-3 — correlation header coverage.** `AssignRequestId` is registered as
  global (prepended) middleware, so `X-Request-Id` is present on matched routes,
  404s, 419s, and `/up`. Regression tests cover each.
- **LOW.** Strict `\z` end anchor; inbound-id acceptance/normalization,
  `duration_ms` semantics, retention, and redundant-key aliases documented in
  `OBSERVABILITY.md`; diagnostics schedule derived from the live scheduler;
  `--probe-worker` coverage added (reachable / unreachable / unconfigured /
  `--strict`).

## Cross-Task Contract Notes (for later P6/P7 contracts; not implemented here)

- **Phase 7 must settle correlation-field naming** (`http_request_id` vs
  `request_id` vs `queue_job_id`) before metrics/tracing work builds on it.
- HTTP→job correlation propagation is now explicit; broader distributed tracing
  remains out of scope and requires separate authorization.