# PP-T2 Readiness Review — Deterministic Provider Resolution & Configuration

Reviewer role: independent readiness/governance reviewer (Claude Code).
Scope: READINESS/GOVERNANCE REVIEW ONLY. No implementation, no production
file touched, no test written, no migration, no vendor call, no state
mutation except this artifact. No file other than
`reviews/PP-T2-READINESS-REVIEW.md` was created or edited during this
review.

---

## A. Baseline

Independently reconstructed from repository evidence, not from the
requester's claimed baseline.

| Task | Requester's claimed state | Independently verified state | Match? |
|---|---|---|---|
| PP-T1 | DONE | `tasks/PP-T1-provider-contracts-existing-adapters.md` §1: `DONE — CLOSED BY HPO (DECISION-PP-T1-CLOSURE-001)`. `DECISION_QUEUE.md` contains `DECISION-PP-T1-CLOSURE-001` (Status: DECIDED — HPO 2026-09-27, `VERIFIED → DONE`). `reviews/PP-T1-INDEPENDENT-REVIEW.md` verdict: `PP-T1 = VERIFIED`, no BLOCKER/HIGH. | MATCH |
| PP-T2 | BACKLOG | `tasks/PP-T2-provider-resolution-configuration.md` §1: `BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED`. No `DECISION-PP-T2-*` entry of any kind exists in `DECISION_QUEUE.md` (grepped; none found). No PP-T2 review artifact exists prior to this one. | MATCH |
| PP-T3 | BACKLOG | `tasks/PP-T3-external-transcription-adapter.md` §1: `BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED`. | MATCH |
| PP-T4 | BACKLOG | `tasks/PP-T4-external-translation-provider.md` §1: `BACKLOG — DEFERRED FROM WAVE 1 / CONTRACT_AUTHORED / NOT AUTHORIZED`. (Additionally, explicitly and durably deferred — stronger than plain BACKLOG.) | MATCH (with the DEFERRED qualifier) |
| PP-T5 | BACKLOG | `tasks/PP-T5-provider-operational-controls.md` §1: `BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED`. | MATCH |
| PP-T6 | BACKLOG | `tasks/PP-T6-integration-compatibility-verification.md` §1: `BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED`. | MATCH |

PP-T1 closure decision `DECISION-PP-T1-CLOSURE-001`: independently confirmed
present in `DECISION_QUEUE.md` (working-tree, uncommitted — see finding
H-1), with the durable-record cross-reference to `DECISIONS.md` ADR-027 and
to the task file's own status-history block. Confirmed real, not merely
asserted by the requester.

**Unauthorized-implementation check for PP-T2 specifically:**

- Repository-wide grep for `ProviderResolver|ProcessingPolicy|TranscriptionProviderResolver|TranslationProviderResolver` under `app/` returns **zero hits**. No PP-T2 code exists anywhere.
- No new config keys matching PP-T2's proposed shape (`transcription.provider`, `translation.provider=self_hosted|external_*`, kill-switch flags) exist in `config/transcription.php` or `config/translation.php` (both read in full; see §E below) beyond the pre-existing, unrelated `translation.provider` identity-label key (see finding under §E).
- `git status` shows no `app/**Resolver*`, no `app/**Policy*`, no new config files, no new migrations. The only application code changed in the working tree is PP-T1's scope (two adapter files, two ServiceProvider bindings).
- Conclusion: **no unauthorized PP-T2 implementation exists in the repository.** The working tree is exactly where the governance record says it is: T1 DONE, T2 contract-authored-only.

**Governance-artifact currency gap (not a PP-T2 defect, but material to
this review's baseline confidence):** `CURRENT_STATE.md`, `plan.md`, and
`AGENTS.md` — the three canonical operational-state files per the
source-of-truth ordering — contain **no reference whatsoever** to the
ProcessingProvider initiative, ADR-027, or any `PP-T*` task. All three
files' latest entries concern Phase 7 Wave 3A / P7-011 / P7-009 Phase A
(dated 2026-09-26/27) and do not mention PP-T1's closure, which the
working-tree `DECISION_QUEUE.md`/`DECISIONS.md` record as having happened
on 2026-09-27. This is recorded as **Finding H-1** below; it does not
change the PP-T2 verdict (the task-file and decision-queue evidence is
internally consistent and sufficient on its own per source-of-truth
ordering item 6 "the relevant task file"), but it means the currently
higher-precedence files (items 4–5, `plan.md`/`CURRENT_STATE.md`) are
silent on this entire initiative and must not be relied upon as
confirmation.

---

## B. PP-T2 Contract Summary

Source: `tasks/PP-T2-provider-resolution-configuration.md` (full read).

- **Title**: Deterministic Provider Resolution & Configuration.
- **Objective**: Define deterministic per-domain provider selection +
  configuration with observable identity, safe failure, self-hosted
  default, translation locked to self-hosted, and a deployment-free kill
  switch. No automatic fallback.
- **Problem it answers**: HPO requires explicit routing ownership
  (`ProcessingPolicy → ProviderResolver → provider`, per domain) so
  overflow capability can never become silent rerouting.
- **Authorized scope (§6)**: per-domain resolver
  (`TranscriptionProviderResolver`, `TranslationProviderResolver`) at the
  orchestrator seam; deterministic read of `transcription.provider` /
  `translation.provider`; config keys + env convention; boot validation
  (unknown name / missing URL+token when external / invariant violation /
  unpinned model → boot exception); provider identity stamping
  (`provider_key`/`model_pinned`/`request_id`) into logs/context only
  (durable columns require separate approval); kill switch (config flip →
  self-hosted, no code deploy).
- **Explicit non-scope (§7)**: live vendor integration; external API code;
  client-side processing; automatic routing/benchmarking; pricing/billing;
  dynamic selection; new queues.
- **Dependencies (§4)**: PP-T1 contract complete, implementation-gated on
  "T1 VERIFIED/DONE before T2 implementation unless canonical policy
  allows a reviewed combined wave; prefer sequential."
- **Architectural ownership**: resolver lives "beside ServiceProvider, one
  per domain"; resolvers are "pure selection (no inference, no network)";
  providers never call each other.
- **Expected production surfaces**: two new resolver classes (one per
  domain), config-key additions to `config/transcription.php` /
  `config/translation.php`, boot-time validation wiring, a kill-switch
  read path, identity-stamping into logs/context (no new DB columns
  without separate approval).
- **Expected test surfaces (§14)**: resolver unit tests (deterministic
  matrix), config validation tests, kill-switch tests, identity-stamping
  tests, secret-absence assertions, full suite + Pint + PHPStan; explicitly
  "no network egress in tests."
- **Acceptance criteria (§13)**: 8 items, enumerated and matrixed in §D
  below.
- **Completion gate**: all 8 ACs pass; full suite/Pint/PHPStan green; no
  BLOCKER/HIGH finding; independent review; HPO closure (per the
  orchestration-policy State-to-Action Contract — this task file does not
  purport to bypass that contract, and none of its wording does).
- **Forbidden changes (§7/§16)**: no live vendor integration, no external
  API code, no client-side work, no automatic routing/benchmarking, no
  pricing/billing, no dynamic selection, no new queues; "T2 must not alter
  normalized outputs"; T1 parity suites must stay green; Phase 3/5
  orchestration tests must stay green.
- **Known open HPO decisions** (per §19 "READY eligibility conditions"):
  T1 VERIFIED/DONE (now satisfied); contract review (not yet performed —
  no `reviews/PP-T2-CONTRACT-REVIEW.md` exists); config-key names
  reconciled with repo conventions (not yet done); kill-switch mechanism
  approved by HPO (not yet recorded as a discrete HPO decision — see
  Finding H-2 in §I).

---

## C. Dependency Assessment

| Dependency | Classification | Evidence / status |
|---|---|---|
| PP-T1 (interfaces + self-hosted adapters + bindings) | **Hard** | DONE (`DECISION-PP-T1-CLOSURE-001`); independently reproduced VERIFIED evidence in `reviews/PP-T1-INDEPENDENT-REVIEW.md`. Satisfied. |
| ADR-027 / `DECISION-PROCESSING-PROVIDER-OPTION1-001` (architecture direction) | **Hard** | ACCEPTED, HPO, 2026-09-27 (`DECISIONS.md`). Satisfied — but only in the **uncommitted working tree** (see Finding H-1); not yet a committed durable record. |
| Processing-Provider Option 1 discovery/architecture plan | **Soft (informational input)** | `discovery/processing-provider/ARCHITECTURE-PLAN-OPTION1.md` explicitly headed "PLANNING ONLY"; used as design input, not itself an authorization source. T2's contract already treats it this way (cites it under "Inputs," not "Dependencies"). Consistent. |
| Frozen Phase 3 transcription contract (`TranscriptionProvider`, `NormalizedTranscript`, taxonomy, queue/timeout invariants) | **Hard** | Confirmed unmodified: `git diff --stat` shows zero diff on `app/Transcription/TranscriptionProvider.php`; `config/transcription.php` unmodified; `job_timeout_seconds`/`retry_after_seconds`/`timeout_seconds` invariant (300/330/420) intact and read directly from the config file. T2's resolver sits *above* this seam (at "orchestrator seam," per §6) and its contract explicitly states "No lifecycle/queue/schema change" (§12) — consistent with the frozen contract if honored. |
| Phase 5 translation contracts (`TranslationProvider`, strict validator, staleness) | **Hard** | Confirmed unmodified (`app/Translation/TranslationProvider.php` zero diff). T2 §8 explicitly restricts translation resolution to `self_hosted` only in Wave 1 ("any other value → validation failure") — this is a hard compatibility constraint the contract itself enforces, not merely respects. |
| Phase 6 revision/staleness/export contracts | **Non-dependency for T2's stated scope** | T2's scope is provider *selection*, not revision/export/staleness. No T2 clause touches `Editing/` or export code. Confirmed no reference to revision/staleness/export anywhere in the T2 task file. Correctly out of scope. |
| Existing container bindings (`TranscriptionServiceProvider`, `AppServiceProvider`) | **Hard** | T2 must extend these bindings to route through the new resolvers rather than directly to `SelfHostedTranscriptionProvider`/`SelfHostedTranslationProvider`. Current state (post-T1) confirmed by direct read: both bind the *concrete self-hosted class* to the interface directly. T2's resolver must sit between the container binding and the concrete adapter, or the "no code outside the ServiceProvider constructs a concrete provider" property (T1 AC4, now generalized) must extend to "no code outside the resolver selects a concrete provider" — this is an architecture decision T2's own contract already anticipates (§6: "resolver lives beside ServiceProvider") but does not fully specify the wiring pattern (does the ServiceProvider call the resolver, or does something new call both?). Flagged as a **contract-completeness gap**, not a blocker (see §D, Finding M-1). |
| Current worker transport contracts (Bearer, IDs-only payload, envelope 1.0) | **Hard** | Unaffected by T2's stated scope; T2 explicitly excludes "live vendor integration; external API code" (§7). Resolver only selects *which bound implementation* to use; it does not touch transport. Consistent. |
| Existing `requestId` minting (`TranscriptionInvocation::create()`, `TranslationInvocation::create()`) | **Soft (already-satisfied infrastructure)** | Independently verified: both `TranscriptionInvocation` and `TranslationInvocation` already mint `requestId: (string) Str::uuid()` in their `create()` factory (pre-existing, pre-PP-T1 code, confirmed by direct source read at `app/Transcription/TranscriptionInvocation.php:51` and `app/Translation/TranslationInvocation.php:63`). T2 §8 states "`request_id` minted once at orchestration, carried through Invocation/outbound/logs" as if this needs to be introduced; in fact the minting already exists and only needs to be *read into* resolver logging, not created. This is favorable to readiness (lower implementation risk than the contract implies) but the contract's wording could mislead an implementer into re-minting a second `request_id` at the resolver seam, creating two competing identifiers. Flagged as **Finding M-2** below (wording precision, non-blocking). |
| Future PP-T3/T4/T5/T6 behavior | **Future-integration (correctly deferred)** | T2 §7 excludes external providers, and its scope is written vendor-neutral (a resolver that can select a fixture/test binding named `external_x` without knowing what a real external adapter looks like). This is architecturally sound sequencing: T2 does not need T3 to exist, only to reference an arbitrary bound name. No premature coupling found. |

---

## D. Contract Completeness Review

Evaluated only where relevant to PP-T2's actual stated scope (per the
task file itself), not against an abstract exhaustive checklist.

- **Module boundary**: Adequately stated — "one per domain," "beside
  ServiceProvider." Gap: does not specify whether the resolver is itself
  container-bound (e.g., `app(TranscriptionProviderResolver::class)`) or a
  plain invoked helper class constructed ad hoc inside the ServiceProvider
  closure. Both are legitimate implementations of the stated contract, but
  the ambiguity could produce architecturally different (though
  behaviorally compatible) results from two implementers. Non-blocking —
  ordinary implementation latitude, comparable to PP-T1's own
  "rename/wrapper/adapter-agnostic" latitude which that contract's review
  found acceptable.
- **Public interfaces**: Not specified as a formal PHP interface/contract
  for the resolver class itself (unlike T1, which froze
  `TranscriptionProvider`/`TranslationProvider` signatures). This is
  appropriate for T2 — the resolver is new, internal, and does not need a
  pre-frozen public signature the way an existing production seam does.
- **Required behavior**: Deterministic selection is precisely specified
  ("same input+config → same provider, 100% deterministic; resolvers are
  pure selection (no inference, no network)"). This is testable and
  unambiguous.
- **Persistence**: Correctly scoped to "logs/context" only, explicitly
  deferring durable columns to separate approval (§6, §11). This avoids a
  P6-009/F-001-style silent schema assumption. Good contract discipline.
- **Transport**: Explicitly out of scope (§7 "Live vendor integration");
  consistent with dependency table above.
- **Configuration ownership**: §6/§8 specify config keys per domain and
  boot-time validation, but the **exact key names and env-variable
  convention are explicitly left open** ("adapted to repo norms" — §6).
  §15/§19 flag this as an open item ("Flag-name drift → canonical key
  table in contract" / "config-key names reconciled with repo
  conventions"). This is a genuine, currently-unresolved contract gap —
  not a defect in judgment (deferring bikeshed-prone naming to
  implementation-adjacent contract review is reasonable), but it is a
  concrete precondition the contract's own §19 lists as unmet. See
  Finding M-3.
- **Lifecycle**: N/A to T2's scope (resolver is a pure function called
  per-request, no lifecycle of its own).
- **Failure/fallback behavior**: Very well specified (§9) — fail-closed on
  invalid value, fail-closed on missing credentials, explicit "no fallback
  attempt" requirement, and kill-switch semantics for degrade-to-self-hosted.
  This is one of the strongest sections of the contract.
- **Idempotency**: Not separately addressed, but correctly so — a pure,
  stateless selection function has no idempotency concern of its own (it
  is not writing anything). No gap.
- **Concurrency**: Not addressed. Since resolution reads config (already
  process-wide, effectively-immutable at request time under normal Laravel
  config caching) and has no write path, this is a legitimate omission,
  not a gap — flagged only for completeness, not as a finding.
- **Observability/audit**: Well specified for T2's tier (§11) — logs
  every resolution with `request_id`, domain, `provider_key`,
  `model_pinned`, config source; explicitly defers dashboards/alerts to
  T5. Correct boundary discipline (mirrors the deferred-durable-schema
  discipline above).
- **Security boundaries**: §10 is specific — no silent fallback, external
  path requires explicit config, secrets env-only/never logged/committed,
  attributable selection changes. Adequate for T2's scope.
- **Network-egress boundaries**: §14 explicitly requires "No network
  egress in tests." Consistent with §7 non-scope (no live vendor
  integration). Adequate.
- **Backward compatibility**: §12 states "Default config routes exactly
  today's behavior. No lifecycle/queue/schema change. Phase 1–7 untouched."
  This is testable and, cross-checked against current `config/*.php`
  defaults (`self-hosted` / no `provider` key currently drives class
  selection at all — see §E below), consistent with what would need to
  happen for AC1 to hold.
- **Testability**: §13's 8 ACs are each concretely assertable (see §D
  matrix). §14 test list is proportionate.
- **Deterministic acceptance criteria**: All 8 ACs are objectively
  checkable (see next section) — no vague "should feel correct" criteria.

No contract-completeness gap found rises above MEDIUM; see consolidated
Findings in §H.

---

## E. Acceptance-Criteria Matrix

Every AC in `tasks/PP-T2-provider-resolution-configuration.md` §13,
verified against current source, not merely paraphrased.

| # | Requirement summary | Testable? | Ambiguity | Dependency | Readiness |
|---|---|---|---|---|---|
| 1 | Default config selects `self_hosted` for both domains (stock env) | Yes — direct assertion on resolver output with default env | None — the phrase "self_hosted" as a config *value* does not exist yet (current `config/translation.php`'s `provider` key holds `'self-hosted'` as an **identity label only**, confirmed used solely for logging/persisted-identity per `AppServiceProvider.php:47` and the PP-T1 contract review's own finding — it does not currently drive any class selection). T2 must introduce a **new** selection key (or repurpose the existing one carefully) without breaking the existing identity-label usage. This dual-use risk is real but is squarely T2 implementation work, not a contract defect. | T1 (satisfied) | READY |
| 2 | Explicit `transcription.provider=external_x` selects the named external binding (fixture binding, no network) | Yes — bind a fixture/test double under a name, assert resolver returns it | None significant — "fixture binding" phrasing correctly avoids requiring T3 to exist | T1 only (T3 not required, per contract's own wording) | READY |
| 3 | Invalid provider value fails validation/boot with a named error | Yes — assert exception class/message/code | None | T1 (satisfied) | READY |
| 4 | Missing URL/token for selected external fails closed (no fallback attempt observed) | Yes — assert zero fallback invocation via a spy/double | None | T1 (satisfied) | READY |
| 5 | Translation with non-`self_hosted` value is rejected | Yes — assert validation failure for any other value | None | T1 (satisfied) | READY |
| 6 | Kill-switch control forces both domains to self-hosted within one flag refresh (assert resolution output) | Yes, **conditionally** — testable only once the specific kill-switch *mechanism* (config flag vs. cache-cleared command vs. env var) is chosen, since "one flag refresh" implies a specific refresh semantic (e.g., `config:clear`, cache TTL, or a dedicated command) that the contract does not name | **Yes — genuine ambiguity.** §19 itself lists "kill-switch mechanism approved by HPO" as an unmet READY-eligibility condition, and no HPO decision record for it exists in `DECISION_QUEUE.md` (grepped: no `kill-switch`/`kill switch` decision entry found anywhere in the queue). This is the single most concrete open item blocking full readiness. | T1 (satisfied); **HPO kill-switch mechanism decision is NOT satisfied** | **NEEDS_CLARIFICATION** |
| 7 | Provider identity (`provider_key`/`model_pinned`/`request_id`) present in logs/context; no secret present | Yes — assert structured-log fields, assert absence of secret substrings | None; `request_id` already exists as infrastructure (see §C) — lower risk than contract implies | T1 (satisfied) | READY |
| 8 | No silent fallback: fault-injected self-hosted failure never triggers external call | Yes — inject failure, assert zero external invocations via spy/fake | None | T1 (satisfied) | READY |

**Summary**: 7 of 8 ACs are READY as written. AC6 is
**NEEDS_CLARIFICATION** because the contract's own §19 lists the
kill-switch mechanism as HPO-unapproved, and no such approval exists in
the decision record as of this review. This is not a defect in the
contract's authoring quality (the contract correctly flags it as an open
item rather than silently assuming a mechanism) — but it is a genuine,
currently unresolved precondition to full READY status.

---

## F. Architecture Compatibility

- **`TranscriptionProvider::transcribe(Invocation): NormalizedTranscript`**
  and **`TranslationProvider::translate(Invocation): TranslationResult`**:
  confirmed byte-unmodified (zero `git diff` against both interface files).
  T2's contract does not propose any signature change (§7 explicitly
  excludes "dynamic selection" logic from living inside the providers
  themselves) — the resolver sits *above* the interface, selecting *which*
  bound implementation is constructed, never altering the interface the
  orchestrator calls. Compatible.
- **`SelfHostedTranscriptionProvider` / `SelfHostedTranslationProvider`**:
  both are zero-override subclasses of the frozen `Http*Provider` classes
  (confirmed by direct source read — each file is an 18-line class
  declaration with an empty body and a docblock). T2 does not need to
  modify these; the resolver simply needs to be able to construct/resolve
  them by a config-driven name. Compatible, no conflict.
- **`HttpTranscriptionProvider` / `HttpTranslationProvider`**: These do
  **not yet exist as named "External"-anything** — they are the pre-PP-T1
  self-hosted worker clients, now wrapped by the `SelfHosted*` subclasses.
  T2's contract correctly does not assume any `HttpExternal*Provider`
  class exists yet (that is PP-T3/PP-T4 territory, both still BACKLOG).
  Compatible.
- **`TranscriptionServiceProvider` / `AppServiceProvider`**: currently
  (post-T1) bind the interface directly to the concrete `SelfHosted*`
  class inside a closure (confirmed by direct read of both files' `git
  diff` and current content). T2 will need to change these closures to
  delegate to the new resolver rather than hardcoding the concrete class.
  This is exactly the kind of change T2's own scope describes ("resolver
  lives beside ServiceProvider") — no incompatibility, but see Finding M-1
  (wiring pattern underspecified).
- **`ProcessTranscription` / `ProcessTranslation`** (the job/orchestration
  classes): confirmed (via grep, `app/Jobs/`) to depend only on the
  `TranscriptionProvider`/`TranslationProvider` **interfaces** via
  constructor/method injection, never on any concrete class name. T2's
  resolver-based indirection is fully transparent to these classes — they
  will continue to receive whatever the container resolves to the
  interface, exactly as today. No change required in these files, and the
  contract does not propose any. Compatible.

No PP-T2 clause invalidates the frozen provider-interface contract or the
existing self-hosted adapter classes. Architecture compatibility: **PASS**.

---

## G. Task Boundary (PP-T2 vs PP-T3–T6)

| Concern | PP-T2 | Later task | Notes |
|---|---|---|---|
| Deterministic per-domain provider selection | **Owns** | — | Core T2 scope |
| Config keys / env convention / boot validation | **Owns** | T5 extends with operational ceilings (ops-level config), not selection-level | T2 owns *selection* config; T5 owns *operational* config (size/concurrency/timeout ceilings) — read directly from both contracts, boundary is clean and non-overlapping |
| Kill switch (mechanism + control) | **Owns** (per T2 §6: "Kill switch... target `config flip → self-hosted`") | T5 additionally owns "kill-switch control + procedure" and treats "Kill-switch IS the rollback" as its own §17 | **Overlap identified** — both T2 and T5 claim kill-switch ownership. T2's version is the resolver-level mechanical implementation (the flag exists and the resolver honors it); T5's version is the operational/procedural layer (runbook, alerting on kill-switch state, rollback rehearsal). Read closely, this is a legitimate split (T2 = mechanism, T5 = operations/procedure around the same mechanism) rather than a true duplication, but the task files do not explicitly cross-reference each other on this point. Flagged as **Finding M-4** (non-blocking, wording/cross-reference gap). |
| Provider identity stamping in logs | **Owns** (emits fields) | T5 defines "Dashboards/alerts... T5 emits the fields" — wait, T2 §11 states "T2 emits the fields," T5 consumes/alerts on them | Correctly split: T2 emits, T5 governs/alerts. Confirmed by direct reading of both contracts — no contradiction, only initially looked ambiguous. |
| External transcription adapter (vendor-neutral boundary, chunking) | Not in scope | **T3 owns** | T2 references "external_x" only as an opaque resolver target; never defines adapter internals. Clean boundary. |
| External translation provider | Not in scope (translation locked to self-hosted in T2) | **T4 owns (deferred)** | T2 explicitly rejects any translation value other than `self_hosted`. Clean, and doubly-enforced (T4 is itself deferred). |
| Privacy/audit/abuse/cost operational ceilings, alerting, rollback rehearsal | Emits raw fields only | **T5 owns** | Confirmed clean split per above. |
| Cross-phase (2/3/5/6/7) compatibility verification / regression gate execution | Not in scope (T2 must merely *not break* these) | **T6 owns** (verification gate, FINAL_GATE_ONLY) | T2 is a *subject* of T6's eventual gate, not a verifier itself. Clean. |

No boundary violation found. The one cross-reference gap (kill-switch
split between T2 and T5) is stylistic/documentation-level, not a scope
conflict — see Finding M-4.

---

## H. Test / Regression Readiness

- T2's own test plan (§14) is proportionate to its scope: resolver unit
  tests (deterministic matrix), config validation tests, kill-switch
  tests, identity-stamping tests, secret-absence assertions, full
  suite+Pint+PHPStan, explicitly "No network egress in tests." This
  mirrors T1's test-plan rigor and is achievable without T3/T4/T5 existing
  (fixture/fake bindings suffice per AC2).
- **Regression boundary**:
  - *Phase 3 transcription*: T2 must not alter `NormalizedTranscript`
    output or timing/language semantics — confirmed T2's scope never
    touches mapping/normalization code, only which bound class receives
    the call. Low regression risk.
  - *Phase 5 translation*: same reasoning; further hard-gated by AC5
    (translation locked to self-hosted).
  - *Phase 6 revision/staleness*: T2 has zero surface area here (no
    `Editing/` reference anywhere in the contract). No regression risk
    from T2 itself; risk would only arise if PP-T2 implementation
    strayed outside its own contract (an implementer-discipline concern
    the independent reviewer would catch, not a contract gap).
  - *PP-T1 provider parity*: T2 sits above the parity-guaranteed
    zero-override subclasses; as long as T2's resolver constructs those
    same classes (rather than reimplementing construction), T1's parity
    guarantee is preserved by inheritance, unchanged.
  - *Queue processing / worker transport*: T2 explicitly excludes "new
    queues" (§7) and states "No lifecycle/queue/schema change" (§12).
    Confirmed no queue-adjacent files (`app/Jobs/*`) are referenced as
    in-scope anywhere in the T2 contract.
  - *Container composition*: the one point of genuine architectural
    change (ServiceProvider closures now route through a resolver instead
    of hardcoding a concrete class) is exactly what T2 exists to do, and
    is testable via the existing binding-test pattern T1 already
    established (`app(TranscriptionProvider::class)` resolves to the
    expected concrete class) — extending it to `app(TranscriptionProvider::class)`
    resolves to the *resolver-selected* class under varying config is a
    direct, low-risk extension of a pattern already proven in
    `tests/Feature/SelfHostedTranscriptionProviderTest.php`.

No BLOCKER/HIGH test-strategy gap found.

---

## I. Findings

```
ID: H-1
Severity: HIGH
Surface: CURRENT_STATE.md, plan.md, AGENTS.md (repo root) vs DECISIONS.md / DECISION_QUEUE.md (working tree, uncommitted)
Evidence: `git status` shows DECISIONS.md and DECISION_QUEUE.md as
  modified (uncommitted) with the entire ProcessingProvider initiative
  (ADR-027, DECISION-PROCESSING-PROVIDER-OPTION1-001,
  DECISION-PP-T1-READY-PROMOTION-001,
  DECISION-PP-T1-EXECUTION-AUTHORIZATION-001, DECISION-PP-T1-CLOSURE-001)
  appended as pure insertions. CURRENT_STATE.md, plan.md, and AGENTS.md —
  three files this repository's own source-of-truth ordering places above
  "the relevant task file" and above raw source/git-history evidence —
  contain zero mention of PP-T1, PP-T2, ADR-027, or "ProcessingProvider"
  anywhere (confirmed by full read of CURRENT_STATE.md, plan.md, and
  grep-verified absence in AGENTS.md). PP-T1's own closure (dated
  2026-09-27, same day) is not reflected in any of the three canonical
  operational-state files.
Why it matters: Per this repo's own `.ai/guidelines/ai-development-os.md`
  source-of-truth ordering, `plan.md` and `CURRENT_STATE.md` outrank the
  task file and outrank "source, schema, tests, configuration, CI, and Git
  history as evidence" — yet in this case the higher-ranked files are
  silent while the lower-ranked ones (task file + decision queue) carry
  the only record. This is exactly the kind of drift the repo's own
  governance model is designed to prevent (`ai-development-os.md`: "Do not
  rely on another agent's chat history or summary when repository state
  provides the answer" / "Important project information must not exist
  only in chat output"). It is currently *not* only in chat output, but it
  is also not yet in the two files an agent is instructed to trust most.
  Additionally, DECISIONS.md/DECISION_QUEUE.md are themselves uncommitted
  — a machine or agent restart before a commit would lose this durable
  record entirely, which is a materially different risk profile than a
  committed record.
Required resolution: Before or alongside PP-T2 READY promotion, the HPO
  (or an explicitly authorized reconciliation pass) should commit
  DECISIONS.md/DECISION_QUEUE.md and reconcile CURRENT_STATE.md/plan.md/
  AGENTS.md to record the ProcessingProvider initiative's existence and
  PP-T1's DONE status, consistent with how every prior phase/task closure
  in this repository's history has been reconciled into those three files.
Blocks READY: NO — the task-file + decision-queue evidence is internally
  consistent, current, and (per source-of-truth item 6) sufficient to
  evaluate PP-T2's own contract. This finding blocks *confidence in the
  durability* of the baseline, not the contract's readiness itself. It is
  raised as HIGH because of the uncommitted-state data-loss risk, not
  because it invalidates today's evidence.
```

```
ID: M-1
Severity: MEDIUM
Surface: tasks/PP-T2-provider-resolution-configuration.md §6, §8
Evidence: §6 states "Per-domain resolver... at orchestrator seam" and §8
  states "resolver lives beside ServiceProvider, one per domain," but
  neither specifies whether the resolver itself is container-bound and
  invoked by the ServiceProvider closure, or a static/plain helper
  invoked inline, nor whether `TranscriptionServiceProvider`/
  `AppServiceProvider` remain the sole call sites of the resolver (as T1's
  now-established "no code outside the ServiceProvider constructs a
  concrete provider" property would suggest by extension) or whether some
  other future seam (e.g., a middleware, a facade) is anticipated.
Why it matters: Two equally contract-compliant implementations could
  produce different container-architecture shapes, which would matter for
  T3 (adapter binding) and T6 (verification gate) reproducibility across
  implementers. This is the same class of latitude T1's contract review
  flagged for AC4 (resolved there by making the AC "binding-shape
  independent" rather than tied to a specific pattern) — T2 has not yet
  received the equivalent tightening.
Required resolution: Add one sentence to §6 or §8 stating the resolver's
  binding shape constraint (e.g., "the resolver is invoked only from
  within `*ServiceProvider::register()`; no other call site may select a
  concrete provider").
Blocks READY: NO — ordinary implementation latitude comparable to what
  PP-T1 was found to tolerate; recommended as a PROPOSED AMENDMENT (see
  §J) rather than a blocking gap.
```

```
ID: M-2
Severity: MEDIUM
Surface: tasks/PP-T2-provider-resolution-configuration.md §8, §11
Evidence: §8 states "`request_id` minted once at orchestration, carried
  through Invocation/outbound/logs" as though this needs to be
  established by T2. Independently verified: `request_id` (as
  `requestId`) is already minted via `Str::uuid()` inside
  `TranscriptionInvocation::create()` (app/Transcription/TranscriptionInvocation.php:51)
  and `TranslationInvocation::create()` (app/Translation/TranslationInvocation.php:63),
  predating both PP-T1 and PP-T2. This pre-existing identifier is distinct
  from the job-level `httpRequestId` also present in `ProcessTranscription`/
  `ProcessTranslation` (explicitly commented in source as "Distinct from
  ADR-017 `request_id` (the worker transport id)").
Why it matters: An implementer reading only the T2 contract, without
  independently discovering the existing `requestId` field, could
  reasonably mint a *second*, competing "request_id" at the resolver seam,
  producing two different UUIDs claiming the same name in different log
  lines — a correctness and debuggability regression T2 exists to prevent,
  not cause.
Required resolution: Clarify in §8/§11 that the resolver must *read and
  propagate* the invocation's existing `requestId` (and, separately, must
  not conflate it with the job-level `httpRequestId`), not mint a new one.
Blocks READY: NO — favorable to readiness on balance (lower actual
  implementation risk than the contract's wording implies), but the
  wording imprecision itself is worth correcting before implementation to
  avoid a foreseeable, easily-avoided defect.
```

```
ID: M-3
Severity: MEDIUM
Surface: tasks/PP-T2-provider-resolution-configuration.md §6, §15, §19
Evidence: §19 itself lists "config-key names reconciled with repo
  conventions" as an unmet READY-eligibility condition, and §15 flags
  "Flag-name drift" as a named risk with a stated mitigation of a
  "canonical key table in contract" that does not yet exist in the
  contract as written.
Why it matters: This is the contract's own admission of an incomplete
  precondition, not an externally-discovered gap. Leaving exact key/env
  names unresolved at READY time risks two things resolvable in the
  reviewed T1 pattern: (a) inconsistent naming vs. the established
  `RTFTT_TRANSCRIPTION_*` / `RTFTT_TRANSLATION_*` env prefixes already used
  throughout `config/transcription.php`/`config/translation.php` (both
  read in full — e.g. `RTFTT_TRANSCRIPTION_WORKER_URL`,
  `RTFTT_TRANSCRIPTION_JOB_TIMEOUT_SECONDS`), and (b) the pre-existing,
  differently-purposed `translation.provider` config key (currently an
  identity *label*, defaulting to `'self-hosted'`, used only for logging —
  confirmed via `AppServiceProvider.php` and the PP-T1 contract review's
  own finding) being silently repurposed or shadowed by T2's new
  *selection* key of a similar name, which would be a subtle and easy
  implementer mistake given the contract does not explicitly warn about
  this existing key's current, different meaning.
Required resolution: Before READY promotion, add a canonical key/env-name
  table to §6 (or a companion addendum), explicitly reconciling with the
  existing `translation.provider` identity-label key so an implementer
  does not conflate "provider identity label" with "provider selection
  key" under the same config path.
Blocks READY: NO for the contract's own internal logic (T2 was authored
  correctly flagging this as open), but this is the most concrete,
  externally-verifiable open item after the kill-switch mechanism (H-2
  below) and should be resolved before implementation begins, not left to
  implementer discretion.
```

```
ID: H-2
Severity: HIGH
Surface: tasks/PP-T2-provider-resolution-configuration.md §13 AC6, §19
Evidence: AC6 requires "Kill-switch control forces both domains to
  self-hosted within one flag refresh (assert resolution output)." §19
  lists "kill-switch mechanism approved by HPO" as an unmet
  READY-eligibility condition. Grepped `DECISION_QUEUE.md` and
  `DECISIONS.md` for any decision record containing "kill-switch," "kill
  switch," or "kill_switch": no matching decision entry exists (the term
  appears only inside the PP-T2/PP-T3/PP-T5/PP-T6 task-file prose itself,
  never in a DECIDED decision record).
Why it matters: AC6 cannot be objectively test-authored today, because
  "one flag refresh" presumes a specific mechanism (env var re-read on
  next request? `config:clear` + cache reload? a dedicated Artisan
  command? a database-backed feature flag?) that has not been chosen. Two
  implementers could each satisfy the AC's letter with materially
  different operational properties (e.g., one requiring a deploy-adjacent
  `config:clear`, defeating the "deployment-free" requirement stated in
  T2's own §2 objective: "a deployment-free kill switch"). This is a
  genuine, HPO-scoped decision the contract correctly does not presume to
  make on its own (per this repo's Decision Boundaries rule: agents must
  not invent product/architecture decisions), but it is not yet resolved.
Required resolution: HPO decision on the kill-switch mechanism (e.g.,
  cached-config-safe env flag with a documented refresh command, versus a
  database/cache-backed runtime flag) must be recorded in
  `DECISION_QUEUE.md`/`DECISIONS.md` before AC6 can be considered
  READY-testable. This is squarely an Owner Decision (see §J below).
Blocks READY: YES for full unconditional READY. See §J for the exact
  3-option framing and §L for how this affects the verdict.
```

```
ID: M-4
Severity: LOW
Surface: tasks/PP-T2-provider-resolution-configuration.md §6 vs tasks/PP-T5-provider-operational-controls.md §6/§17
Evidence: T2 §6 states "Kill switch: operator control disabling external
  usage without code deploy; target `config flip → self-hosted`." T5 §6
  separately claims "kill-switch control + procedure" and §17 states
  "Kill-switch IS the rollback." Neither task file cross-references the
  other on this shared concern.
Why it matters: Read closely the split is legitimate (T2 = mechanical flag
  + resolver honoring it; T5 = procedural/runbook/alerting layer around
  the same mechanism), and no contradiction was found — but the lack of
  an explicit cross-reference is a documentation gap that could cause a
  future implementer or reviewer to treat T2's kill-switch work as
  complete/redundant with T5's, or vice versa, without re-deriving the
  split independently as this review did.
Required resolution: Add a one-line cross-reference in both T2 §6 and T5
  §6 clarifying "T2 implements the mechanism; T5 owns the operational
  procedure/runbook/alerting around the same mechanism."
Blocks READY: NO — cosmetic/documentation-completeness only.
```

```
ID: I-1
Severity: INFO
Surface: discovery/processing-provider/ARCHITECTURE-PLAN-OPTION1.md §15 vs tasks/PP-T1..PP-T6
Evidence: The discovery plan's proposed task decomposition (§15: "T1
  Contract freeze... T2 Resolver+policy... T3 Self-hosted migration...
  T4 External boundary... T5 Config/observability... T6 Verification
  gate") differs from the canonical task numbering actually authored
  (PP-T1 = contract freeze *and* self-hosted migration merged; PP-T2 =
  resolver (matches plan's T2); PP-T3 = external transcription (matches
  plan's T4); PP-T4 = external translation, not separately named in the
  plan's decomposition; PP-T5 = config/observability (matches plan's T5);
  PP-T6 = verification (matches plan's T6)).
Why it matters: Purely informational — the discovery plan is explicitly
  "PLANNING ONLY" and the canonical `tasks/PP-T*.md` files supersede it by
  design (per source-of-truth ordering, task files rank above discovery/
  future-looking material). No contradiction in scope was found once the
  renumbering is accounted for; PP-T1's merge of the plan's T1+T3 is
  consistent with PP-T1's own final, HPO-closed scope.
Required resolution: None required. Noted for completeness only.
Blocks READY: NO
```

---

## J. Owner Decisions

Two genuinely unresolved questions require Human Product Owner decisions
before PP-T2 can be considered unconditionally READY. Per the Decision
Boundaries rule, this review does not resolve them — it presents options.

### Decision 1 — Kill-switch mechanism (blocks AC6 testability; Finding H-2)

**Question**: What concrete mechanism implements the "deployment-free kill
switch" that forces both domains to self-hosted?

1. **Env-var flag + documented cache-refresh procedure** (e.g.
   `RTFTT_PROCESSING_KILL_SWITCH=true` read at resolution time, combined
   with a required `php artisan config:clear` or equivalent as the "one
   flag refresh" step).
   - Behavior: simplest to implement; matches existing `env()`-based
     config patterns already used throughout `config/transcription.php`/
     `config/translation.php`.
   - Impact: requires an operator to run one Artisan command after
     changing the env value — arguably in tension with T2's own
     objective wording "deployment-free" if "deployment-free" is read to
     exclude any command execution, though it does not require a code
     deploy or release.
   - Compatibility: zero schema/infra change; fits current
     `env()`-everywhere convention.
   - Risk: cached-config bypass if the operator forgets the refresh step
     (T2's own §15 names exactly this risk).
   - Future implications: simplest to extend to T3+'s external providers
     later; no new infrastructure to maintain.

2. **Cache/database-backed runtime flag** (e.g., a `settings` table row or
   cache key toggled via an Artisan command or admin action, read fresh
   on every resolution with no manual refresh step required).
   - Behavior: genuinely zero-touch — flipping the flag takes effect on
     the very next request with no operator refresh step.
   - Impact: most faithfully satisfies "deployment-free" *and*
     refresh-free, but introduces a new persistence mechanism (cache key
     or table) that this repository does not currently have for
     operational flags, which is new infrastructure T2's own §7 arguably
     did not anticipate ("no new queues" is stated, but a new
     settings-cache mechanism is a comparable-weight addition).
   - Compatibility: requires either a new cache convention or (worse,
     and out of scope per §7) a new migration — the database option would
     need explicit HPO schema approval, which the contract does not
     currently request.
   - Risk: adds an operational moving part (cache/db must itself be
     reliably read); slightly larger surface than option 1.
   - Future implications: reusable for T5's broader operational-control
     needs (T5 already anticipates "operator command" style controls) —
     could be the more forward-compatible choice if T5 is expected to add
     more runtime-toggleable ceilings later.

3. **Do not decide yet; implement T2 with an explicit config-only flag and
   defer "zero-refresh" guarantees to T5.**
   - Behavior: T2 ships with option 1's mechanism but stops calling it a
     complete "kill switch" — T5 later decides whether to upgrade to
     option 2.
   - Impact: unblocks T2 implementation soonest, at the cost of AC6's
     "deployment-free" framing being provisionally weaker than the
     objective states.
   - Compatibility: no new infrastructure now; possible AC6 rewording
     needed to match ("within one `config:clear`" rather than "within one
     flag refresh").
   - Risk: kicks a real question down the road; T5 might re-litigate T2's
     already-implemented behavior.
   - Future implications: keeps T2 minimal and sequenced correctly (T5 is
     explicitly the "operational controls" task), but risks a rework if
     T5 later requires a different underlying flag storage than T2 chose.

**Recommendation**: Option 1, with AC6 reworded to state the exact refresh
step explicitly (e.g., "...within one `config:clear` (or equivalent
documented refresh command)..."), and with T5 explicitly noted as owning
any future upgrade to a zero-refresh mechanism. This keeps T2 minimal,
consistent with the existing `env()`-based configuration convention
already used everywhere else in `config/transcription.php` and
`config/translation.php`, and avoids introducing new infrastructure (a
settings cache/table) that neither T2 nor T5's contract currently requests
formal approval for.

### Decision 2 — Config-key naming reconciliation (Finding M-3)

**Question**: How should the new provider-*selection* config key relate to
the existing `translation.provider` identity-*label* key (currently
`env('RTFTT_TRANSLATION_PROVIDER', 'self-hosted')`, used only for logging
per the PP-T1 contract review's own finding)?

1. **Introduce distinctly-named new keys** (e.g.
   `transcription.provider_selection` / `translation.provider_selection`,
   or a nested `transcription.routing.provider`) that never collide with
   the existing `translation.provider` identity label, leaving the label
   key's current meaning fully untouched.
   - Behavior: zero risk of the two meanings colliding; existing
     identity-label behavior (and any code reading `config('translation.provider')`
     for logging today) is provably unaffected.
   - Impact: slightly more verbose config surface (two `provider`-ish
     keys per translation domain).
   - Compatibility: safest option against AC1's "Default config selects
     self_hosted... today's behavior" requirement.
   - Risk: naming can look redundant/confusing to a future maintainer
     without a code comment explaining the split.
   - Future implications: cleanly extensible; T3/T4 external names slot in
     without further renaming.

2. **Repurpose `translation.provider` itself as the selection key**, and
   have the resolver additionally derive/pass through the identity label
   from the same value (since in Wave 1 translation is locked to
   `self_hosted` only anyway, the two meanings never actually diverge
   under T2's own AC5 constraint).
   - Behavior: fewer config keys; works today because Wave 1 forces
     translation to a single value regardless.
   - Impact: **risk deferred, not eliminated** — if a future wave (T4,
     once un-deferred) needs `translation.provider` to hold an external
     name while a *different* identity label is still desired for
     persisted-identity purposes, this option silently breaks that
     future need, forcing a rename exactly when it's most disruptive
     (mid-way through a later wave, against live data).
   - Compatibility: works for Wave 1 only; the contract states "translation
     stays self-hosted-only in Wave 1," so the collision is currently
     invisible but not resolved.
   - Risk: papers over a real naming collision rather than resolving it.
   - Future implications: likely requires a corrective task when T4 is
     eventually un-deferred.

3. **Defer the decision entirely to the T2 implementer**, i.e., promote T2
   to READY without resolving this in the contract.
   - Behavior: fastest to promote.
   - Impact: leaves a foreseeable, contract-acknowledged (§15/§19) risk
     unresolved at exactly the point (contract authoring) where this
     repository's own governance model says naming/config decisions
     should be pinned, not left to implementer discretion.
   - Compatibility: risk of AC1 regression if the implementer's chosen key
     name accidentally shadows the existing identity-label key.
   - Risk: highest of the three.
   - Future implications: possible rework.

**Recommendation**: Option 1 — introduce distinctly-named selection keys
that do not collide with the existing identity-label key, with an
explicit one-line note in the reconciled T2 contract stating both keys'
distinct purposes side by side. This is the only option that fully
satisfies T2's own AC1 ("Default config selects self_hosted... assert in
test with stock env" — i.e., existing behavior is provably unaffected)
without deferring a foreseeable collision into a future wave.

---

## K. Test / Regression Readiness

(Consolidated; see also §H above for the detailed walk-through.)

- Full regression suite currently green per PP-T1's independently
  reproduced evidence (1108 total / 1103 passed / 5 pre-existing skips / 0
  failures; Pint clean; PHPStan 0 errors) — this is PP-T2's starting
  baseline, and nothing in this review found any reason that baseline has
  since regressed (no further application-code changes exist beyond
  PP-T1's own diff).
- T2's own test plan (§14) is proportionate, fixture-based, and requires
  no network egress — consistent with T1's precedent and directly
  extensible from patterns T1 already established
  (`app(Interface::class)` binding-resolution tests).
- No regression risk identified against Phase 3/5/6/7 from T2's *stated*
  scope; residual risk is entirely a function of implementer discipline
  staying within that scope, which is what independent review (post-T2-
  implementation) exists to catch.

---

## L. Readiness Verdict

```
PP-T2 = NOT_READY
```

Rationale: Two Owner Decisions are unresolved (kill-switch mechanism —
Finding H-2/AC6; config-key naming reconciliation — Finding M-3/Decision
2), and the contract's own §19 READY-eligibility conditions explicitly
list both as currently unmet ("kill-switch mechanism approved by HPO";
"config-key names reconciled with repo conventions"). Per this review's
own instruction — "Use READY only if there is no unresolved
BLOCKER/HIGH/MEDIUM finding and no unresolved HPO decision needed for
implementation" — Finding H-2 (HIGH) and the associated unresolved HPO
decisions are sufficient on their own to require `NOT_READY`.

This is **not** a verdict that the T2 contract is poorly authored: it is,
on the contrary, one of the more disciplined contracts in this task
family, because it correctly *identifies* both open items in its own §19
rather than silently assuming an answer. The path to READY is narrow and
concrete: two HPO decisions (§J) plus the associated wording
tightening (Findings M-1, M-2, M-4, each cheap, non-substantive edits
comparable to what PP-T1's contract review required and received before
its own READY promotion).

No BLOCKER finding exists. No architecture-compatibility contradiction
exists. No forbidden-scope leakage exists (there is no PP-T2 code in the
repository to leak). Dependency (PP-T1) is fully satisfied. The gating
issue is narrowly the two Owner Decisions plus their wording
consequences.

`PP-T2` is **not** READY-ELIGIBLE-AWAITING-HPO-PROMOTION in the
unconditional sense (that phrasing is reserved for a contract with zero
open findings requiring an HPO decision) — it is, more precisely,
**READY-ELIGIBLE CONDITIONAL ON TWO NAMED HPO DECISIONS**, which is a
narrower and more accurate status than either `READY` or a generic
`NOT_READY` alone would communicate. The verdict line above remains the
literal required output; this qualification is provided for precision.

---

## M. Governance Changes

Files created or modified by this review: **one** —
`reviews/PP-T2-READINESS-REVIEW.md` (this file).

No other file was created, edited, or deleted. Specifically:

- `tasks/PP-T2-provider-resolution-configuration.md` was **not** edited.
  The wording corrections implied by Findings M-1/M-2/M-3/M-4 are
  presented above as **PROPOSED AMENDMENTS** for the task owner (OpenCode,
  under HPO direction) to apply — this review does not apply them.
- `CURRENT_STATE.md`, `plan.md`, `AGENTS.md`, `DECISIONS.md`,
  `DECISION_QUEUE.md` were **not** edited by this review (Finding H-1
  documents their current state; it does not correct it).
- No production code (`app/`, `config/`, `database/`) was touched.
- No test file was touched.
- No `php artisan`, `composer`, or `npm` command that mutates state was
  run. Only read-only `git status`/`git diff`/`git log` and file reads
  were performed.

**No production implementation was performed.**

---

## N. Exact Next Legal Action

1. Per this repository's promotion-authority rule (see immediately below),
   this review does **not** edit `CURRENT_STATE.md` or
   `tasks/PP-T2-provider-resolution-configuration.md`'s `## 1. Status`
   field. PP-T2 remains `BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED` in
   both the task file and (by omission) `CURRENT_STATE.md`.
2. The Human Product Owner should resolve the two Owner Decisions in §J
   (kill-switch mechanism; config-key naming reconciliation) and record
   them in `DECISION_QUEUE.md`/`DECISIONS.md`.
3. Once decided, the task owner should apply the resulting wording
   corrections to `tasks/PP-T2-provider-resolution-configuration.md`
   (equivalent in weight to the two MEDIUM wording fixes PP-T1's contract
   review required before its own READY promotion — not a new authoring
   cycle).
4. Separately and independently of this review, the HPO/governance
   process should reconcile `CURRENT_STATE.md`, `plan.md`, and `AGENTS.md`
   to record the ProcessingProvider initiative and PP-T1's DONE status
   (Finding H-1), and commit the currently-uncommitted
   `DECISIONS.md`/`DECISION_QUEUE.md` changes, consistent with how every
   prior phase/task closure in this repository has been reconciled.
5. Only after 2–3 above, a fresh contract-completeness pass (not
   necessarily this full readiness review repeated) should confirm both
   findings are resolved, at which point PP-T2 would be eligible for the
   HPO's explicit `BACKLOG → READY` promotion decision — a decision this
   review does not make and is not authorized to make.

No implementation of PP-T2 (or any later task) is authorized by this
review. This review authorizes nothing; it reports.
