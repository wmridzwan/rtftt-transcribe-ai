# PP-T2 — Independent Re-Review (Corrective Cycle 1)

Reviewer: Claude Code (independent reviewer role). Review-only: no
implementation changes, no test modifications, no ACs altered, no
promotion to DONE performed. The corrective builder report/task-file
narrative was treated as context only; every claim below was
independently reconstructed from repository state, fresh test/tool
execution, and direct source reading.

## A. Baseline and authorization

- `tasks/PP-T2-provider-resolution-configuration.md` §1 currently reads
  `REVIEW — IMPLEMENTATION COMPLETE / AWAITING INDEPENDENT REVIEW`,
  narrating: implementation round (35/35, 62 assertions) → corrective
  cycle 1 (independent review CHANGES_REQUESTED: HIGH
  request_id-in-resolution-records, MEDIUM rejection-logging) → fix
  (42/42, 85 assertions). No implementer claim of VERIFIED/DONE.
- Authorization chain independently confirmed in `DECISION_QUEUE.md`:
  `DECISION-PP-T2-KILL-SWITCH-001` (:6487), `DECISION-PP-T2-CONFIG-NAMING-001`
  (:6562), `DECISION-PP-T2-READY-PROMOTION-001` (:6625),
  `DECISION-PP-T2-EXECUTION-AUTHORIZATION-001` (:6685). Readiness chain:
  `reviews/PP-T2-READINESS-REVIEW.md` (NOT_READY) →
  `reviews/PP-T2-READINESS-CONFIRMATION.md` (READY-ELIGIBLE). Consistent
  and internally coherent.
- **Governance gap (process finding, not a technical defect — see
  Finding M-1 below):** no durable review artifact for the *implementation*
  round exists anywhere under `reviews/` — neither the corrective cycle's
  originating CHANGES_REQUESTED review nor a prior VERIFIED/CHANGES_REQUESTED
  round for the initial 35/35 implementation. The only record of that
  review's HIGH/MEDIUM findings is the task file's own prose summary
  (§1). This violates this repo's own rule ("Write durable review
  results under reviews") even though the prose summary is internally
  plausible and (per this review's independent reconstruction below) its
  substance checks out.
- PP-T3–PP-T6 confirmed still `BACKLOG — CONTRACT_AUTHORED / NOT
  AUTHORIZED` / `DEFERRED FROM WAVE 1` (PP-T4); no code under `app/`
  matches T3–T6 territory (external adapters, chunking, ranking,
  fallback) — see §F.

## B. Change-set integrity (independent `git` inspection)

```
M  app/Jobs/ProcessTranscription.php              (+1 line: provider_class)
M  app/Jobs/ProcessTranslation.php                (+1 line: provider_class)
M  app/Providers/AppServiceProvider.php           (singleton→bind + resolver + boot validation)
M  app/Providers/TranscriptionServiceProvider.php (singleton→bind + resolver + boot validation)
M  config/transcription.php                       (+provider_selection key/comment)
M  config/translation.php                          (+provider_selection key/comment, provider label cross-ref)
?? app/Transcription/{ProviderResolutionException,SelfHostedTranscriptionProvider,TranscriptionProviderResolver}.php
?? app/Translation/{ProviderResolutionException,SelfHostedTranslationProvider,TranslationProviderResolver}.php
?? config/processing.php
?? tests/Feature/{SelfHostedTranscriptionProviderTest,Transcription/TranscriptionProviderResolutionTest,Translation/{SelfHostedTranslationProviderTest,TranslationProviderResolutionTest}}.php
```

Frozen contracts confirmed byte-unchanged (`git diff --stat` empty):
`app/Transcription/TranscriptionProvider.php`,
`app/Translation/TranslationProvider.php`,
`app/Transcription/TranscriptionInvocation.php`,
`app/Translation/TranslationInvocation.php`. No PP-T1 file touched
beyond the two ServiceProvider binding closures (which PP-T2's own
contract explicitly authorizes as the resolver's sole call sites).

## C. Prior HIGH/MEDIUM disposition — independently verified, not accepted on report

### HIGH — `request_id` used in provider selection / correlation conflation

Independently re-derived from source (not from the corrective report's
narrative):

- `TranscriptionProviderResolver::resolve(?string $requestId = null)` and
  `TranslationProviderResolver::resolve(?string $requestId = null)`
  (`app/Transcription/TranscriptionProviderResolver.php:40`,
  `app/Translation/TranslationProviderResolver.php:37`): grepped every
  use of `$requestId` inside both classes — every occurrence is inside a
  `Log::warning`/`Log::info` context array (`'request_id' => $requestId`).
  Zero occurrences in any conditional, `match`, selection branch, or
  binding-name construction. Selection is driven exclusively by
  `$killSwitchState` (derived from `config(KILL_SWITCH_CONFIG_KEY)`) and
  `$selection` (derived from `config(SELECTION_CONFIG_KEY)`) — never by
  `$requestId`.
- Container path passes no request identity: both call sites
  (`app/Providers/TranscriptionServiceProvider.php:16`,
  `app/Providers/AppServiceProvider.php:46`) call `->resolve()` with zero
  arguments. Grepped repo-wide for `new TranscriptionProviderResolver` /
  `new TranslationProviderResolver`: only these two call sites exist.
- Job-level correlation: `ProcessTranscription::handle()`
  (`app/Jobs/ProcessTranscription.php:166-171`) and
  `ProcessTranslation::execute()`
  (`app/Jobs/ProcessTranslation.php:138-141`) each add exactly one key,
  `'provider_class' => $provider::class`, to the existing
  `request_id`-carrying "provider invocation started" log line. Diff
  confirms this is the *only* change to both files (§B) — no other
  behavior altered.
- Test evidence independently rerun (not accepted from the report): both
  resolution suites include a test that resolves twice (with and without
  a caller-supplied `requestId`) and asserts the `request_id` log field
  reflects the argument exactly while `provider_key`/selection outcome is
  identical either way (`.../TranscriptionProviderResolutionTest.php:315`,
  `.../TranslationProviderResolutionTest.php:203`); a second test
  (`'joins request identity with the selected provider in job logs'`,
  both files) independently drives a real job `handle()` call and asserts
  the invocation-started log line carries both `request_id` (a string)
  and `provider_class` (the injected provider's class) together. Both
  pass under fresh execution (§E).

**Verdict: HIGH fully resolved.** `request_id` is correlation-only,
never inspected for selection; the container path passes nothing; job
logs correlate `request_id` with `provider_class` exactly as specified.

### MEDIUM — rejection paths not logged before throwing

Independently walked every `throw new ProviderResolutionException(...)`
site in both resolver classes:

| File | Rejection | Preceding `Log::warning` before throw? |
|---|---|---|
| Transcription resolver | unknown/malformed selection value | Yes (`:84-89`) |
| Transcription resolver | external binding missing | Yes (`:98-103`) |
| Transcription resolver | external binding wrong type | Yes (`:112-117`) |
| Transcription resolver | boot `validateSelection()` invalid value | Yes (`:148-153`) |
| Translation resolver | non-`self_hosted` selection | Yes (`:66-71`) |
| Translation resolver | boot `validateSelection()` invalid value | Yes (`:101-106`) |

Every throw site is preceded by exactly one `Log::warning` naming the
rejected `configured_value`. Two dedicated tests
(`'logs selection rejections without secrets'`,
`'logs boot-validation rejections without secrets'`, both resolver
suites) independently drive every rejection path (including a
worker-token secret probe planted in config) and assert (a) a warning
was logged per rejection and (b) the secret string never appears in any
logged message or context across the whole capture. Both pass under
fresh execution.

**Verdict: MEDIUM fully resolved.**

## D. AC1–AC8 — independent verification

| AC | Requirement | Independent method | Result |
|---|---|---|---|
| 1 | Default → self_hosted both domains | Read resolver default (`self::SELF_HOSTED` fallback in `config(...)` call); reran `AC1` tests fresh | PASS |
| 2 | `external_x` selects named binding, no network | Reran `AC2` test; independently confirmed `Http::assertNothingSent()` present | PASS |
| 3 | Invalid value → named error | Reran both resolve-time and boot-time `AC3` tests; exception message format matches contract's "named error" requirement | PASS |
| 4 | Missing credentials fail closed, no fallback | Reran `AC4` test (binding throws `RuntimeException` when a stand-in "credential" config is absent); no self-hosted fallback path exists in the resolver's external branch (confirmed by reading the branch — it only throws or returns the external instance, never falls back) | PASS |
| 5 | Translation non-`self_hosted` rejected | Reran both `AC5` tests, including the binding-exists variant (proves rejection is unconditional, not merely "binding missing") | PASS |
| 6 | Kill switch 3-state (disabled/enabled+refresh/invalid) | Reran the full kill-switch matrix (`disabled honors selection`, `enabled forces self-hosted after config:clear`, parametrized engaged/disengaged value sets, invalid-value warning test) for both domains | PASS |
| 7 | Identity fields in logs, no secrets | Reran `AC7` tests with a live token probe string (`pp2-secret-token-probe`) planted in config; asserted field presence and secret absence across every captured log record | PASS |
| 8 | No silent fallback under injected failure | Reran `AC8` tests (transcription: HTTP 500 fake, asserts `Pp2FakeExternalTranscriptionProvider::$resolutions === 0`; translation: HTTP 503 fake, asserts exception without invoking any external path) | PASS |

All 8 ACs independently PASS. No AC regressed by the corrective cycle's
changes (the corrective diff touched only requestId propagation and
provider_class logging — orthogonal to the AC1–AC5/AC8 mechanics, and
AC6/AC7 tests were rerun and still pass).

## E. Independent test/tool execution (fresh, this session — not accepted from any report)

```
php artisan test --filter=TranscriptionProviderResolutionTest --compact
  -> passed: 28 tests, 54 assertions

php artisan test --filter=TranslationProviderResolutionTest --compact
  -> passed: 14 tests, 31 assertions

  (28+14 = 42 tests, 54+31 = 85 assertions — matches the corrective
  cycle's claimed "42/42 pass (85 assertions)" exactly.)

php artisan test --compact  (full suite, run 1)
  -> 1150 total, 1144 passed, 1 error, 5 skipped
     error: Tests\Feature\Observability\LogContextTest
       "it counts attempt ordinals across attempts for the same transcription"
       SQLSTATE[23000] UNIQUE constraint failed: processing_jobs.transcription_id

php artisan test --filter=LogContextTest --compact  (isolated rerun)
  -> passed: 6 tests, 26 assertions  (no failure in isolation)

php artisan test --compact  (full suite, run 2)
  -> 1150 total, 1145 passed, 0 errors, 5 skipped
     -> matches the corrective cycle's claimed "1150 (1145 passed, 5
        pre-existing skips, 0 failures)" exactly.

vendor/bin/pint --test --format agent
  -> {"tool":"pint","result":"passed"}

composer types:check  (PHPStan)
  -> {"tool":"phpstan","result":"passed","errors":0}
```

`LogContextTest.php` is confirmed pre-existing (P7-005, unmodified in
this diff — `git status`/`git log` both confirm) and unrelated to PP-T2
scope (it tests attempt-ordinal counting, no provider-resolution
surface). It passed in isolation and on a same-session full-suite
rerun. This is the *same* known order-dependent flake independently
recorded as `PP-T1-REV-03` in `reviews/PP-T1-INDEPENDENT-REVIEW.md`
("did not reproduce in this review's full-suite run or isolated
rerun") — corroborating evidence this is a pre-existing, non-PP-T2
test-ordering sensitivity, not a regression introduced by this
corrective cycle. Recorded as Finding L-1 (LOW, non-blocking,
out-of-scope for PP-T2).

## F. Scope / frozen-contract audit

- No T3–T6 surface introduced: `find app/Transcription app/Translation
  -iname "*external*" -o -iname "*ranking*" -o -iname "*fallback*"`
  returns zero files. No chunking/ranking/payload-routing/staleness code
  in either resolver (grepped for `chunk|fallback|ranking|benchmark` in
  both resolvers and both SelfHosted adapters — zero hits outside two
  docblock negation statements, e.g. "No routing, fallback, chunking...").
- No new queues, no schema/migration change (`git status` shows no
  `database/migrations/*` entries).
- Jobs depend only on interfaces: grepped `app/Jobs/ProcessTranscription.php`
  and `app/Jobs/ProcessTranslation.php` for `SelfHosted|ProviderResolver`
  — zero hits. Both jobs' `handle()`/`execute()` signatures still type
  against `TranscriptionProvider`/`TranslationProvider` only.
- Resolver binding shape pinned and verified: resolver instantiated only
  inside `TranscriptionServiceProvider::register()` /
  `AppServiceProvider::register()` (`new *ProviderResolver` grepped
  repo-wide — exactly two hits, both there); both bindings use `->bind()`
  (re-evaluated every resolution), not `->singleton()` (confirmed by
  direct diff read, §B).
- Config-naming decision honored exactly: `transcription.provider_selection`
  / `translation.provider_selection` are new, non-colliding keys;
  `translation.provider` (identity label) is untouched in meaning, with
  an explicit code-comment cross-reference in both directions
  (`config/translation.php:24-26` and `:41-46`), matching
  `DECISION-PP-T2-CONFIG-NAMING-001` and Finding M-3/M-4 from the
  readiness review.
- Corrective contract edits: task file §8/§11 describe exactly the
  implemented `?string $requestId = null` semantics (log-context only,
  never read for selection) and the job-level `provider_class` addition
  — verified against the actual code in §C above; no scope expansion
  found in the §8/§11 wording itself.

**Scope boundary: CLEAN.** No leakage into PP-T3–PP-T6 territory found.

## G. New findings

```
ID: M-1
Severity: MEDIUM
Surface: reviews/ (governance-process gap, not a code defect)
Evidence: The task file (§1) narrates a full implementation-round
  independent review (35/35, CHANGES_REQUESTED: HIGH
  request_id-in-resolution-records, MEDIUM rejection-logging) and a
  corrective-cycle re-review, but no durable artifact for either round
  exists under reviews/ (only the two PP-T2-READINESS-* artifacts,
  which predate implementation). This repository's own governance model
  requires "Write durable review results under reviews" and "Important
  project information must not exist only in chat output" — the
  implementation-round review currently exists only as task-file prose,
  which is a materially thinner record than every other implementation
  review in this repository's history (compare PP-T1, which has
  `reviews/PP-T1-INDEPENDENT-REVIEW.md`).
Why it matters: without the original review artifact, an independent
  reconstruction (this review) must re-derive the HIGH/MEDIUM findings'
  substance from source alone rather than cross-checking against a fixed
  prior record — which this review did, and the substance held up (§C),
  but the durability gap is real and would compound if a future cycle
  needed to reference exactly what the first reviewer found.
Required resolution: Before HPO closure, either locate/restore a missing
  `reviews/PP-T2-*` implementation-round artifact if one was in fact
  written and not committed, or accept this review
  (`reviews/PP-T2-CORRECTIVE-CYCLE1-RE-REVIEW.md`) as the durable
  record of both the corrective cycle's resolution and the original
  findings' substance (independently reconstructed in §C), and note the
  gap in CURRENT_STATE.md for the historical record.
Blocks VERIFIED: NO — this review independently reconstructed and
  verified the underlying substance from source and fresh test runs,
  which is what governance ultimately requires; the missing artifact is
  a paper-trail completeness issue, not an unverified claim.
```

```
ID: L-1
Severity: LOW
Surface: tests/Feature/Observability/LogContextTest.php (pre-existing,
  out of PP-T2 scope)
Evidence: One order-dependent flake observed on the first full-suite
  run this session (UNIQUE constraint violation on
  processing_jobs.transcription_id); passed in isolation and on a
  same-session full-suite rerun. Same flake independently recorded
  during PP-T1's review (`PP-T1-REV-03`).
Why it matters: Not a PP-T2 regression (file untouched by this diff,
  unrelated subsystem) but a recurring, still-unticketed test-ordering
  sensitivity.
Required resolution: Optional follow-up ticket outside PP-T2 scope to
  investigate LogContextTest's apparent shared-state/ordering
  dependency. Does not block PP-T2.
Blocks VERIFIED: NO
```

No BLOCKER or HIGH finding in this re-review.

## H. Independent verdict

```text
PP-T2 (corrective cycle 1) = VERIFIED
```

Every element of the independent re-review request is satisfied:

- The prior HIGH (`request_id` correlation) is fully resolved:
  `request_id` is never read for selection in either resolver; the
  container path passes no request identity; job-level "provider
  invocation started" logs correlate `request_id` with `provider_class`.
- The prior MEDIUM (rejection logging) is fully resolved: every
  fail-closed rejection path in both resolvers logs a warning naming the
  rejected value before throwing, with no secrets in any captured log
  record.
- AC1–AC8 independently PASS for both domains (§D).
- Per-resolution evaluation is intact (`bind`, not `singleton`; explicit
  test proves selection changes take effect without container restart).
- Kill-switch semantics match the contract exactly: disabled/missing →
  selection honored; enabled → forced self-hosted + engagement log;
  invalid → fail-closed-as-engaged + warning naming the value, no boot
  crash; evaluated every resolution.
- Frozen `TranscriptionProvider`/`TranslationProvider`/both `*Invocation`
  factories are byte-unchanged.
- Both jobs depend only on the frozen interfaces; zero concrete-provider
  or resolver coupling in either job file.
- Existing invocation `requestId` semantics (minted once by
  `*Invocation::create()`, preserved end-to-end) are untouched — verified
  by a dedicated "preserves the existing invocation requestId end to
  end" test in both resolution suites, independently rerun.
- No T3–T6 implementation, fallback, ranking, payload/language/user/media
  routing, chunking, schema, queue/lifecycle, staleness, or unrelated
  refactor exists anywhere in the diff (§B, §F).
- §§8/11 corrective wording describes exactly the implemented semantics,
  with no scope expansion.
- Lifecycle state is legal: task file remains `REVIEW` (correct — only
  Claude Code, as independent reviewer, may move it to `VERIFIED`; only
  the Human Product Owner may then close it `DONE`).

One MEDIUM governance-process finding (M-1, missing implementation-round
review artifact) and one LOW pre-existing/out-of-scope test-flake
finding (L-1) are recorded; neither blocks VERIFIED per this
repository's own verification rule (MEDIUM/LOW findings may remain
documented without preventing safe completion).

## I. Exact lifecycle state after this review

```text
PP-T2: REVIEW -> VERIFIED
```

This review artifact constitutes Claude Code's independent-reviewer
VERIFIED determination for PP-T2's corrective cycle 1. This review does
not itself edit `tasks/PP-T2-provider-resolution-configuration.md` §1 or
`CURRENT_STATE.md` beyond recording this artifact; per the canonical
State-to-Action Contract (`.ai/guidelines/orchestration-policy.md`),
transitioning the task file's recorded status line to `VERIFIED` and
reconciling `CURRENT_STATE.md` is the next mechanical step that follows
from this verdict (implementer/governance-record update), not a further
independent-review action.

## J. Exact legal HPO closure action required (2-step operating flow)

Per `.ai/guidelines/orchestration-policy.md` ("VERIFIED does not mean
DONE. After Claude Code returns VERIFIED, the Human Product Owner must
close the task as DONE."):

1. The task-file/governance record should be updated to reflect
   `PP-T2: REVIEW → VERIFIED` (this review's verdict, §H/§I).
2. The Human Product Owner must then record an explicit HPO decision
   (e.g. `DECISION-PP-T2-CLOSURE-001`, mirroring
   `DECISION-PP-T1-CLOSURE-001`'s pattern) closing PP-T2
   `VERIFIED → DONE` in `DECISION_QUEUE.md`/`DECISIONS.md`, and updating
   the task file's §1 status line and `CURRENT_STATE.md` accordingly.

No implementation, promotion, or DONE-closure is performed by this
review. This review authorizes nothing beyond its own VERIFIED
determination; closure remains an HPO action. PP-T3–PP-T6 remain
unauthorized and untouched by this review.
