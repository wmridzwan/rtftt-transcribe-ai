# P7-005 — Observability Foundation

## Status

IMPLEMENTED_PENDING_REVIEW — implementation complete 2026-09-23. Contract
authored and implemented; structured logging, request correlation, job
observability, health diagnostics, and a runbook added. 11 focused tests pass
(full suite 643/642); Pint clean; PHPStan 0. Fresh independent review pending.
Not VERIFIED; not DONE.

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