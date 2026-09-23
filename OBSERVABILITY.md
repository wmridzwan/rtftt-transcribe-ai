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
- Retention: the `structured` channel is a `daily` driver with
  `days = LOG_STRUCTURED_DAYS` (default `7`). Operational log retention is an
  operator concern, not data retention; set `LOG_STRUCTURED_DAYS` to match the
  local retention policy. The default `daily`/`single` channels are unchanged.
- The channel uses Monolog's `JsonFormatter` in newline batch mode, so each log
  record is one JSON object per line for ingestion.

## 2. Correlation identifiers

Distinct field semantics (P7-005 corrective M-2). The same field name is never
used for two identifier spaces:

| Field | Meaning | Source |
|---|---|---|
| `http_request_id` | Per-HTTP-request correlation id | `AssignRequestId` middleware; propagated into queued jobs |
| `request_id` | ADR-017 domain/transport id for an outbound worker call | `TranscriptionInvocation` / `TranslationInvocation` |
| `queue_job_id` | Framework queue job id | `$this->job->getJobId()` in the job |
| `processing_job_id` / `translation_id` | Domain attempt/translation id | Domain models |

- Every HTTP request is assigned an `http_request_id` by
  `App\Http\Middleware\AssignRequestId`, registered as **global (prepended)**
  middleware so the `X-Request-Id` header and log context apply to every HTTP
  response, including unmatched routes (404), CSRF failures (419), and the
  framework health endpoint `/up`.
- A well-formed inbound `X-Request-Id` (`[A-Za-z0-9._-]{8,128}`) is honored
  **as-is** (not normalized); anything else is replaced by a generated UUID.
  Client-supplied ids are accepted for correlation only; they are not used for
  authorization, idempotency, or any trust decision, and they can be chosen or
  collided by a caller (a forensic concern, not a security boundary).
- Domain/transport `request_id` values created by
  `TranscriptionInvocation` / `TranslationInvocation` remain authoritative for
  outbound worker requests and are never overwritten by `http_request_id`.
- When work moves from HTTP to the queue, the dispatcher copies the current
  `http_request_id` into the job payload. Job records then carry
  `http_request_id` (dispatch origin), `request_id` (worker transport), and
  `queue_job_id` together, so a request can be correlated to the queued work it
  produced. Broader distributed tracing is out of scope and not authorized here.

## 3. Job observability (ADR-017 minimum correlation)

`App\Observability\LogContext` assembles the available ADR-017 fields:

- `LogContext::forTranscription($transcription, $attempt, $extra)`:
  `transcription_id`, `media_file_id`, `model`, and — when an attempt exists —
  `processing_job_id`, `stage`, `attempt_number`.
- `LogContext::forTranslation($translation, $extra)`:
  `translation_id`, `transcription_id`, `target_language`, `model`.

`ProcessTranscription` and `ProcessTranslation` use these contexts on their
provider-invocation, completion, and failure records. Completion records also
carry `duration_ms`; failure records carry `failure_code`. The
`TranscriptionFailure` / `TranslationFailure` enums remain the single
classification source. No job, queue, retry, or persistence behavior changed.

- **Best-effort contract (M-1):** `LogContext` never throws. Model access and the
  attempt-ordinal lookup are defensive; a failure while building context
  degrades to a partial context and cannot fail a job, corrupt lifecycle state,
  or consume an attempt. The transcription job computes the ordinal **once**
  after the claim and passes it to each record, so logging paths do not repeat
  the query.
- **`duration_ms` semantics (L-3):** elapsed milliseconds from immediately
  before the provider invocation to immediately after result persistence. It
  excludes the claim and media-preparation steps, and is emitted only on the
  success record.
- **Redundant keys (INFO):** `processing_attempt_id` aliases `processing_job_id`,
  and `failure` aliases `failure_code`. Both are retained for backward
  compatibility; new consumers should prefer `processing_job_id` and
  `failure_code`.

## 4. Diagnostics

```bash
php artisan observability:diagnostics
php artisan observability:diagnostics --probe-worker
php artisan observability:diagnostics --strict
```

Reports the active log channel and whether the structured channel is
configured, the queue default/effective connection and queue name, the timeout
invariant (`provider < job < retry_after`), and the stale-recovery schedule
derived from the live scheduler state (`routes/console.php`).

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
