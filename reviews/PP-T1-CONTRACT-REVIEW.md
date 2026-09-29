# PP-T1 — Independent Contract Review

Reviewer: Claude Code (independent reviewer role). Contract review only — no
implementation, no production files edited, no promotion performed.

## A. Baseline

- Target: `tasks/PP-T1-provider-contracts-existing-adapters.md`.
- State at review start: `BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED`.
- T1 is the only READY_ELIGIBLE candidate; T2–T6 remain BACKLOG.
- No provider implementation authorized by this review. No vendor selected.
  No routing/config/resolver logic evaluated for authorization (T2/T3 scope).

## B. Contract inputs reviewed

- `tasks/PP-T1-provider-contracts-existing-adapters.md` (full).
- `discovery/processing-provider/ARCHITECTURE-PLAN-OPTION1.md`.
- `discovery/processing-provider/CURRENT-TRANSCRIPTION-BOUNDARY.md` (DISC-A01).
- `discovery/processing-provider/CURRENT-TRANSLATION-BOUNDARY.md` (DISC-A02).
- `discovery/processing-provider/PROVIDER-ABSTRACTION-OPTIONS.md` (DISC-A03/H01/H02).
- `discovery/processing-provider/PHASE17-COMPATIBILITY-REVIEW.md` (DISC-Q01).
- Current source at the cited seams: `app/Transcription/TranscriptionProvider.php`,
  `app/Transcription/HttpTranscriptionProvider.php`,
  `app/Translation/TranslationProvider.php`,
  `app/Translation/HttpTranslationProvider.php`,
  `app/Providers/TranscriptionServiceProvider.php`,
  `app/Providers/AppServiceProvider.php`, `config/transcription.php`,
  `config/translation.php`.
- Durable governance records: `DECISIONS.md`, `DECISION_QUEUE.md`,
  `CURRENT_STATE.md` (searched for any recorded HPO decision on the
  "ProcessingProvider" initiative or "Option 1" architecture direction).

## C. Abstraction / seam assessment

T1 correctly specifies two separate interfaces — `TranscriptionProvider` and
`TranslationProvider` — not a unified generic processing interface. This
matches current source: both already exist as distinct interfaces with
domain-specific Invocation/Result types (`TranscriptionInvocation`/
`NormalizedTranscript` vs `TranslationInvocation`/`TranslationResult`), so no
flattening of timestamps/segmentation/language/alignment/lifecycle/failure
semantics occurs or is proposed.

Seam verification (evidence, not assertion): both `TranscriptionServiceProvider`
and `AppServiceProvider` already bind `TranscriptionProvider::class` /
`TranslationProvider::class` to their `Http*Provider` concrete classes via
`$this->app->singleton(...)`, and a repo-wide grep found `new
Http{Transcription,Translation}Provider` used **only** inside those two
ServiceProviders — never on the orchestration hot path. This independently
confirms DISC-A01/A02's "narrowest boundary" finding: the seam T1 targets
(construction + binding) is real and already isolated. T1's actual delta is
narrow: rename/wrap the concrete classes and formalize the freeze + parity
tests, not introduce new indirection.

No jobs/domain models/persistence/lifecycle/normalized-result/revision/queue
redesign is proposed. Assessment: **PASS**.

## D. Transcription parity assessment

§5/§6/§9/§12 require preserving: faster-whisper `large-v3`, IDs-only
payload, Bearer auth, envelope `1.0`, `WorkerResponseValidator`/
`NormalizedTranscript` shape, 12-case failure taxonomy, worker `retryable`
advisory-only (confirmed authoritative-only handling in
`HttpTranscriptionProvider.php:45-52`, citing ADR-018 in-code), manual-only
retry (`tries=1`), stale recovery at timeout+60s, queue `transcription`,
timeouts 300/330/420. All of this matches current source and DISC-A01
FACT trace. No new field, no resegmentation, no confidence/word-timing
introduced. Assessment: **PASS**.

## E. Translation parity assessment

§5/§6/§9/§12 require preserving: NLLB self-hosted path, text-only
segment-aligned request, strict validator (count/index/timestamp
±0.0005s/source-echo), alignment sourced from invocation/DB never provider,
10-case failure taxonomy, worker flag advisory-only (confirmed in
`HttpTranslationProvider.php`), manual retry, stale recovery, queue
`translation`. Matches DISC-A02 FACT trace and current source. One
imprecision: T1 §5 paraphrases `config/translation.php` as "(NLLB
distilled-600M, `1.0`)", but the actual config default is
`'model' => env('RTFTT_TRANSLATION_MODEL', 'self-hosted-default')` — the
literal `distilled-600M` string does not appear in config; it is a
deployment-time env value, not a config-file default. This is a factual
imprecision in the contract's description of frozen inputs, not a scope or
behavior defect. Assessment: **PASS with a LOW correction**.

## F. Phase 3/5/6/7 compatibility

- Phase 3 (transcription normalization): preserved per §12; matches DISC-Q01
  "PASS w/ constraint."
- Phase 5 (translation lifecycle): preserved; disjoint lifecycles untouched.
- Phase 6 (revision/staleness/export): T1 makes no reference to revision
  identity, active-revision authority, staleness invalidation, or export
  logic anywhere in its scope — correctly terminates the abstraction before
  these invariants, per DISC-Q01's Phase 6 "PASS w/ constraint."
- Phase 7 (ops/queue/backup/retention): §7 explicitly excludes queue
  topology changes; §10/§17 confirm no schema/config change. No storage
  topology, retention, or deployment-topology alteration proposed.

Assessment: **PASS** — no Phase 3/5/6/7 invariant is put at risk by the
stated scope.

## G. Error / observability semantics

Failure mapping is explicitly frozen (§9): "Unknown codes map into frozen
taxonomies, never new states." This matches both `HttpTranscriptionProvider`
and `HttpTranslationProvider`'s current authoritative-taxonomy handling.
Observability (§11) preserves existing correlation fields and explicitly
defers provider-selection observability to T2/T5 — correct non-leakage.
Assessment: **PASS**.

## H. Testing / parity sufficiency

§14 requires contract/interface-conformance tests, parity fixtures
(recorded worker envelopes), validator-strictness tests, error-map
exhaustiveness, IDs-only payload assertions, full suite + Pint + PHPStan,
no live vendor calls. This covers the review brief's minimum list.

Two gaps:

1. **Parity definition is under-specified for a boundary-only change.**
   §12 says "byte/semantic equivalent where determinism allows," and AC5
   says "byte/semantic-equivalent... assert equality." Because T1 does not
   touch the worker or model code — only the PHP-side class name/binding —
   there is no legitimate source of non-determinism inside T1's own scope
   (the recorded fixture is replayed through unchanged mapping logic).
   Leaving "where determinism allows" unqualified lets an implementer treat
   an actual behavioral drift introduced during the rename as tolerable
   "semantic" variance. The contract should state that, for T1 specifically,
   parity is **exact/byte-equal** for all recorded-fixture replays, with
   "semantic equivalence" reserved for genuinely non-deterministic fields
   (none currently exist in `NormalizedTranscript`/`TranslationResult`).
2. **Existing test files that reference the old class names by name**
   (`tests/Feature/HttpTranscriptionProviderTest.php`,
   `tests/Feature/Translation/HttpTranslationProviderTest.php`) are not
   mentioned in §14. The contract should state whether these are renamed in
   place (pinning the new `SelfHosted*Provider` name) or left/duplicated,
   so the implementer isn't left to invent that.

Assessment: **PASS with two MEDIUM clarifications required**.

## I. Non-scope review

§7 excludes: external providers, vendor selection, chunking, fallback,
Auto, client-side, external translation traffic, schema changes (escalate
if required), queue topology changes. Cross-checked against source: the
pre-existing `config('translation.provider')` key is confirmed (by grep) to
be used **only** as an identity label passed into `HttpTranslationProvider`
for logging/persisted-identity purposes (`AppServiceProvider.php:47`,
`EnvironmentMetadata.php:58`) — it does not drive any class switching today,
and T1 does not propose to change that. No routing, resolver, or
provider-switching leakage found. AC "no routing branch introduced (grep:
no `external`/fallback selector in providers)" and "no new external network
call" are both objectively testable via grep/config inspection.

Assessment: **PASS** — no scope leakage identified.

## J. AC review

Six of eight ACs are precise and objectively testable as written. One AC
has a wording defect:

- **AC4** ("Orchestration resolves via interface; no direct `new
  Http*Provider` remains on the hot path") references a class name
  (`Http*Provider`) that the same task's own scope (§6) renames away. After
  the rename, this AC is checkable but nearly vacuous — the string
  `Http*Provider` may no longer exist anywhere, which trivially satisfies
  the AC without proving the actual property it's meant to guard (that
  orchestration never constructs a concrete provider directly, and only the
  ServiceProvider does). Recommend rewording to something binding-shape
  independent, e.g. "no code outside `app/Providers/*ServiceProvider.php`
  directly instantiates a concrete `TranscriptionProvider`/
  `TranslationProvider` implementation," which stays meaningful regardless
  of the class's final name.

All other ACs (interface freeze, `SelfHostedTranscriptionProvider`/
`SelfHostedTranslationProvider` conformance, parity suite, regression
suites green, no routing branch, no new external network call) are
concrete, testable, and do not depend on T2+ decisions.

## K. Findings

| # | Severity | Section | Finding |
|---|----------|---------|---------|
| 1 | HIGH | §4 Dependencies | T1 states "Requires: HPO architecture direction (Option 1)" as a satisfied precondition. No entry in `DECISIONS.md` or `DECISION_QUEUE.md` records an HPO-accepted ADR for the "ProcessingProvider" initiative or "Option 1" (separate `TranscriptionProvider`/`TranslationProvider`, self-hosted canonical, deterministic routing). The only source for this direction is `ARCHITECTURE-PLAN-OPTION1.md`, which is explicitly headed "PLANNING ONLY. No production code... authorized" and is a discovery artifact, not an accepted ADR. Per this repo's own source-of-truth ordering, a proposed planning document is not itself implementation authorization until reconciled into a repository-native artifact (ADR) and explicitly approved. This does not make T1's internal contract content wrong, but it means the dependency the contract cites as already-satisfied is not yet durably recorded. |
| 2 | MEDIUM | §12/§13 (AC5) | Parity is defined as "byte/semantic equivalent where determinism allows" without stating that, because T1 touches no worker/model code, byte-exact equality is the expected outcome for every recorded-fixture replay and "semantic" is not an escape hatch for actual behavior drift. Leaves the parity bar to implementer interpretation. |
| 3 | MEDIUM | §13 AC4 | AC wording references the pre-rename class name (`Http*Provider`), which becomes vacuous/self-satisfying once the rename in §6 happens. Should be reworded to a binding-shape-independent assertion (see §J above). |
| 4 | LOW | §14 Testing requirements | Existing test files named after the concrete classes (`tests/Feature/HttpTranscriptionProviderTest.php`, `tests/Feature/Translation/HttpTranslationProviderTest.php`) are not addressed — contract doesn't say whether they're renamed in place or left, leaving a small implementer decision. |
| 5 | LOW | §5 Inputs | `config/translation.php` is described as "(NLLB distilled-600M, `1.0`)" but the actual file has no such literal default (`model` defaults to `'self-hosted-default'`); minor inaccuracy in describing a frozen input, not a scope defect. |
| 6 | INFO | §6 Scope | `ARCHITECTURE-PLAN-OPTION1.md` §3 suggests a "deprecated alias one release" for the renamed classes; T1's scope doesn't include an alias. Repo-wide grep confirms no code outside the two ServiceProviders references the concrete class names directly (only two old-named test files do, see Finding 4), so an alias is unnecessary — this is not a gap, just a deliberate and justified simplification worth noting. |

None of findings 2–6 are BLOCKER/HIGH; they are precise, correctable
clarifications. Finding 1 is HIGH but concerns the authorization chain
around T1, not the internal precision, boundedness, or testability of the
T1 contract itself.

## L. Verdict

**`CONTRACT_VERIFIED — READY_ELIGIBLE`**, conditioned on Finding 1 being
resolved procedurally (see §N) and Findings 2–3 being corrected in the
task file before implementation begins (they do not require another full
review cycle — they are wording/definition tightening, not scope changes).

Rationale: the abstraction shape is deterministic and evidence-confirmed
against current source; self-hosted parity is objectively defined (modulo
the Finding 2 tightening); no T2+ dependency is required (T1 is
independently implementable — confirmed no vendor/config/routing decision
is needed); no BLOCKER finding exists; the contract can be implemented
without inventing policy, apart from the two MEDIUM wording gaps, which are
narrow enough not to require a second full review cycle.

## M. READY recommendation

PP-T1 may be presented to the HPO for `BACKLOG → READY` promotion once
Findings 2 and 3 are corrected in the task file (recommend folding the
correction in as a fast, non-substantive edit rather than a new authoring
cycle, since neither changes scope, ACs' testability, or non-scope
boundaries — only clarifies existing ones). No implementation is
authorized by this review.

## N. Exact next legal action

1. Correct Findings 2 and 3 in `tasks/PP-T1-provider-contracts-existing-adapters.md`
   (parity-definition wording; AC4 wording). Finding 5 may be corrected at
   the same time (trivial). Finding 4 may be resolved as an implementation
   note rather than a contract edit.
2. Because Finding 1 identifies that the architecture direction T1 depends
   on has never been recorded as an accepted ADR, the HPO's forthcoming
   `BACKLOG → READY` promotion decision for PP-T1 should explicitly ratify
   the Option 1 / separate-provider-interfaces direction as part of that
   promotion record (or a companion ADR entry in `DECISIONS.md`), so the
   dependency T1 cites in §4 is durably satisfied rather than resting on a
   document headed "PLANNING ONLY."
3. Obtain explicit HPO `BACKLOG → READY` promotion for PP-T1, then perform
   separate readiness confirmation before any execution authorization.
   T2–T6 remain BACKLOG; no provider implementation may begin.
