# PP-T4 — Readiness Review (Step 1)

Date: 2026-09-28. Scope: governance/readiness only; no implementation.
Authority: `PP-T4 — Step 1: Prepare & Authorize` prompt (PP-T4 only; Step 2 not
authorized by this review).

## A. Baseline reconstructed

Source of truth: working-tree `AGENTS.md` (ProcessingProvider track paragraph),
`tasks/PP-T*.md`, `DECISION_QUEUE.md`, `DECISIONS.md` (ADR-027),
`CURRENT_STATE.md` (ProcessingProvider track §),
`discovery/processing-provider/ARCHITECTURE-PLAN-OPTION1.md`,
`RTFTT-MASTER-ROADMAP.md`, `plan.md`, `architecture.md` (both contain no PP-track
records — no contradiction), live source (`app/Translation/*`,
`config/translation.php`, `app/Transcription/ReferenceExternalTranscriptionProvider.php`
as the T3 pattern reference).

| Task | State before this step |
|---|---|
| PP-T1 | DONE (`DECISION-PP-T1-CLOSURE-001`; independently VERIFIED, no BLOCKER/HIGH) |
| PP-T2 | DONE (`DECISION-PP-T2-CLOSURE-001`; corrective cycle 1 independently VERIFIED, no BLOCKER/HIGH) |
| PP-T3 | DONE (`DECISION-PP-T3-CLOSURE-001`, 2026-09-28; independently VERIFIED, AC1–AC10 PASS, no BLOCKER/HIGH/MEDIUM) |
| PP-T4 | `BACKLOG — DEFERRED FROM WAVE 1 / CONTRACT_AUTHORED / NOT AUTHORIZED` (candidate; no implementation) |
| PP-T5 | BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED |
| PP-T6 | BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED (FINAL_GATE_ONLY) |

Matches the prompt's expected baseline; the canonical repository wording for PP-T4
is `BACKLOG — DEFERRED FROM WAVE 1 / CONTRACT_AUTHORED / NOT AUTHORIZED`
(`tasks/PP-T4-external-translation-provider.md` §1).

Authoritative constraints applied: ADR-027 (Option 1, server-first + pinned
direct overflow; separate provider contracts; self-hosted canonicals; no silent
fallback; no Wave-1 Auto/client; external translation deferred; chunk identity
execution-only; per-chunk audit logs-only initially; vendor selection deferred;
Phase 1–7 frozen); `DECISION-PROCESSING-PROVIDER-OPTION1-001`;
`DECISION-PP-T2-KILL-SWITCH-001`; `DECISION-PP-T2-CONFIG-NAMING-001`;
ADR-022 (D5-01/D5-04/D5-05/D5-06: segment-aligned result, text-only path,
lifecycle, atomic writer, additive staleness); ADR-018 (manual-only retry,
`tries=1`, completed protected, stale rules); PP-T1 §12 parity rule; PP-T2
§§6/8/11 (resolver seam, request identity, logging); PP-T3 reconciled contract
(single fixture-shaped reference adapter, scalar ctor, §9 error table, log
checklist, T3-enforces/T5-owns split) as the proven pattern to mirror.

No premature PP-T4 implementation exists: repo-wide scan finds
`ExternalTranslationProvider` named only in `tasks/PP-T4-*`; no
`ReferenceExternalTranslation*` production class exists under `app/`.
Frozen translation interfaces verified live: `TranslationProvider::translate()`,
`TranslationInvocation` (text-only; UUID `requestId` minted once in `::create()`),
`TranslationSegmentData` (index/timestamps/source-language alignment invariant),
`TranslationResult` (segment-aligned + provider/model identity),
`TranslationResponseValidator` (strict count/index/±0.0005s/source-echo/
target-match + `failureFromCode` with `ProviderFailed` default),
10-case `TranslationFailure` + frozen `isRetryable()`, `TranslationException`
(carries `TranslationFailure`), `TranslationResultWriter` (atomic, idempotent,
attempt-token-fenced, machine-source-only reads), `TranslationLifecycle`
(pending → queued → translating → completed/failed; failed → queued manual
only), `TranslationProviderResolver` (Wave-1 lock: non-`self_hosted` rejected;
kill-switch evaluated per resolution), `config/translation.php`
(`provider_selection` default `self_hosted`; timeout 300 < job 330 < retry_after
420). `ProcessTranslation::$tries = 1` (manual-only) confirmed.

## B. Deferment analysis

Original deferment reason: product/architectural exclusion from Wave 1.
ADR-027 (accepted HPO 2026-09-27, `DECISION-PROCESSING-PROVIDER-OPTION1-001`):
"External translation deferred from Wave 1 (translation stays self-hosted
NLLB)." `tasks/PP-T4-*` §§18–19 restate it: "DEFERRED. No READY promotion, no
implementation, no vendor work in Wave 1. Reactivation needs HPO wave
authorization."

Release condition (as-authored §19): "Eligible only after later HPO wave
authorization + T1/T2 stable + vendor decision."

Current evidence:

- T1/T2 stable: EXCEEDED — PP-T1 DONE + PP-T2 DONE + PP-T3 DONE (the full
  transcription-side reference pattern proven, independently VERIFIED).
- Later HPO wave authorization: SATISFIED for Step 1 by the present Step-1
  prompt itself, which explicitly tasks deferment review, release where legally
  justified, promotion to READY, and execution authorization for PP-T4 only.
  No Wave-1 transcription work remains that PP-T4 could disturb (PP-T3 DONE).
- Vendor decision: NO VENDOR SELECTED — and none is required for the T3-mirror
  scope. ADR-027 defers vendor selection "until after the provider
  abstraction/config foundation exists"; that foundation now exists
  (T1/T2/T3 DONE). PP-T3 precedent (H-1, `DECISION-PP-T3-CONTRACT-RECONCILIATION-001`)
  established the track's interpretation: fixture-shaped, vendor-neutral,
  zero-egress reference work proceeds with vendor selection deferred;
  per-vendor classes require a separate provider-specific addendum + HPO auth.
  The as-authored §19 "vendor decision" clause, read literally, would make PP-T4
  unbuildable forever (no vendor can be chosen before the boundary it proves
  exists). It is a stale over-constraint, not a product decision, and is
  narrowed by reconciliation (H-1 below) — not silently dropped: vendor
  selection stays deferred and per-vendor work stays forbidden.

Disposition:

```text
DEFERMENT MAY BE RELEASED
```

via `DECISION-PP-T4-DEFERMENT-RELEASE-001` (releasing Step-1 authority only;
Step-2 execution still requires the separate execution authorization of §L).

## C. Findings

Severity scale: BLOCKER / HIGH / MEDIUM / LOW / INFO. All BLOCKER/HIGH/MEDIUM
affecting readiness must be resolved before promotion.

### H-1 — §19 "vendor decision" precondition contradicts deferred vendor selection (HIGH)

As-authored §19 makes PP-T4 "eligible only after … vendor decision", but
ADR-027 defers vendor selection and no vendor decision exists or is in flight.
Read literally, the release condition can never be satisfied by design work.
Disposition: RESOLVE by reconciliation — PP-T4 builds exactly ONE
fixture-shaped, vendor-neutral reference translation adapter proving the
boundary (request/response/identity/alignment/errors/determinism); vendor
selection stays deferred; per-vendor classes require a separate
provider-specific addendum + HPO authorization (T3 H-1 precedent, same §18
gate). Recorded in `DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### H-2 — External translation construction/config contract undefined (HIGH)

The draft requires asserting exact outbound fields (auth/pinned model), timeout
behavior, and ceilings, but defines no ctor, no timeout source, no ceiling
source, and no credential-less rule. A builder would invent config keys or
 weaken PP-T2 fail-closed to make fixtures pass.
Disposition: RESOLVE — T3 H-2 mirror: no new global config keys; public ctor
takes scalars only (`baseUrl`, `token`, `timeoutSeconds` defaulting to
`config('translation.timeout_seconds')` 300, `providerKey`, `modelPinned`,
`maxSegments`, `maxPayloadChars`) plus the single dispatch seam
(`ReferenceExternalTranslationTransport`, named explicitly — PP-T3-REV-01
lesson); tests inject explicit fixture values; env secrets stay empty; PP-T2
boot/selection validation semantics untouched except the scoped H-3 amendment.
Recorded in `DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### H-3 — Translation-lock conflict: AC1 "explicit selection" is unimplementable as written (HIGH)

AC1 requires selection "via T2-style explicit selection", but
`TranslationProviderResolver` rejects every non-`self_hosted` value (Wave-1
lock, ADR-027) and `validateSelection()` fails boot on any other value. Unlike
T3 (transcription selection already admitted `external_x`), PP-T4 cannot reach
any adapter through the resolver without a contract-scoped amendment — and a
general lock-lifting would violate ADR-027 ("translation stays self-hosted
NLLB").
Disposition: RESOLVE — scoped fixture-selection amendment (reconciliation, not
product redesign): the resolver accepts exactly one additional value,
`external_reference`, resolving only to the fixture reference adapter bound
under the `translation.providers.*` convention; default stays `self_hosted`;
kill-switch engaged (or invalid) still forces self-hosted; production
construction with an empty token fails closed with `ConfigurationError` before
any dispatch (so a misconfigured prod selection fails closed with zero egress);
any other non-`self_hosted` value remains a validation failure. General
translation unlocking stays forbidden without a fresh HPO decision. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-1 — No canonical error-map table (MEDIUM)

§9 says "map into 10-case taxonomy" with no per-condition table; "unknown →
safe code" lets the builder choose the default.
Disposition: RESOLVE — canonical table: transport timeout → `ProviderTimeout`
(retryable); HTTP 429 → `ProviderUnavailable` (retryable, mirroring
`HttpTranslationProvider::failureForStatus`); HTTP 5xx / unreachable →
`ProviderUnavailable` (retryable); 504 → `ProviderTimeout` (retryable);
401/403/404/405 → `ConfigurationError` (non-retryable); 422 →
`MalformedOutput` (non-retryable); count/index-coverage failure →
`MissingSegments` (non-retryable); timestamp/source-echo/target-mismatch,
non-object shapes → `MalformedOutput` (non-retryable); oversized-for-provider
→ `InvalidRequest` (non-retryable); empty-secret production construction →
`ConfigurationError` (non-retryable, fail closed before dispatch); unsupported
source tag → `UnsupportedSource` (non-retryable); unknown →
`ProviderFailed` (frozen `failureFromCode` default; taxonomy authoritative,
vendor advisory flag never controls retry). Each mapping asserted with code +
`isRetryable()`. Recorded in `DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-2 — Alignment rule stated but not pinned to the frozen validator (MEDIUM)

§8 states "no resegmentation; alignment from DB, never provider" without
naming the enforcement mechanism; §6 omits the writer contract.
Disposition: RESOLVE — enforcement is the frozen `TranslationResponseValidator`
(count/index/±0.0005s/source-echo/target-match) at the adapter boundary plus
the frozen `TranslationResultWriter` (atomic persist; alignment copied from
machine-source DB rows, never provider output; attempt-token fencing;
completed immutable) at the persist path. LLM-style free-text output is
inadmissible unless segment-aligned and versioned (P6-001/P6-002 guard kept).
Translation is single-shot 1:1 — there is no chunk fan-out, hence no
overlap/dedup rule (explicitly N/A, unlike T3 M-1/M-2). Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-3 — Retry/idempotency vs manual-only + attempt-token fencing unstated (MEDIUM)

The draft never addresses ADR-022 manual-only retry, `tries=1`, or the writer's
attempt-token fencing; a builder could add adapter-internal retries.
Disposition: RESOLVE — no retries and no fallback inside the adapter: the first
mapped failure ends the attempt. Operator manual retry always creates a NEW
parent attempt via the existing retry action (ADR-018/ADR-022 precedent;
`ProcessTranslation::$tries = 1` unchanged); prior attempts immutable.
Idempotency is the existing writer fencing (same-token completion idempotent;
different-token overwrite rejected). Request identity: reuse the invocation's
existing `requestId`; the adapter mints no second identity. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-4 — Timeout behavior valueless (MEDIUM)

"Retry behavior" cited with no value; invariant 300/330/420 must hold.
Disposition: RESOLVE — honor `config('translation.timeout_seconds')` (300) as
the provider ceiling via the timeout ctor default; no new timeout keys;
job/stale rules unchanged. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-5 — Size-ceiling source and named error undefined (MEDIUM)

"Retry behavior" and ceilings implied; no source, value, or code.
Disposition: RESOLVE — adapter-internal ceilings via ctor scalars
(`maxSegments`, `maxPayloadChars`; generous contract-pinned defaults, tests
inject small values to prove enforcement); product behavior unchanged
(translation has no product byte limit; ceilings are adapter-internal, never
product changes); breach rejected before dispatch with `InvalidRequest`
(non-retryable), logged with ceiling + actuals, zero dispatch. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-6 — Log-reconstruction checklist missing (MEDIUM)

§11 lists fields loosely ("request_id/translation/attempt-token/provider/model/
outcome stamping") with no exact checklist and no absence rule.
Disposition: RESOLVE — checklist embedded in reconciled §11: invocation
`requestId` (reused), `provider_key`, `model_pinned`, transcription/
translation ids, target language, outcome, timing ms, failure category;
secrets, media bytes (N/A text-only, stated), and full translated text absent
(asserted). Parent-row identity reuses the existing PP-T2 job-layer
`provider_class` lines. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-7 — Kill-switch interplay has no acceptance criterion (MEDIUM)

The draft relies on selection but never asserts kill-switch behavior (T3 M-8
analog).
Disposition: RESOLVE — add AC: kill-switch engaged → resolution returns
self-hosted even when selection names the PP-T4 fixture binding (assert zero
adapter invocations); reuses the PP-T2 mechanism, no new logic. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-8 — Language mapping imprecise (MEDIUM)

"Language mapping (ms/en/zh/ta/mixed)" does not distinguish source from target
rules and never mentions `und`.
Disposition: RESOLVE — source echo via `LanguageIdentifier` (ms/en/zh/ta/und;
`und` source allowed and echoed; allowlist miss → `Undetermined` handling
preserved); target via `TranslationTarget::fromBcp47` (ms/en/zh/ta only; `und`
or unsupported target → rejected before dispatch/persist); mixed/code-switched
source is a segment-level property, never a resegmentation license. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-9 — PP-T4/PP-T5 control boundary unstated (MEDIUM)

Draft never splits adapter enforcement from operational procedure (T3 M-10
analog); unbounded, T4 could implement T5's runbook/alerts/spend controls.
Disposition: RESOLVE — PP-T4 owns adapter-internal enforcement + log emission
only (ceilings, error map, identity fields, fail-closed); kill-switch
procedure/runbook/alerts, secrets rotation procedure, spend/billing controls
stay PP-T5-owned (PP-T5 §6 cross-reference both ways). Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### M-10 — Staleness/P6-005 interplay asserted only as an AC line (MEDIUM)

AC mentions "marks stale without rewriting segments" with no lifecycle rule;
export F-001 guard not regression-gated.
Disposition: RESOLVE — rule: edit-after-translate marks stale via the additive
staleness schema (`stale_at`/reason/`causing_revision_id`) without rewriting
segments; machine source immutable; writer path unchanged; Phase 6
revision/invalidation/history/export suites (incl. F-001 active-revision guard)
explicit regression-gate items. Recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.

### L-1 — Fixture corpus not enumerated (LOW)

Disposition: RESOLVE — minimal corpus embedded in reconciled §14: per-target
fixtures (ms/en/zh/ta), `und`-source, mixed/code-switch, count-mismatch,
index-mismatch, timestamp drift at the ±0.0005s boundary, source-echo mismatch,
target mismatch, empty, invalid shapes, timeout/429/5xx/partial/unknown,
oversized, determinism rerun pairs. Resolved inline.

### L-2 — No-migration rule implicit only (LOW)

Disposition: RESOLVE — explicit clause: no migration in PP-T4; any claimed
need → STOP + HPO escalation, never a silent additive column (ADR-027
logs-only rule). Resolved inline.

### L-3 — Determinism/version rule vague (LOW)

"Versioned output, rerun-identical or explicitly versioned" with no assertable
rule.
Disposition: RESOLVE — rule: same input + pinned version → byte-identical
output asserted by rerun test; any intentional behavior change → version bump
recorded in model identity; LLM non-determinism never leaks unversioned.
Resolved inline.

### I-1 — Fixture binding name (INFO)

The concrete `translation.providers.external_reference` binding name is an
implementation detail within the contract (test/fixture binding); no product
decision. Noted, no action.

## D. PP-T1 / PP-T2 / PP-T3 compatibility audit (pre-reconciliation)

- PP-T1 (`TranslationProvider`, `TranslationInvocation`, `TranslationResult`,
  strict validator, 10-case taxonomy, lifecycle, atomic writer, additive
  staleness): the draft respects all frozen surfaces in prose but under-specifies
  enforcement (M-2/M-3/M-10). No interface change proposed. CONDITIONAL PASS
  (passes after M-2/M-3/M-10 reconciliation, which adds no new surface).
- PP-T2 (translation lock, fail-closed, per-resolution evaluation, kill-switch,
  config naming, request identity, interface-only orchestration): the draft's
  AC1 conflicts with the lock as-implemented (H-3); otherwise compatible.
  CONDITIONAL PASS (passes after scoped H-3 amendment; default, kill-switch,
  fail-closed, and identity semantics preserved).
- PP-T3 (reference-adapter pattern, firewall, ceilings, logs-only audit, no
  hidden retries, no second identity): the draft mirrors the pattern in prose
  ("validator + error-map + identity rules mirror T3 firewall") but lacks the
  exact mechanisms (H-2, M-1, M-5, M-6). No T3 file or behavior is touched by
  the draft. CONDITIONAL PASS (passes after reconciliation; PP-T4 must not
  regress T3 — regression-gated).
- What PP-T4 may change vs frozen: NOTHING frozen changes except the single
  scoped resolver amendment (H-3: accept `external_reference` → fixture binding;
  default/kill-switch/fail-closed preserved). All T1 translation surfaces,
  queue/lifecycle/schema, and the transcription path stay untouched.

## E. PP-T5 / PP-T6 boundary (pre-reconciliation)

Must NOT be implemented in PP-T4: vendor selection and any live
credentials/traffic/customer-text egress; per-vendor classes; automatic
routing/failover/retry schedulers; kill-switch procedure/runbook/alerts,
secrets rotation procedure, spend/billing/cost controls (PP-T5 — T4 emits
fields/logs only); integration-gate execution and verdict reporting (PP-T6
FINAL_GATE_ONLY); client-side/Auto/Local UI; durable tables or migration;
transcription-path changes; queue/lifecycle redesign; schema changes.
PP-T5/PP-T6 stay BACKLOG / NOT AUTHORIZED.

## F. Review verdict

`PP-T4 = NOT_READY` on the as-authored 86-line draft (3 HIGH + 10 MEDIUM
require reconciliation). No BLOCKER. All findings are contract-precision gaps;
none reopen a closed decision and none require vendor, schema, or architecture
choices beyond the non-controversial canonical options recorded in
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`. Proceed to reconciliation (§F of
the Step-1 prompt); then a fresh confirmation must return READY-eligible before
any promotion or authorization.
