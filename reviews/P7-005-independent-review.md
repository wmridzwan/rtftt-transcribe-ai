# P7-005 — Independent Review (Observability Foundation)

Reviewer: Claude Code (fresh independent review context; did not implement)
Date: 2026-09-23
Task: `tasks/P7-005-observability-foundation.md`
Implementation commit: `91b030b`
Authority reviewed: `DECISION-P7-005-AUTHORIZATION-001`, ADR-017 (minimum
correlation fields), ADR-023, ADR-025

This review does not modify code, tests, task status, or governance state and
does not mark P7-005 DONE.

## Verdict

**CHANGES_REQUESTED** — one HIGH finding (acceptance criterion 3 unmet and the
runbook misstates committed behavior), plus MEDIUM findings.

## Findings

### BLOCKER

None.

### HIGH

**H-1 — Translation failure records were not enriched. The runbook and
pre-review claim they were.** `app/Jobs/ProcessTranslation.php:263-276`: the
`Translation job failed.` and `Translation failure ignored for a superseded
attempt.` records still carry only `translation_id`, `failure`, and `message`.
They have no `LogContext::forTranslation()` fields (`transcription_id`,
`target_language`, `model`) and no `failure_code`. Only the transcription
failure record got `failure_code` (`ProcessTranscription.php:263-268`).

However, `OBSERVABILITY.md` §3 states that "`ProcessTranscription` and
`ProcessTranslation` use these contexts on their provider-invocation,
completion, and failure records… failure records carry `failure_code`". The
pre-review (item 4) makes the same claim. An operator filtering structured logs
on `failure_code`/`transcription_id` will silently miss every translation
failure, which is the most important record for this foundation. Acceptance
criterion 3 is therefore not met, and the runbook does not match committed
behavior.

There is no test that asserts job log records. `LogContextTest` only exercises
the builder, which is why this gap went unnoticed. Required: enrich the
translation failure records (and ideally the `Log::error` unexpected-failure
records in both jobs). Add `Log::spy`-based job tests covering
start/completion/failure fields. Correct the runbook to match.

### MEDIUM

**M-1 — The logging-context builder can now throw inside job control flow.**
`LogContext::forTranscription()` runs a DB `COUNT` query (`attempt_number`).
It is evaluated at the provider-start log (after the attempt is claimed), at
completion (after the transcript is persisted), and in `fail()` (after
`databaseFail()` commits). A transient DB error there (SQLite, the default
`DB_CONNECTION`) now propagates out of `handle()` with `$tries = 1`. That can
leave a claimed attempt in `transcribing` until stale recovery, or put a
successfully persisted job in `failed_jobs`.

Before P7-005 these log calls could not throw. This conflicts with the
"observability must not break normal behavior" requirement and the
"no job behavior change" claim. Compute the ordinal defensively
(try/catch → `null`), or derive it once before the claim.

**M-2 — The log key `request_id` means two different identifiers.** The
middleware shares `request_id` = HTTP correlation id via `Log::withContext`.
The job records put `request_id` = worker transport id (`$invocation->requestId`)
in the per-record context, which overrides the shared value on merge (visible
under the `sync` queue driver or any in-request dispatch). In a single
ingestion index, the same field carries unrelated id spaces. No HTTP-to-job
propagation exists, so "all logs for request X" cannot be answered. Use
distinct keys (for example `http_request_id` / `worker_request_id`) before
ingestion pipelines depend on the name. Changing this later is costly.

**M-3 — Not every HTTP request carries a correlation id (AC2).**
`AssignRequestId` is appended to the `web` group, so it runs only after the
route matches and after the earlier web middleware. Reproduced against the
running app: `404` (unmatched route), `419` (CSRF on `POST /logout`), and
`GET /up` responses have no `X-Request-Id`. Exceptions raised in
cookie/session/CSRF middleware are logged without `request_id`. Register the
middleware globally (prepended) if AC2 is meant literally, or narrow AC2/the
runbook to "requests reaching web routes".

### LOW

- **L-1 — The validation regex uses `$`, which accepts a trailing newline.**
  `/^[A-Za-z0-9._-]{8,128}$/` matches `"abcdefgh\n"` (reproduced:
  `preg_match` → 1; with `\z` → 0). This is not reachable over real HTTP (a
  header line cannot contain LF), and JSON/Line formatters escape it. As
  defense-in-depth for the header echo and log context, use `\z` or the `D`
  modifier.
- **L-2 — The inbound id is trusted from any client.** A caller can choose or
  collide correlation ids, which is a forensic-spoofing concern rather than an
  authorization issue. The id is not used for any authorization or idempotency
  decision (verified: no other reader of the `request_id` attribute). Document
  this in the runbook, or honor inbound ids only from trusted proxies.
- **L-3 — `duration_ms` semantics are undefined.** It measures provider
  invocation plus result persistence, not whole-job time (claim and preparation
  are excluded), and it is emitted only on success. State this in the runbook.
- **L-4 — The diagnostics schedule line is hardcoded.**
  `Schedule: translation:recover-stale-attempts (every minute, without overlapping)`
  is a literal, not read from the scheduler, so it can drift from
  `routes/console.php`.
- **L-5 — There is no test for `--probe-worker`** (`Http::fake` success,
  failure, and unconfigured; `--strict` exit on an unreachable worker).
- **L-6 — The structured channel's retention is undocumented.** The `daily`
  driver has no `days` key, so Laravel's default of 7 days applies. Log
  retention is operational rather than data retention, but the runbook should
  state it.
- **INFO — Redundant keys.** `processing_attempt_id` and `processing_job_id`,
  and `failure` and `failure_code`, are duplicated on the same records.
  Harmless and backward-compatible. Note the aliasing in the runbook.

## Acceptance Criteria

1. Structured JSON channel, default unchanged — **MET** (`logging.default`
   stays `stack`; channel is opt-in).
2. Each HTTP request carries a correlation id, echoed and in log context —
   **PARTIAL** (M-3). It holds for matched web routes (header echoed; valid
   inbound honored; malformed `bad id <script>` replaced by a UUID —
   reproduced).
3. Job logs include the available ADR-017 fields without altering behavior —
   **NOT MET** (H-1; M-1 behavioral risk).
4. Diagnostics reports state; non-zero only on a configured failure — **MET**
   (reproduced: exit 0 without and with `--strict` on a consistent config;
   feature test covers the violation path). The command is non-mutating: config
   reads plus optional GET `/health`, which exists at `worker/main.py:132`.
   Worker tokens are not printed.
5. Failure taxonomy remains the single classification source — **MET**
   (`failure_code` = enum value; no new taxonomy).
6. Runbook exists — **MET, but inaccurate** (H-1, L-3, L-6).
7. No datastore/storage/tenancy/auth/provider/retention change — **MET.**
8. Pint, PHPStan, full suite — **MET** (reproduced).

## Scope / Safety

- No schema, storage, tenancy, policy, provider-contract, queue/retry, or data
  retention change. No public route was added. `bootstrap/app.php` only appends
  web middleware.
- Sensitive data: the new records carry ids, model names, language, stage, and
  timings only. There are no bearer tokens, worker tokens, storage paths, or
  transcript text. Pre-existing `message` fields are the existing safe messages.
- **P7-005 stayed within early-hardening scope.** The defects are correctness
  and consistency issues inside that scope, not scope creep.

## Evidence Independently Reproduced

| Evidence | Result |
|---|---|
| Targeted tests (P6-006 + `tests/Feature/Observability`) | 17 passed |
| Full suite `php artisan test --compact` | 643 tests, 642 passed, 1 skipped |
| `vendor/bin/pint --test` (read-only) | passed |
| `vendor/bin/phpstan analyse` | 0 errors |
| `php artisan observability:diagnostics` / `--strict` | exit 0 / exit 0; output as documented |
| Real HTTP (`php -S`, isolated scratch DB) | `X-Request-Id` generated; valid inbound echoed; invalid replaced; absent on 404, 419, `/up` |
| Regex probe | `$` accepts trailing `\n`; `\z` rejects |
| Not reproduced | `--probe-worker` against a live worker (no worker running) |

## Implications for later Phase 7 work

- Settle the correlation-key naming (M-2) and whether correlation is global
  (M-3) before P7 metrics/tracing tasks build on these fields.
- Queue-to-job correlation propagation (HTTP id into dispatched job payloads) is
  absent. It is a natural follow-up candidate, and needs its own authorization.
- Any P6 revision/translation-invalidation work should reuse `LogContext`
  rather than ad-hoc arrays, and should include log-record tests.

## Conclusion

**CHANGES_REQUESTED.** Return to the implementation owner (OpenCode) for H-1 and
M-1..M-3. Then run a fresh re-review. P7-005 is not safe for HPO closure.
