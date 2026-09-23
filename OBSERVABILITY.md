# Observability Foundation — Operations Runbook

Task: P7-005 (Phase 7 early-hardening; `DECISION-P7-005-AUTHORIZATION-001`).
Status: Observability foundation only. It is product-semantic-neutral and does
not redesign datastore, storage, tenancy, authorization, provider, or retention
semantics.

## 1. Structured logging

- Default channel remains `LOG_CHANNEL=stack` (`LOG_STACK=single`).
- A single-line JSON channel is available as `structured` (daily rotation to
  `storage/logs/structured.log`):
  - select globally with `LOG_CHANNEL=structured`;
  - or add it to a stack with `LOG_STACK=single,structured`.
- Level: `LOG_STRUCTURED_LEVEL` (default `info`).
- The channel uses Monolog's `JsonFormatter` in newline batch mode, so each log
  record is one JSON object per line for ingestion.

## 2. Correlation identifiers

- Every HTTP request is assigned a correlation id by
  `App\Http\Middleware\AssignRequestId` (appended to the `web` group):
  - a well-formed inbound `X-Request-Id` (`[A-Za-z0-9._-]{8,128}`) is honored;
  - otherwise a UUID is generated;
  - the id is added to the log context and echoed on the response
    `X-Request-Id` header;
  - the same value is available as the request attribute `request_id`.
- Domain/transport `request_id` values (the UUIDs created by
  `TranscriptionInvocation` / `TranslationInvocation` for worker calls) remain
  authoritative for outbound worker requests; the middleware id is the
  per-HTTP-request correlation and does not replace them.

## 3. Job observability (ADR-017 minimum correlation)

`App\Observability\LogContext` assembles the available ADR-017 fields:

- `LogContext::forTranscription($transcription, $attempt, $extra)`:
  `transcription_id`, `media_file_id`, `model`, and — when an attempt exists —
  `processing_job_id`, `attempt_number`, `stage`.
- `LogContext::forTranslation($translation, $extra)`:
  `translation_id`, `transcription_id`, `target_language`, `model`.

`ProcessTranscription` and `ProcessTranslation` use these contexts on their
provider-invocation, completion, and failure records. Completion records also
carry `duration_ms`; failure records carry `failure_code`. The
`TranscriptionFailure` / `TranslationFailure` enums remain the single
classification source. No job, queue, retry, or persistence behavior changed.

## 4. Diagnostics

```bash
php artisan observability:diagnostics
php artisan observability:diagnostics --probe-worker
php artisan observability:diagnostics --strict
```

Reports the active log channel and whether the structured channel is
configured, the queue default/effective connection and queue name, the timeout
invariant (`provider < job < retry_after`), and the stale-recovery schedule.

- `--probe-worker` performs a short (2 s) HTTP probe of the configured
  transcription/translation `/health` endpoints.
- `--strict` exits non-zero when a consistency check fails (timeout violation or
  an unreachable probed worker). Without `--strict`, diagnostics always exit
  `0` and report findings.

## 5. Conventions

- Prefer contextual arrays over interpolated strings for machine-readable logs.
- Include the applicable `LogContext` fields on job lifecycle records.
- Never log secrets, bearer tokens, or raw media paths.
- New operational documentation belongs in a runbook; this file is the
  observability entry point.
