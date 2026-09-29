# PP-T4 — External Translation Reference Adapter (vendor-neutral, credential-less)

## 1. Status

`DONE — CLOSED BY HPO (DECISION-PP-T4-CLOSURE-001)`

Step-2 execution record (2026-09-28): lifecycle
`READY → IN_PROGRESS → REVIEW` under
`DECISION-PP-T4-EXECUTION-AUTHORIZATION-001`, strictly within this contract.
Implementation: 5 new production files (`ReferenceExternal*` in
`app/Translation/`), scoped `external_reference` amendment in
`TranslationProviderResolver` (+ config comment), 1 test-support fake,
2 focused test files (40 tests, 41 after corrective). Builder verification:
focused 40/40 pass (151 assertions); PP-T1/PP-T2 translation + resolver +
PP-T3 suites 81/81; translation persistence/orchestration/retry/fence/job
suites 48/48; staleness filter 15/15; Revision filter first run 142/143
(one unrelated `RevisionHistoryActivationTest` order-dependent flake,
passes in isolation 12/12 and on rerun 143/143); Export filter 45/45;
full suite 1213 (1208 passed, 5 pre-existing skips, 0 failures, clean
first run); Pint clean (1 auto-fix: unused import); PHPStan 0 errors
(1 finding fixed at cause). AC1–AC12 all PASS on executed evidence. Scope
audit clean (no migration/queue/lifecycle/transcription/fallback/routing/
vendor/scheduler/alerts/spend change; frozen interfaces untouched;
PP-T5/PP-T6 untouched). Builder claims neither VERIFIED nor DONE.

Independent review (2026-09-28, `reviews/PP-T4-INDEPENDENT-REVIEW.md`):
`PP-T4 = CHANGES_REQUESTED` (cycle 1) for one MEDIUM (PP-T4-REV-01:
validator-rejection runs left no adapter audit record); all AC codes PASS;
scope audit clean; no BLOCKER/HIGH. Notes PP-T4-REV-02/03 (INFO) harmless.

Corrective cycle 1 (2026-09-28): every terminal outcome now logged
(log-then-rethrow, no mapping change) with a new AC6 regression test.
Re-verified: focused 41/41 pass (155 assertions); Pint clean; PHPStan
0 errors. Still REVIEW; still no VERIFIED/DONE claim.

Independent re-review (2026-09-28,
`reviews/PP-T4-CORRECTIVE-CYCLE1-RE-REVIEW.md`):
`PP-T4 (corrective cycle 1) = VERIFIED` on independently reproduced
evidence (AC1–AC12 PASS; scope audit clean; no BLOCKER/HIGH/MEDIUM).
Lifecycle `REVIEW → VERIFIED` evidenced by the re-review artifact.

Closure (2026-09-28, HPO `DECISION-PP-T4-CLOSURE-001`): HPO accepts PP-T4;
canonical transition `VERIFIED → DONE`. Full lifecycle history:
`READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`. PP-T5/PP-T6 remain
BACKLOG / NOT AUTHORIZED; closure authorizes no later work.

Step-1 history (2026-09-28; governance/readiness only, no implementation):

- Deferment release `DECISION-PP-T4-DEFERMENT-RELEASE-001`: ADR-027 Wave-1
  deferment (`DECISION-PROCESSING-PROVIDER-OPTION1-001`) released for readiness
  on Step-1 evidence (PP-T1/PP-T2/PP-T3 DONE; Step-1 HPO prompt as the later
  wave authorization for governance; vendor selection stays deferred per H-1).
  PP-T5/PP-T6 remain BACKLOG / NOT AUTHORIZED.
- Readiness review `reviews/PP-T4-READINESS-REVIEW.md`: `NOT_READY`
  (0 BLOCKER, 3 HIGH, 10 MEDIUM, 3 LOW, 1 INFO — all contract-precision gaps).
- Reconciliation `DECISION-PP-T4-CONTRACT-RECONCILIATION-001`: single
  fixture-shaped reference adapter (H-1); scalar ctor + named transport seam,
  no new global keys (H-2); scoped `external_reference` fixture selection with
  fail-closed preserved (H-3); canonical §9 error table (M-1); frozen
  validator + writer enforcement, single-shot 1:1, no chunking (M-2);
  manual-only retry + attempt-token fencing + reused requestId (M-3); 300s
  provider ceiling honored (M-4); adapter-internal segment/char ceilings (M-5);
  §11 log checklist (M-6); kill-switch AC (M-7); source/target language rules
  (M-8); T4-enforces/T5-owns split (M-9); additive staleness rule + F-001 gate
  (M-10); LOWs resolved inline; §§2/6/8/9/10/11/13/14/15/19 amended. Review
  artifact itself unchanged.
- Fresh confirmation `reviews/PP-T4-READINESS-CONFIRMATION.md`:
  `READY-ELIGIBLE` (no unresolved BLOCKER/HIGH/MEDIUM).
- Promotion `DECISION-PP-T4-READY-PROMOTION-001`: `BACKLOG → READY`.
- Execution authorization `DECISION-PP-T4-EXECUTION-AUTHORIZATION-001`:
  PP-T4 may move `READY → IN_PROGRESS` when work begins, strictly within this
  contract. PP-T5/PP-T6 remain unauthorized.

Step-2 execution (2026-09-28): lifecycle `READY → IN_PROGRESS` under
`DECISION-PP-T4-EXECUTION-AUTHORIZATION-001`, strictly within this contract.
Implementation: 5 new production files (`ReferenceExternal*` in
`app/Translation/`), scoped `external_reference` amendment in
`TranslationProviderResolver` (+ config comment), 1 test-support fake,
2 focused test files (40 tests). Builder verification: focused 40/40 pass
(151 assertions); PP-T1/PP-T2 translation + resolver + PP-T3 suites 81/81;
translation persistence/orchestration/retry/fence/job suites 48/48;
staleness filter 15/15; Revision filter first run 142/143 (one unrelated
`RevisionHistoryActivationTest` order-dependent flake, passes in isolation
12/12 and on rerun 143/143); Export filter 45/45; full suite 1213
(1208 passed, 5 pre-existing skips, 0 failures, clean first run); Pint
clean (1 auto-fix: unused import); PHPStan 0 errors (1 finding fixed at
cause). AC1–AC12 all PASS on executed evidence. Scope audit clean (no
migration/queue/lifecycle/transcription/fallback/routing/vendor/scheduler/
alerts/spend change; frozen interfaces untouched; PP-T5/PP-T6 untouched).
Builder claims neither VERIFIED nor DONE.

Builder lifecycle transition (2026-09-28): `READY → IN_PROGRESS → REVIEW`.
Next: independent review before any VERIFIED or DONE transition.

## 2. Objective

Define and build a vendor-neutral external translation adapter boundary
(request/response/identity/alignment/errors/determinism) as exactly ONE
fixture-shaped reference adapter implementing the frozen T1
`TranslationProvider` — buildable and testable with fixtures and mocked
responses, with no live credentials and no vendor traffic.

Reconciled 2026-09-28 (H-1): with vendor selection deferred (ADR-027), PP-T4
builds a single reference adapter proving the boundary. Per-vendor classes
require a separate provider-specific addendum + HPO authorization (§18).

Translation is single-shot 1:1 (no chunk fan-out): one invocation → one
segment-aligned `TranslationResult` → single atomic persist path. There is no
overlap/dedup rule (explicitly N/A, unlike PP-T3); alignment is enforced by the
frozen validator + writer, never recomputed by the adapter.

## 3. Why

Option 1 needs a provable external-translation seam without selecting a vendor
or granting live-call authority. T4 proves alignment/identity/determinism while
T5 governs enablement — the exact transcription-side precedent PP-T3 set.

## 4. Dependencies

Implementation requires PP-T1 DONE + PP-T2 DONE (both satisfied:
`DECISION-PP-T1-CLOSURE-001`, `DECISION-PP-T2-CLOSURE-001`) and PP-T3 DONE
(pattern precedent: `DECISION-PP-T3-CLOSURE-001`). Planning inputs:
`ARCHITECTURE-PLAN-OPTION1.md` §§4,6,8–10; `EXTERNAL-LANDSCAPE.md`;
`CURRENT-TRANSLATION-BOUNDARY.md`; PP-T3 reconciled contract (pattern mirror).

## 5. Inputs / frozen contracts

T1 `TranslationProvider` iface + text-only `TranslationInvocation`
(UUID `requestId` minted once in `::create()`) + `TranslationRequest`
(segment-aligned, IDs-only, text-only) + strict `TranslationResponseValidator`
(count/index/±0.0005s/source-echo/target-match, `failureFromCode` with
`ProviderFailed` default) + `TranslationResult` (segment-aligned +
provider/model identity) + 10-case `TranslationFailure` + frozen
`isRetryable()` + `TranslationException` (carries `TranslationFailure`) +
`TranslationLifecycle` (pending → queued → translating → completed/failed;
failed → queued manual only) + atomic idempotent `TranslationResultWriter`
(attempt-token fencing, completed immutable, machine-source-only reads) +
additive staleness (`stale_at`/reason/`causing_revision_id`); T2
`TranslationProviderResolver` + naming convention + identity fields +
kill-switch + fail-closed behavior; translation queue/timeout invariant
(`timeout_seconds` 300 < job 330 < retry_after 420); manual-only retry
(`ProcessTranslation::$tries = 1`); P6-001/P6-002 revision guards.

## 6. Scope

- Request preparation through ONE reference adapter behind the T1 interface,
  with private shape-builder/validator/error-map helpers (public ctor takes
  scalars + the single dispatch seam — see §8; reconciled H-2).
- Planned surface (Step 2 creates; stated here so scope is auditable): 5 new
  production files (`ReferenceExternalTranslationTransport` seam interface,
  `ReferenceExternalTranslationRequest` outbound DTO,
  `ReferenceExternalTranslationResponse` shaped success/failure,
  `ReferenceExternalTranslationFailureKind` vendor-neutral kinds,
  `ReferenceExternalTranslationProvider` adapter, all in `app/Translation/`),
  1 test-support fake, 2 focused test files. No other production file gains
  behavior except the scoped resolver amendment (§8, H-3).
- Segment-aligned response mapping (target/segments/text) through the frozen
  validator; provider/model identity capture (reusing the invocation
  `requestId`, §8).
- Canonical error mapping per the §9 table (taxonomy authoritative, vendor flag
  advisory).
- Timeout behavior: honor `config('translation.timeout_seconds')` (300) as the
  provider ceiling via the timeout ctor arg default; no new timeout keys (M-4).
- Idempotency/retry implications per §9 (existing attempt-token fencing;
  adapter performs no internal retry; parent retry unchanged, M-3).
- Adapter-internal request-size ceilings via ctor scalars (`maxSegments`,
  `maxPayloadChars`; contract-pinned generous defaults, tests inject small
  values to prove enforcement); product behavior unchanged — translation has
  no product byte limit and ceilings are adapter-internal, never product
  changes (M-5).
- Single-shot dispatch: one invocation → one shaped response → frozen
  validator → one `TranslationResult` → single atomic persist path (existing
  writer). No chunking, no overlap/dedup, no recomposition (M-2).
- Logs-only per-request audit per the §11 checklist (M-6).
- Determinism: same input + pinned version → byte-identical output asserted by
  rerun; any intentional behavior change → version bump recorded in model
  identity (L-3).
- Privacy/egress disclosure for the reference adapter shape (what WOULD leave:
  segment-aligned source text + metadata; never media, never ownership data).

## 7. Non-scope

Vendor selection; live credentials/traffic/customer-text egress; per-vendor
classes (require a provider-specific addendum + HPO auth); automatic
routing/failover/retry schedulers; transcription-path changes; client-side;
Local/Auto UI; kill-switch procedure/runbook/alerts and spend/billing controls
(PP-T5 — T4 emits fields/logs only, M-9); integration gate
execution/verdict (PP-T6 FINAL_GATE_ONLY); durable tables (no migration in
PP-T4; any claimed need → STOP + HPO escalation, L-2); queue/lifecycle
redesign; schema changes; new global config keys (H-2 — deferred to the future
vendor-binding addendum); general translation unlocking (H-3 — the scoped
`external_reference` fixture value is the only exception).

## 8. Architecture / behavioral contract

- The reference adapter implements the frozen T1 `TranslationProvider`;
  vendor structs die inside the adapter (firewall rule). No vendor
  JSON/headers/confidence/scores leak past it.
- Construction (reconciled H-2): public ctor takes scalars only —
  `baseUrl`, `token`, `timeoutSeconds` (default: `config('translation.timeout_seconds')`),
  `providerKey`, `modelPinned`, `maxSegments`, `maxPayloadChars` — plus the
  single dispatch seam `ReferenceExternalTranslationTransport` (explicitly
  named here; PP-T3-REV-01 lesson). No new global config keys; tests inject
  explicit fixture values (H-2). Production construction with an empty token
  fails closed with `ConfigurationError` before any dispatch; tests never set
  real env secrets.
- Single-shot 1:1 semantics (reconciled M-2): build the text-only outbound
  payload from the invocation (`TranslationRequest` shape) → dispatch once
  through the seam → validate the shaped response with the frozen
  `TranslationResponseValidator` (count/index/±0.0005s/source-echo/
  target-match) → construct `TranslationResult` with alignment copied from
  the invocation source (index/timestamps/source language, never provider) →
  single atomic persist path (existing writer, machine-source-only reads).
  The first mapped failure ends the attempt: no internal retry, no fallback.
- Request identity: reuse the invocation's existing `requestId` (minted once
  by `TranslationInvocation::create()`); the adapter mints no second logical
  request identity (M-3).
- Language rules (reconciled M-8): source echo via `LanguageIdentifier`
  (ms/en/zh/ta/und; `und` source allowed and echoed); target via
  `TranslationTarget::fromBcp47` (ms/en/zh/ta only — `und` or unsupported
  target rejected); mixed/code-switched source is a segment-level property,
  never a resegmentation license. LLM-style free-text output inadmissible
  unless segment-aligned + versioned (P6-001/P6-002 guard).
- Binding/selection (reconciled H-3): the adapter is reachable ONLY via the
  T2 resolver under the `translation.providers.*` convention (fixture binding
  `external_reference`); no other call site may select it. Scoped amendment:
  the resolver accepts exactly `external_reference` in addition to
  `self_hosted`; default stays `self_hosted`; kill-switch engaged (or invalid)
  → self-hosted regardless (reuses the PP-T2 mechanism, no new logic); any
  other non-`self_hosted` value remains a validation failure. General
  translation unlocking requires a fresh HPO decision.
- Staleness (reconciled M-10): edit-after-translate marks stale via the
  additive staleness schema without rewriting segments; machine source
  immutable; writer path unchanged.

## 9. Failure semantics

Canonical error-map table (reconciled M-1; frozen taxonomy authoritative,
vendor flag advisory only):

| Condition | Mapped code | Retryable |
|---|---|---|
| Transport timeout | `ProviderTimeout` | yes |
| HTTP 429 | `ProviderUnavailable` | yes |
| HTTP 5xx / unreachable | `ProviderUnavailable` | yes |
| HTTP 504 | `ProviderTimeout` | yes |
| HTTP 401/403/404/405 | `ConfigurationError` | no |
| HTTP 422 | `MalformedOutput` | no |
| Count/index-coverage failure | `MissingSegments` | no |
| Timestamp/source-echo/target mismatch, non-object shapes | `MalformedOutput` | no |
| Oversized-for-provider (pre-dispatch ceiling breach) | `InvalidRequest` | no |
| Empty-secret production construction | `ConfigurationError` | no (fail closed before dispatch) |
| Unsupported source tag | `UnsupportedSource` | no |
| Unknown | `ProviderFailed` | yes (frozen `failureFromCode` default) |

No new terminal state is invented; the frozen 10-case taxonomy stays
authoritative. Attempt failure after policy → parent attempt fails with the
mapped code; no partial persist (validator/writer reject before/inside the
transaction). Operator manual retry always creates a NEW parent attempt via
the existing retry action (ADR-022 precedent; `tries=1` unchanged); prior
attempts stay immutable; same-token completion stays idempotent and
different-token overwrite stays rejected via existing writer fencing.
Stale rules unchanged. No fallback to another provider inside the adapter
(no-fallback arm exists nowhere in the table).

## 10. Security/privacy constraints

No live egress in build/tests (fake transport, zero network calls); per-adapter
egress disclosure (what would leave: segment-aligned source text + metadata;
never media, never ownership data); secrets env-only and never logged/
committed/asserted-present (assert absence); production empty-secret → fail
closed before dispatch (H-3); ownership/authorize fences unchanged;
translation stays text-only; per-vendor DPA/retention/region required before
any future traffic.

## 11. Observability/audit requirements

Logs-only per-request records sufficient to reconstruct any run — checklist
(reconciled M-6), every record carrying: invocation `requestId` (reused),
`domain = translation`, `provider_key`, `model_pinned`, transcription/
translation ids, target language, outcome, timing ms, failure category.
Parent-row identity (`provider_key`/`model_pinned`/`request_id` +
`provider_class`) stamped via the existing PP-T2 job-layer lines. Secrets,
media bytes (N/A, text-only — stated), and full translated text absent from
all records (asserted). If review proves logs insufficient → STOP, escalate,
no silent schema (L-2).

## 12. Compatibility requirements

Downstream domains never branch on vendor; Phase 2/5/6/7 invariants hold
(media identity/checksum/storage/500 MiB; NLLB/self-hosted parity, identity,
staleness; revision/invalidation/history/export incl. the F-001
active-revision guard; queue/timeout invariants; retention/storage/backup);
transcription path untouched (assert PP-T3 suites green); machine source
immutable; revision/staleness/export unchanged. PP-T1 parity and PP-T2
resolution suites stay green (translation default still self-hosted;
kill-switch semantics unchanged).

## 13. Acceptance criteria

- [ ] AC1 — Adapter implements `TranslationProvider` and is selected only via
  the T2 resolver (fixture binding `translation.providers.external_reference`;
  assert wiring; assert no other selection call site; assert all other
  non-`self_hosted` values still rejected).
- [ ] AC2 — Fixture request mapping: synthetic provider payloads assert exact
  outbound fields (auth/pinned model/target/segments/requestId/timeout) with
  zero network calls (fixture credentials via ctor, env secrets empty — H-3).
- [ ] AC3 — Alignment: per-target fixtures (ms/en/zh/ta), `und`-source, and
  mixed/code-switch fixtures produce the expected segment-aligned result
  (assert count/index/timestamps/source-echo/target through the frozen
  validator).
- [ ] AC4 — No silent partial: count/index-mismatch fixture fails closed with
  the mapped error (assert `MissingSegments`/`MalformedOutput`, no partial
  persist, translation row untouched).
- [ ] AC5 — Error mapping table (§9) covers timeout/429/5xx/504/auth-status/
  422/partial/invalid/unknown/oversized/empty-secret/unsupported-source, each
  asserting the mapped 10-case code + `isRetryable()` value.
- [ ] AC6 — Identity: logs assert `request_id`/provider/model/translation ids/
  target/outcome/timing present; secrets/media-bytes/full-text absent.
- [ ] AC7 — Size ceiling: oversized request rejected before dispatch with
  `InvalidRequest` (assert code, zero dispatch — M-5).
- [ ] AC8 — No live credentials required to build/test (assert suite passes with
  empty provider env secrets — H-3).
- [ ] AC9 — Kill-switch engaged → resolution returns self-hosted even when
  selection names the PP-T4 fixture binding (assert zero adapter
  invocations — M-7).
- [ ] AC10 — Operator retry creates a new parent attempt (existing retry
  action); same-token completion idempotent, different-token overwrite
  rejected (existing writer fencing); invocation `requestId` reused, no second
  identity minted (assert — M-3).
- [ ] AC11 — Determinism: same input + pinned version rerun → byte-identical
  output (assert rerun equality — L-3).
- [ ] AC12 — Staleness: edit-after-translate path marks stale via the additive
  schema without rewriting segments (assert P6-005 behavior — M-10).

## 14. Testing requirements

Fixture + mocked-response suites (no HTTP egress — fake transport);
alignment tests per target + `und`-source + mixed (M-8); error-map matrix
(AC5); identity/log assertions incl. secret-absence and full-text-absence
scans (AC6); attempt-token fencing tests (AC10); kill-switch test (AC9);
parent-retry test (AC10); determinism reruns (AC11); staleness tests (AC12);
minimal fixture corpus (reconciled L-1): per-target, `und`-source,
mixed/code-switch, count-mismatch, index-mismatch, timestamp drift at the
±0.0005s boundary, source-echo mismatch, target mismatch, empty, invalid
shapes, timeout/429/5xx/partial/unknown errors, oversized, rerun pairs; full
suite + Pint + PHPStan.

## 15. Risks

Timestamp drift → frozen ±0.0005s validator + threshold tests. Resegmentation
→ validator reject + writer machine-source-only reads. Taxonomy gaps →
map-unknown per §9 table + tests (M-1). LLM non-determinism → versioned-output
rule + rerun asserts (L-3). Alignment loss → validator reject + no partial
persist. Cost/rate unknowns → ceilings + kill-switch procedure (PP-T5 owns
procedure; T4 enforces adapter ceilings only — M-9). Credential-less/auth
tension → ctor-injected fixtures, env empty (H-3). Scope creep into Wave-1
translation behavior → default stays self-hosted + regression gates.

## 16. Regression concerns

T1/T2 suites stay green (parity + resolution incl. fail-closed/kill-switch/
translation-lock-minus-scoped-fixture); PP-T3 transcription suites green (no
transcription behavior change); Phase 5 translation suites (NLLB identity/
lifecycle/staleness); Phase 6 suites (revision/invalidation/history/export
F-001 guard); no queue/lifecycle change. Any regression → STOP, fix within
PP-T4, no contract rewrite.

## 17. Rollback expectations

Resolver flag back to self-hosted; no data migration to undo (fixtures only;
no migration shipped — L-2). Document adapter disablement (remove/rename the
fixture binding; default selection already self-hosted).

## 18. Governance / authorization boundary

Contract authoring complete; execution authorized
(`DECISION-PP-T4-EXECUTION-AUTHORIZATION-001`, 2026-09-28) strictly within this
reconciled contract. Still forbidden without fresh authorization: vendor calls,
customer-text egress, spike execution, per-vendor classes (require a
provider-specific addendum + HPO auth), general translation unlocking, schema/
migration, queue/lifecycle redesign, transcription changes, PP-T5/PP-T6 work.
Vendor binding needs separate provider-specific addendum + HPO auth.

## 19. READY eligibility conditions

Satisfied 2026-09-28: PP-T1 DONE + PP-T2 DONE + PP-T3 DONE (T1/T2-stable
exceeded); deferment released (`DECISION-PP-T4-DEFERMENT-RELEASE-001`;
§19 vendor clause narrowed to vendor-neutral reference per H-1, vendor
selection stays deferred); contract review clean (fresh confirmation
`READY-ELIGIBLE`, no BLOCKER/HIGH/MEDIUM); single-reference-adapter scope
reconciled (H-1); construction via scalar ctor + named seam, no new global
keys (H-2); credential-less test rule + scoped fixture selection reconciled
without weakening PP-T2 fail-closed (H-3); canonical error table (M-1);
validator/writer enforcement + single-shot N/A-chunking (M-2); manual-only
retry + token fencing + reused identity (M-3); timeout/ceiling sources pinned
(M-4/M-5); log checklist embedded (M-6); kill-switch AC added (M-7); language
rules exact (M-8); T4/T5 boundary split (M-9); staleness rule + F-001 gate
(M-10); fixture corpus enumerated (L-1); no-migration explicit (L-2);
determinism rule versioned (L-3). Promotion recorded
(`DECISION-PP-T4-READY-PROMOTION-001`).

## 20. Exact next legal action

Begin PP-T4 — Step 2: Build, Verify & Close (implementation strictly within
this contract; builder moves `READY → IN_PROGRESS → REVIEW`; no VERIFIED/DONE
claim by the builder; PP-T5/PP-T6 remain unauthorized).
