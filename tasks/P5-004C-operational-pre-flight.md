# P5-004C — Phase 5 Operational Pre-Flight

## Status

IMPLEMENTED_PENDING_REVIEW — 2026-09-22. Independent review pending. Not
VERIFIED; not DONE. P5-008 not started.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code (independent review)

## Authorized Phase

Phase 5 — Translation (ADR-022). Authorized by the HPO instruction of
2026-09-22 ("prepare Phase 5 for the P5-008 real integration gate").

## Origin

`reviews/P5-004B-P5-006-independent-review.md` §4 residuals 1–3, §5 X1/X2, §8
"Must fix before P5-008", P4B-4/P4B-6, and §2 P6-1/P6-2.

## Scope

1. SQLite concurrency configuration: commit the intended `busy_timeout` and
   prove runtime behaviour does not depend on an uncommitted local config.
2. Translation job timeout: explicit per-job timeout safely below the queue
   connection `retry_after`; token-fenced `failed()` handler.
3. Translation worker operational contract: canonical `queue:work` command,
   queue name, timeout, and provider/job/retry_after reconciliation.
4. Scheduled `translation:recover-stale-attempts`, token-fenced, documented
   against job timeout and `retry_after`.
5. Persistence failure taxonomy: transient persistence/concurrency failures get
   a retryable path; deterministic integrity failures stay non-retryable.
6. Test debt: post-claim `refresh()` failure handling coverage.
7. Governance provenance: additive ownership note; historical records unchanged.

## Non-Scope

Real-model integration gate (P5-008); UI changes beyond what the taxonomy
requires; Phase 6/7; wider Phase 3/4 baseline.

## Acceptance Criteria

1. `busy_timeout` is committed and evidenced by a test that does not depend on
   the dirty baseline.
2. `ProcessTranslation::$timeout` is explicit; `job timeout < retry_after`;
   `failed()` is token-fenced and cannot overwrite a newer attempt.
3. A worker runbook documents the translation queue command and the
   provider/job/`retry_after` values; the generic default worker is explicitly
   not sufficient.
4. `translation:recover-stale-attempts` is scheduled; recovery stays
   token-fenced; scheduling evidence retained.
5. A transient lock during persistence yields a retryable failure; a
   deterministic constraint failure stays non-retryable.
6. The post-claim refresh failure path is covered by a test.
7. Tests, Pint, PHPStan pass; scheduler inspection recorded.

## Review

Pre-review: `reviews/pre-review/P5-004C-pre-review.md`. Independent review pending.