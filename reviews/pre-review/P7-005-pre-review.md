# P7-005 — Builder Pre-Review Handoff

Date: 2026-09-23
Task: P7-005 — Observability Foundation
Status: `IMPLEMENTED_PENDING_REVIEW`
Authority: `DECISION-P7-005-AUTHORIZATION-001`; ADR-023; ADR-017 observability
clause; ADR-025 (DC-01)
Reviewer: Claude Code (fresh independent review required)

## Scope discipline (product-semantic-neutral)

No datastore, storage, tenancy, authorization, provider-contract, or retention
change was made. No queue/job/retry/persistence behavior changed. No public
health route was added.

## What changed

1. `config/logging.php` — added a single-line JSON `structured` channel
   (`daily`, Monolog `JsonFormatter`, newline batch mode, `LOG_STRUCTURED_LEVEL`).
   Default channel behavior unchanged.
2. `app/Http/Middleware/AssignRequestId.php` — per-request correlation id
   (honors a well-formed inbound `X-Request-Id`, else UUID), added to log context
   and echoed on the response header; registered via `bootstrap/app.php`
   (`web(append:)`).
3. `app/Observability/LogContext.php` — assembles the available ADR-017 minimum
   correlation fields from domain models.
4. `app/Jobs/ProcessTranscription.php`, `app/Jobs/ProcessTranslation.php` —
   provider-invocation, completion (`duration_ms`), and failure (`failure_code`)
   records now carry `LogContext` fields. Failure enums remain the single
   classification source.
5. `app/Console/Commands/ObservabilityDiagnostics.php` — `observability:diagnostics`
   reports log channel/structured availability, queue/effective connection,
   timeout invariant, and stale-recovery schedule; `--probe-worker` (2 s HTTP) and
   `--strict` (non-zero only on a configured failure) options.
6. `OBSERVABILITY.md` — runbook.

## Deliberate design points

- `Log::withContext` is used only in the HTTP middleware (per-request lifecycle,
  app recreated per test/request); job log calls use explicit per-record arrays
  via `LogContext` to avoid cross-job context leakage in long-running workers.
- Domain/transport `request_id` values remain authoritative for worker calls; the
  middleware id is the per-HTTP-request correlation.
- Correlation values are identifiers/runtime metadata only; no secrets, tokens,
  or media paths are logged.

## Quality / evidence

- Full PHP suite: `643 tests, 642 passed, 1 skipped, 2 warnings, 0 failures`.
- New tests: `tests/Feature/Observability/RequestCorrelationTest.php` (4),
  `tests/Feature/Observability/LogContextTest.php` (4),
  `tests/Feature/Observability/ObservabilityDiagnosticsTest.php` (3).
- Pint clean; PHPStan 0 errors.

## Requested fresh-review focus

- no product-semantic or datastore/storage/tenancy/auth/provider/retention change;
- structured channel is additive and default behavior is unchanged;
- correlation id generation/honoring and log-context behavior;
- job log enrichment does not alter job behavior;
- diagnostics exits non-zero only under `--strict` with a real failure condition.

P7-005 is not VERIFIED and not DONE. The implementer must not self-verify.
