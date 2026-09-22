# P5-004C — Independent Review

Task: P5-004C — Phase 5 Operational Pre-Flight
Reviewer: Claude Code (independent reviewer; did not implement this task)
Date: 2026-09-22
Commits reviewed: `48dfa44` (sqlite busy_timeout), `e689389` (operational pre-flight)
Pre-review: `reviews/pre-review/P5-004C-pre-review.md` (OpenCode, PRE_REVIEW_PASS)

**Verdict: CHANGES_REQUESTED**

One BLOCKER: the queue `retry_after` reconciliation this task exists to
guarantee is not actually enforced against the repository's own shipped,
committed configuration. The boot-time guard silently no-ops for the
default/current `.env`, and a real (non-test) `php artisan` boot confirms it.
Everything else reproduced cleanly, including all three requested mutation
probes (all killed).

## Acceptance criteria

| # | Criterion | Status | Evidence |
|---|---|---|---|
| 1 | `busy_timeout` committed, evidenced independent of dirty baseline | MET | `config/database.php` diff (`48dfa44`); `TranslationOperationalPreflightTest` `PRAGMA busy_timeout` test passes |
| 2 | `$timeout` explicit; job timeout < retry_after; `failed()` token-fenced | PARTIALLY MET | Config values satisfy `300 < 330 < 420` in isolation (see BLOCKER below for why this is not sufficient); `failed()` fence confirmed by M2 mutation kill |
| 3 | Worker runbook documents queue command + provider/job/retry_after; generic worker explicitly insufficient | PARTIALLY MET | `worker/TRANSLATION-OPERATIONS.md` exists and states this, but its "canonical command" section omits the queue-connection env var that the reconciliation actually depends on (see BLOCKER) |
| 4 | `translation:recover-stale-attempts` scheduled, token-fenced, evidence retained | MET | `php artisan schedule:list` output below; recovery re-uses the same token-fenced compare-and-set (pre-existing `StaleTranslationAttemptRecovery`, unchanged by this task) |
| 5 | Transient lock → retryable; deterministic constraint → non-retryable | MET | `TranslationHardeningTest` transient/deterministic tests pass; M3 mutation kill confirms the branch is load-bearing |
| 6 | Post-claim `refresh()` failure path covered by test | MET | `TranslationHardeningTest::it_records_a_post_claim_refresh_failure...` passes |
| 7 | Tests, Pint, PHPStan pass; scheduler inspection recorded | MET | See reproduced results below |

## Reproduced vs implementer claim

| Check | Implementer claim | Reproduced | Match |
|---|---|---|---|
| Translation suite | 188 passed, 716 assertions | 188 passed, 716 assertions, 14999ms | Yes |
| Concurrency races ×3 (claim/retry/request) | passing | 3/3 runs: 3 tests, 3 passed, 37 assertions each | Yes |
| Pint (changed files) | clean | `{"tool":"pint","result":"passed"}` | Yes |
| PHPStan | 0 errors | `{"tool":"phpstan","result":"passed","errors":0}` | Yes |
| `schedule:list` | `translation:recover-stale-attempts` every minute | `* * * * * php artisan translation:recover-stale-attempts .. Next Due: 17 seconds from now` | Yes |
| Worker pytest | 42 passed | 42 passed, 2 warnings, 1.09s | Yes |
| Full suite | 621 tests, 620 passed, 1 skipped, 3 warnings, 0 failures | 621 tests, 620 passed, 1 skipped, **2 warnings**, 2169 assertions | Off by one warning (see LOW finding) |

All commands above were run in the foreground against the actual repo working
tree at HEAD (`4cd49f4`), not copied from the pre-review artifact.

## Mutation results (scratch copy, `git archive HEAD` into a temp dir; repo untouched)

- **M1** — `TranslationQueueConfig::jobTimeoutSeconds()` hardcoded to `500`
  (ignoring config, structurally exceeding the then-recomputed
  `requiredRetryAfterSeconds()`... except `requiredRetryAfterSeconds()` derives
  from `jobTimeoutSeconds()`, so the *invariant* can never be violated by this
  method alone — only the explicit hardcoded-value tests can catch drift).
  **KILLED**: `TranslationHardeningTest` (`job timeout... (P5-004C)`, asserts
  `330`) and `TranslationOperationalPreflightTest` (`reconciles provider, job
  and retry_after defaults`, asserts `330`/`420`) both failed
  (`Failed asserting that 500 is identical to 330`). This confirms the tests
  pin concrete numbers rather than only the self-referential inequality.
- **M2** — removed the `->where('attempt_token', $this->attemptToken)`
  predicate from `fail()` (the query `failed()` delegates to). **KILLED**:
  `it does not let a killed stale job mark a newer attempt failed` failed —
  the newer attempt's status flipped from `Queued` to `Failed`, exactly the
  regression the fence exists to prevent.
- **M3** — forced the persistence-taxonomy branch to always assign
  `TranslationFailure::PersistenceFailed`, discarding the transient/
  deterministic classification. **KILLED**: `it maps a transient
  persistence/concurrency failure to a retryable failure` failed — the
  transient-lock case was marked `PersistenceFailed` instead of
  `ProcessingFailed`.

All three mutations were killed. The scratch copy was created via
`git archive HEAD | tar -x` plus a symlinked `vendor/` (no repo files were
edited); it was deleted after use.

## BLOCKER

**The queue `retry_after` invariant this task exists to guarantee is not
enforced against the app's actual, shipped default queue connection.**

`TranslationQueueConfig::consistencyViolation()` only checks
`translation.queue_connection` when that config key is a non-empty string. It
is `null` by default (`config/translation.php:41`,
`env('RTFTT_TRANSLATION_QUEUE_CONNECTION', env('RTFTT_TRANSCRIPTION_QUEUE_CONNECTION'))`),
and **neither `.env` nor `.env.example` sets either variable** — confirmed by
`grep`. When the connection is `null`, `consistencyViolation()` returns `null`
("exempt"), and `AppServiceProvider::boot()`'s `assertConsistent()` never
throws.

But `TranslationDispatcher::dispatch()` (unchanged by this task, line 78)
already documents the real behavior: when `translation.queue_connection` is
unset, jobs are pushed through `config('queue.default')`, not through a
connection that is exempt from any timing contract. In this repository's
committed `.env`, `QUEUE_CONNECTION=database`, and
`config('queue.connections.database.retry_after')` defaults to **90 seconds**
(`config/queue.php:43`, `env('DB_QUEUE_RETRY_AFTER', 90)`) — nowhere near the
required 420s, and below even the provider timeout (300s) and job timeout
(330s) this task just introduced.

Reproduced directly against the live app (not the test runner, since
`assertConsistent()` intentionally no-ops under `runningUnitTests()`):

```
$ php artisan tinker --execute="echo config('queue.connections.database.retry_after').PHP_EOL; \
    echo var_export(config('translation.queue_connection'), true).PHP_EOL; \
    echo var_export(App\Translation\TranslationQueueConfig::consistencyViolation(), true).PHP_EOL; \
    echo App\Translation\TranslationQueueConfig::requiredRetryAfterSeconds().PHP_EOL;"
90
NULL
NULL
420

$ php artisan about --only=environment   # a real, non-test boot — does not throw
 Environment .. local
 ...
```

So today, with the repository exactly as committed, a worker started per the
runbook's own canonical command
(`php artisan queue:work --queue=translation --timeout=330 --tries=1`, no
`--queue-connection`) dispatches through the `database` connection with a 90s
`retry_after`. A translation whose provider call runs anywhere near its 300s
timeout will be considered abandoned and become re-deliverable well before it
finishes — the exact hazard class `job timeout < retry_after` was written to
close — and the boot guard that is supposed to catch this misconfiguration
stays silent because it only checks an *explicitly configured* override, not
the connection that is actually in effect.

This is not a hypothetical: it is the default state of this repository right
now. It directly undercuts AC2, AC3, and the stated purpose of this task
(closing operational hazards before P5-008's real-model gate, which will
introduce genuinely long-running provider calls). No existing test exercises
this path — every `consistencyViolation()`/`assertConsistent()` test
explicitly sets `translation.queue_connection` to a concrete value (`redis`,
`sync`), so none of them observe the unset/exempt + low-default-retry_after
combination that the shipped `.env` actually produces.

**Fix directions** (not prescriptive — HPO/implementation owner's call):
`configuredConnection()` could fall back to `config('queue.default')` instead
of returning `null` when the translation-specific override is unset (matching
`TranslationDispatcher`'s own fallback), and/or `.env`/`.env.example` /
`worker/TRANSLATION-OPERATIONS.md`'s canonical command could set
`RTFTT_TRANSLATION_QUEUE_CONNECTION` and a compliant `retry_after` explicitly
so the guard has something concrete to check by default.

## Other residual findings

- **LOW** — Full-suite warning count: reproduced 2 warnings vs. the claimed 3.
  Not a regression (fewer, not more), likely environment/ordering-sensitive;
  not blocking.
- **INFO** — `worker/TRANSLATION-OPERATIONS.md` has no trailing newline (cosmetic).
- **INFO** — The provenance note added to `tasks/P5-004B-pre-ui-hardening.md`
  is additive only; historical review verdicts were not altered. Consistent
  with the task's own scope item 7.

## P5-008 operational prerequisites

**Not satisfied**, pending the BLOCKER above. The reconciliation invariant
this task was scoped to guarantee is not actually protective under the
repository's own default configuration, and P5-008 will be the first place
genuinely long-running (real-model) provider calls make this hazard
observable in practice. Everything else in scope (busy_timeout,
`failed()` fencing, persistence taxonomy, stale-attempt scheduling, post-claim
refresh coverage) is verified and solid.

## Scope discipline

This review covers P5-004C only. No implementation code, tests, task files,
or governance files were modified. Mutation probes were performed exclusively
in a `git archive` scratch copy under the OS temp directory, which was deleted
after use. P5-008 and Phase 6/7 were not started.
