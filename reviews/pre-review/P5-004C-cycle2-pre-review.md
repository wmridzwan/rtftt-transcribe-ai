# P5-004C — Cycle 2 Internal Adversarial Pre-Review

Task: P5-004C — Phase 5 Operational Pre-Flight (corrective cycle 2)
Date: 2026-09-22
Origin: `reviews/P5-004C-independent-review.md` BLOCKER (queue retry_after
invariant not enforced against the effective default connection)
Reviewer: OpenCode (implementation owner internal pre-review)
Verdict: **PRE_REVIEW_PASS**

Internal pre-review only. Not independent verification.

## Changes

- `TranslationQueueConfig::effectiveConnection()` added: resolves
  `translation.queue_connection` when set, otherwise `config('queue.default')`,
  mirroring `TranslationDispatcher`'s actual fallback. `connectionRetryAfterSeconds()`
  and `consistencyViolation()` now use it, so the guard checks the connection
  actually in effect rather than only an explicit override.
- `config/queue.php`: `database` and `redis` `retry_after` defaults raised from
  90 s to 420 s (env-overridable via `DB_QUEUE_RETRY_AFTER` /
  `REDIS_QUEUE_RETRY_AFTER`), so the shipped configuration satisfies
  provider (300) < job (330) < retry_after (420).
- `.env.example`: documents `RTFTT_TRANSLATION_QUEUE_CONNECTION` (empty = use
  `QUEUE_CONNECTION`) and the retry_after envs.
- `worker/TRANSLATION-OPERATIONS.md`: documents the effective-connection
  fallback and the shipped-default guarantee.
- Tests: effective-connection resolution, low default retry_after flagging, and
  a shipped-defaults guard.

## BLOCKER closure evidence

- Non-test boot with the shipped default: `effective='database'`,
  `db_retry=420`, `redis_retry=420`, `consistencyViolation()=NULL`.
- Fail-fast proof: with `DB_QUEUE_RETRY_AFTER=90`, a real (non-test) boot throws
  `LogicException: Translation queue connection "database" retry_after (90) is
  below the required 420; a running translation could be re-delivered.`
  (`TranslationQueueConfig.php:124`).

## Adversarial checks

- The guard now mirrors the dispatcher's fallback, closing the blind spot.
- `assertConsistent()` still skips under the test runner (so CLI/test children
  are unaffected); the pure logic is covered directly.
- `sync`/`null` drivers remain exempt (no `retry_after`).
- No change to attempt fencing, persistence taxonomy, scheduling, or the
  translation writer.

## Evidence

- Translation suite: **191 passed, 724 assertions**.
- Full suite: **624 tests, 623 passed, 1 skipped, 2 warnings, 0 failures**.
- Concurrency races (claim/retry/request): 3/3 passed on 3 consecutive runs.
- Pint clean; PHPStan 0.
- Real-boot fail-fast reproduced (above).

## Verdict

PRE_REVIEW_PASS. P5-004C remains `IMPLEMENTED_PENDING_REVIEW`; fresh independent
re-review (cycle 2) required. Not VERIFIED.