# P5-004C — Independent Re-Review (Cycle 2)

Task: P5-004C — Phase 5 Operational Pre-Flight
Reviewer: Claude Code (independent reviewer; did not implement this corrective)
Date: 2026-09-22
Commit reviewed: `5487852` — "P5-004C cycle 2: enforce retry_after on the effective connection"
Cycle 1 review: `reviews/P5-004C-independent-review.md` (CHANGES_REQUESTED, one BLOCKER)
Pre-review: `reviews/pre-review/P5-004C-cycle2-pre-review.md`

**Verdict: VERIFIED** (recommendation only — this task is not marked VERIFIED by this
artifact; that status change belongs to the governance record per
`.ai/guidelines/orchestration-policy.md`)

The cycle 1 BLOCKER is closed. `TranslationQueueConfig::effectiveConnection()` now
resolves the connection the dispatcher will actually use (explicit
`translation.queue_connection` override, else `config('queue.default')`), the guard
checks that effective connection, and the shipped `database`/`redis` `retry_after`
defaults were raised from 90s to 420s so the repository's own committed configuration
satisfies the invariant instead of merely being capable of satisfying it. No BLOCKER or
HIGH findings remain.

## Acceptance criteria

| # | Criterion | Status | Evidence |
|---|---|---|---|
| 1 | `busy_timeout` committed | MET | Unchanged since cycle 1; still passes (`TranslationOperationalPreflightTest`) |
| 2 | `$timeout` explicit; job timeout < retry_after; `failed()` token-fenced | MET | `provider(300) < job(330) < retry_after(420)` now holds under the *effective* connection, not just an explicit override (see BLOCKER-closure evidence below); `failed()` fence unchanged, still killed by mutation M2 in cycle 1 |
| 3 | Worker runbook documents queue command + provider/job/retry_after; generic worker explicitly insufficient | MET | `worker/TRANSLATION-OPERATIONS.md` now states the effective-connection fallback explicitly and that committed defaults are compliant even with no override set |
| 4 | `translation:recover-stale-attempts` scheduled, token-fenced, evidence retained | MET | Unchanged from cycle 1; not touched by this corrective |
| 5 | Transient lock → retryable; deterministic constraint → non-retryable | MET | Unchanged from cycle 1; not touched by this corrective |
| 6 | Post-claim `refresh()` failure path covered by test | MET | Unchanged from cycle 1; not touched by this corrective |
| 7 | Tests, Pint, PHPStan pass; scheduler inspection recorded | MET | See reproduced results below |
| — | **Cycle 1 BLOCKER**: retry_after invariant enforced against the *effective* default queue connection, not only an explicit override | MET | See "BLOCKER closure" below |

## BLOCKER closure — reproduced independently

**Code**: `effectiveConnection()` (`app/Translation/TranslationQueueConfig.php:51-62`)
falls back to `config('queue.default')` when `configuredConnection()` is `null`,
mirroring `TranslationDispatcher::dispatch()` (`app/Actions/TranslationDispatcher.php:28,37-38,78`),
which only calls `->onConnection($connection)` when an explicit override is set —
otherwise the job is pushed through the default connection, and the dispatcher's own
log line at line 78 already computed `config('queue.default')` as the fallback for
exactly this reason. `connectionRetryAfterSeconds()` and `consistencyViolation()` both
now call `effectiveConnection()` instead of `configuredConnection()`.

**Config**: `config/queue.php:43` (`database`) and `:71` (`redis`) now default
`retry_after` to 420 (was 90); `.env.example` documents
`RTFTT_TRANSLATION_QUEUE_CONNECTION=` (left empty — intentionally exercising the
fallback path) plus `DB_QUEUE_RETRY_AFTER=420` / `REDIS_QUEUE_RETRY_AFTER=420`.
`config/translation.php` is unchanged: `timeout_seconds` 300, `job_timeout_seconds`
330, `retry_after_seconds` 420 — `300 < 330 < 420` holds.

**Real (non-test) boot — reproduced directly**, PowerShell:

```
PS> $env:DB_QUEUE_RETRY_AFTER='90'; php artisan about --only=environment
   LogicException
  Translation queue connection "database" retry_after (90) is below the required 420;
  a running translation could be re-delivered.
  at app\Translation\TranslationQueueConfig.php:124
  1   app\Providers\AppServiceProvider.php:39
      App\Translation\TranslationQueueConfig::assertConsistent()
EXITCODE=1
```

```
PS> Remove-Item Env:\DB_QUEUE_RETRY_AFTER
PS> php artisan about --only=environment
 Environment .. local
 ...
EXITCODE=0
```

This is the exact scenario cycle 1 reproduced as silent/exempt (`consistencyViolation()`
returned `NULL` with no override set and `DB_QUEUE_RETRY_AFTER` unset, `about` did not
throw). It now throws with an unsafe override and boots cleanly with the committed
defaults — the guard is protective against the shipped configuration, not just against
explicitly misconfigured overrides.

## Reproduced vs implementer claim

| Check | Implementer claim | Reproduced | Match |
|---|---|---|---|
| Translation suite (`tests/Unit/Translation` + `tests/Feature/Translation`) | 191 | 191 passed, 724 assertions, 25747ms | Yes |
| Full suite | 624 tests, 623 passed, 1 skipped | 624 tests, 623 passed, 1 skipped, 2177 assertions, 2 warnings, 72218ms | Yes |
| Concurrency races ×3 (claim/retry/request) | 3/3 ×3 | 3 runs, each: 3 tests, 3 passed, 37 assertions (7416ms / 7355ms / 9706ms) | Yes |
| Pint (changed PHP files) | clean | `{"tool":"pint","result":"passed"}` | Yes |
| PHPStan | 0 errors | `php -d memory_limit=1G vendor/bin/phpstan analyse` → `{"tool":"phpstan","result":"passed","errors":0}` | Yes |
| Boot fails fast with unsafe effective connection | throws | Reproduced above | Yes |
| Boot succeeds with committed defaults | no throw | Reproduced above | Yes |

All commands were run in the foreground against the actual repo working tree at HEAD
(`5487852`), not copied from the pre-review artifact. `php` was resolved via the
PowerShell/Herd shim (not on the Git Bash `PATH` in this environment); `git archive` /
mutation work used the Bash (Git Bash) tool, since piping binary tar data through
PowerShell corrupts it.

## New tests — load-bearing

Three tests were added to `tests/Feature/Translation/TranslationOperationalPreflightTest.php`:

1. `it resolves the effective connection from the default when no override is set` —
   asserts `effectiveConnection() === 'database'`, `connectionRetryAfterSeconds() === 420`,
   `consistencyViolation() === null` when `translation.queue_connection` is `null`.
2. `it flags the default connection when its retry_after is too low and no override is set` —
   same setup but `retry_after = 90`, asserts `consistencyViolation()` is not null.
3. `it ships default database and redis retry_after at or above the requirement` —
   asserts the *actual* `config/queue.php` shipped defaults (not a test-set override)
   are `>= requiredRetryAfterSeconds()` for both `database` and `redis`.

Test 3 is the one that pins the shipped configuration itself, closing the exact gap
cycle 1 identified ("no existing test exercises this path... every test explicitly sets
`translation.queue_connection`"). All three are confirmed load-bearing by the mutation
probe below.

## Mutation probe (scratch copy, `git archive HEAD`; repo untouched)

**Mutation**: restored the cycle-1 blind spot by making `effectiveConnection()` return
`null` when no explicit override is configured (i.e. reverted the fallback to
`config('queue.default')`), applied only inside a `git archive HEAD` scratch copy under
the OS temp directory (`vendor/` symlinked, `.env` copied in). No repo file was edited.

**Result: KILLED.**

```
{"tool":"pest","result":"failed","tests":9,"passed":7,"failed":2,"assertions":19,
 "failures":[
   {"test":"...it_resolves_the_effective_connection_from_the_default_when_no_override_is_set",
    "message":"Failed asserting that null is identical to 'database'."},
   {"test":"...it_flags_the_default_connection_when_its_retry__after_is_too_low_and_no_override_is_set",
    "message":"Expecting null not to be null ."}
 ]}
```

Both of the new tests targeting the fallback path failed exactly as expected; the third
new test (shipped-defaults assertion) is independent of `effectiveConnection()`'s
fallback branch and was not expected to catch this specific mutation — its target is
drift in `config/queue.php`'s literal defaults, which a separate mutation (not requested
for this cycle) would exercise. The scratch directory was deleted after use;
`git status --porcelain` on the real repo confirmed no residue.

## Residual findings

None BLOCKER or HIGH. Carried over from cycle 1 (not in scope of this corrective, not
re-verified here since unaffected by commit `5487852`):

- **LOW** (cycle 1, unchanged) — full-suite warning count is environment/ordering
  sensitive (2 vs. previously claimed 3); not a regression.
- **INFO** (cycle 1) — `worker/TRANSLATION-OPERATIONS.md` trailing-newline cosmetic
  note; not re-checked this cycle.

No new findings surfaced in this cycle.

## P5-008 operational prerequisites

**Satisfied**, conditioned on the BLOCKER closure verified above. The retry_after
invariant this task exists to guarantee now holds under the repository's own shipped,
committed configuration (not only under an explicitly configured override), and boot
fails fast when it doesn't. This review does not authorize starting P5-008; that
remains a separate Human Product Owner gate per `.ai/guidelines/orchestration-policy.md`.

## Scope discipline

This review covers P5-004C cycle 2 only. No implementation code, tests, task files, or
governance files were modified — the only file written by this review is this artifact.
Mutation probing was performed exclusively in a `git archive` scratch copy under the OS
temp directory, deleted after use; `git status --porcelain` confirmed the real repo was
untouched throughout. This task was not marked VERIFIED, P5-008 was not started, and no
Phase 6/7 work was begun.
