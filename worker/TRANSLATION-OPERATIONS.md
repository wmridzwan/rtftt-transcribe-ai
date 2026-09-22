# Translation Worker — Operational Contract (P5-004C)

This is the canonical operational contract for running **translation** jobs.
The generic worker command is **not** sufficient: it listens to the default
queue only and would never consume the translation queue.

## Queue and worker command

Translation jobs are dispatched to the `translation` queue with a small
identifier payload and the attempt token.

```bash
# canonical translation worker (queue name + timeout + attempts must be explicit)
php artisan queue:work --queue=translation --timeout=330 --tries=1
```

- `--queue=translation` — required; translations are not on `default`.
- `--timeout=330` — must match `config('translation.job_timeout_seconds')`.
- `--tries=1` — Phase 5 is manual-retry only; the job does not auto-retry.

`composer run dev` and a bare `php artisan queue:work` consume the `default`
queue only and are therefore **not** sufficient for translation.

## Timeout reconciliation

| Value | Source | Default | Meaning |
|---|---|---|---|
| Provider timeout | `translation.timeout_seconds` | 300 s | maximum provider/model inference time |
| Job timeout | `translation.job_timeout_seconds` | 330 s | per-job worker timeout (provider + 30 s overhead) |
| Required `retry_after` | `translation.retry_after_seconds` | 420 s | minimum queue connection `retry_after` (job + 90 s margin) |
| Stale recovery threshold | `translation.attempt_stale_seconds` | provider + 60 = 360 s | a `translating` row older than this is recoverable |

Invariant: **provider (300) < job timeout (330) < retry_after (420)**.
A running translation is never re-delivered while it is still executing.

Set the queue connection `retry_after` to at least 420, e.g.:

```dotenv
# Redis queue connection used by translations
RTFTT_TRANSLATION_QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=420
```

The effective connection is `translation.queue_connection` when set, otherwise
`QUEUE_CONNECTION` (`config('queue.default')`) — the same fallback the dispatcher
uses. The committed defaults for the `database` and `redis` connections are
therefore 420 s (`DB_QUEUE_RETRY_AFTER` / `REDIS_QUEUE_RETRY_AFTER`), so the
shipped configuration is compliant even when no translation override is set.

`TranslationQueueConfig::assertConsistent()` fails application boot when the
**effective** connection has `retry_after` below the required value (drivers
without `retry_after`, e.g. `sync`, are exempt).

## Stale-attempt recovery

`translation:recover-stale-attempts` is scheduled every minute
(`routes/console.php`). It moves a demonstrably stale `translating` row to
`failed` with `PROVIDER_TIMEOUT` (retryable), fenced by the attempt token, so it
can never affect a newer attempt. A killed job is thus recoverable within about
one minute after the stale threshold, and the user can retry.

```bash
php artisan schedule:list          # confirm the schedule
php artisan translation:recover-stale-attempts   # run on demand
```

## Persistence-failure taxonomy

Transient persistence/concurrency failures (e.g. a lost SQLite write lock)
during result persistence are mapped to the retryable `PROCESSING_FAILED`.
Deterministic integrity/constraint failures remain non-retryable
`PERSISTENCE_FAILED`.

## Failure→retryability reference

- Retryable: `PROVIDER_UNAVAILABLE`, `PROVIDER_TIMEOUT`, `PROVIDER_FAILED`,
  `PROCESSING_FAILED`.
- Non-retryable: `MALFORMED_OUTPUT`, `MISSING_SEGMENTS`, `UNSUPPORTED_SOURCE`,
  `INVALID_REQUEST`, `PERSISTENCE_FAILED`, `CONFIGURATION_ERROR`.

## Real-model gate

This runbook does **not** satisfy the P5-008 real provider/model integration
gate (ADR-022 D5-09); it covers runtime prerequisites only.