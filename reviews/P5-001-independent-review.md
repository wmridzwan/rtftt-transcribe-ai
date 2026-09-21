# P5-001 — Independent Review: Translation Domain Contract

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-21
Scope: `tasks/P5-001-translation-domain-contract.md` only (commit `3524524`).
This review does not modify implementation code or tests, does not mark P5-001
DONE, and does not authorize P5-003 or any later task. P5-002 is reviewed
separately in `reviews/P5-002-independent-review.md`.

**Verdict: VERIFIED** (no BLOCKER / HIGH / MEDIUM; two LOW and three INFO,
all non-blocking). VERIFIED is not DONE; HPO closure is required.

## 1. Contract and Authorization Checked

- Contract: `tasks/P5-001-translation-domain-contract.md`
  (status at review start: `IMPLEMENTED_PENDING_REVIEW`).
- Authorization: ADR-022 (D5-01..D5-09 frozen) and
  `DECISION-PHASE5-AUTHORIZATION-001` (`DECISION_QUEUE.md`, DECIDED 2026-09-21).
  See §8 for a governance observation on how that authorization is recorded.

## 2. Implementation Inspected (all read in full)

`app/Translation/`: `TranslationStatus`, `TranslationTarget`,
`TranslationLifecycle`, `TranslationFailure`, `TranslationException`,
`TranslationSegmentData`, `TranslationResult`, `TranslationAlignment`,
`TranslationInvocation`, `TranslationProvider`; and all seven
`tests/Unit/Translation/*` files.

Live Git evidence: `git show --stat 3524524` touches only `app/Translation/*`,
`tests/Unit/Translation/*`, `reviews/pre-review/P5-001-pre-review.md`, and
`tasks/P5-001-…md`. `git diff HEAD` over these paths is empty (working tree
equals the commit). No schema, route, view, JS, worker, or existing app file was
modified (AC 9).

## 3. Acceptance Criteria

| AC | Result | Reviewer evidence |
|---|---|---|
| 1. Five statuses, correct terminal semantics | PASS | enum has exactly pending/queued/translating/completed/failed; `isTerminal()` true only for completed/failed; asserted in `TranslationStatusTest` |
| 2. Target enum + `fromBcp47` | PASS | exactly `ms,en,zh,ta`; `und`, `fr`, `''`, whitespace → `null`; `en-US`, `MS-MY`, `Zh-CN` resolve |
| 3. Lifecycle transitions | PASS | transition map matches the contract edge-for-edge; `completed` has no outgoing edge; `failed→queued` is the only exit from failed; `assertValidTransition` throws on illegal edges |
| 4. Failure taxonomy | PASS | 10 cases; `isRetryable()` deterministic `match` (4 retryable, 6 not); tested |
| 5. DTO invariants, no re-rounding | PASS | index ≥ 0, start ≥ 0, end ≥ start enforced; values stored as given (`15.999` round-trips); duplicate result indices rejected. See LOW-1 for the non-finite edge |
| 6. Alignment/passthrough policy | PASS | passthrough only when source known and equals target; `und` never passthrough; index/timestamps/text/source language preserved verbatim; non-passthrough throws |
| 7. Provider-neutral interface | PASS | `translate(TranslationInvocation): TranslationResult` only; no media path/binary/runtime type anywhere in the invocation |
| 8. Tests, Pint, PHPStan | PASS | reproduced below |
| 9. No unrelated change | PASS | see §2 |

## 4. Commands Independently Reproduced by the Reviewer

Run with Herd PHP 8.4.24 from the repository root:

- `php artisan test --compact tests/Unit/Translation tests/Feature/Translation`
  → 33 passed / 104 assertions (26 of these are the P5-001 unit tests; the rest
  are P5-002). Matches the implementer's numbers.
- `php -d memory_limit=1G vendor/bin/phpstan analyse app/Translation …
  --no-progress` → 0 errors.
- `vendor/bin/pint --test` (read-only, chosen deliberately so the reviewer does
  not modify files) over `app/Translation` and `tests/Unit/Translation` →
  passed.

**Implementer-reported, NOT reproduced by the reviewer:** the full-suite result
(459 total / 458 passed / 1 skipped / 0 failures). Per repo rules the complete
suite is run by the user; the change set is purely additive new files, so
regression risk is low, but the claim is not independently confirmed here.

## 5. Findings

### BLOCKER / HIGH / MEDIUM

None.

### LOW-1 — `TranslationSegmentData` accepts non-finite timestamps

Reproduced: `new TranslationSegmentData(0, NAN, NAN, 'x', …)` and
`(0, 0.0, INF, …)` are both accepted, because `NAN < 0`, `NAN < NAN` and
`INF < 0` are all false. AC 5 says the DTO rejects invalid alignment values.
Practical exposure is small (a JSON worker response cannot carry `NaN`/`Inf`),
but the guard is cheap (`is_finite`) and this DTO is the single alignment
choke point for P5-003/P5-002/P5-007. Non-blocking.

### LOW-2 — Input-side alignment is not validated in `TranslationInvocation`

`TranslationInvocation` accepts an empty `segments` list and does not reject
duplicate source indices (the existing test constructs it with `segments: []`
as if valid). The result side (`TranslationResult`) rejects duplicate indices;
the request side does not. Non-blocking; P5-003/P5-004 build the invocation
from persisted, unique source segments, but the contract type does not enforce
it.

### INFO-1 — Runtime element-type checks were removed

The pre-review records that two defensive `instanceof` checks were removed
after PHPStan flagged them as always-true. This is acceptable: PHPDoc
`list<TranslationSegmentData>` plus PHPStan is the guard, and a wrong element
would raise a `TypeError` on first property access. Noted for transparency.

### INFO-2 — Provider binding/config registration absent

By design (P5-003). Interface only.

### INFO-3 — Passthrough helper has no consumer yet

By design (P5-004). Policy is pure and correctly unit-tested; whether it is
actually invoked in the orchestration path can only be verified when P5-004 is
reviewed.

## 6. Architecture Review

Consistent with ADR-022 (D5-01 segment-aligned, D5-02 target vocabulary,
D5-04 provider-neutral boundary, D5-05 derived data). The contract is
model-free and never reads or writes `Transcription`/`TranscriptionSegment`.
Mirrors the Phase 3 shape (`TranscriptionLifecycle`, failure taxonomy,
provider interface). `LanguageIdentifier` is reused rather than duplicated,
and `und` is correctly a valid source marker but never a target.

## 7. Security / Regression

No secrets, credentials, I/O, or persistence are introduced. Exception
messages are fixed user-safe strings that do not leak provider internals.
Regression risk: none identified (new namespace, new tests).

## 8. Governance Observations (not defects in P5-001)

- **GOV-2 (documentation drift; see also the P5-002 review):** `AGENTS.md` and
  `CURRENT_STATE.md` in the working tree still say "Phase 5 NOT AUTHORIZED /
  do not start Phase 5 without a separate HPO authorization", while ADR-022 and
  `DECISION-PHASE5-AUTHORIZATION-001` record the HPO authorization. ADR-022 is
  present only in the uncommitted working tree (`git show HEAD:DECISIONS.md`
  contains no ADR-022). Under the repository's source-of-truth order `AGENTS.md`
  ranks above an ADR. I relied on the recorded HPO decision as the authorization
  and did not independently confirm it with the HPO; the HPO/orchestrator
  should reconcile these documents.
- `CURRENT_STATE.md` does not list P5-001 as awaiting review.

## 9. Required Changes

None for VERIFIED. LOW-1 and LOW-2 may be batched into a follow-up task per
the orchestration policy (follow-ups do not start automatically).

## 10. Reviewer Conclusion

**VERIFIED.** All nine acceptance criteria are satisfied, the reviewed tests
pass on reviewer reproduction, PHPStan and Pint are clean, and no BLOCKER,
HIGH, or MEDIUM findings remain. Not DONE until closed by the Human Product
Owner. VERIFIED does not authorize production deployment.
