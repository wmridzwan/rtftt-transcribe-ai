# PP-T4 — Independent Review (cycle 1)

Date: 2026-09-28. Reviewer pass logically separate from the builder pass:
contract reread, full diff/source/test inspection, independent reruns.
Verdict basis is reproduced evidence, not the builder report.

## 1. Contract reread

Source of truth: `tasks/PP-T4-external-translation-provider.md` (reconciled;
AC1–AC12, §§6–14) under `DECISION-PP-T4-EXECUTION-AUTHORIZATION-001`, within
ADR-027, `DECISION-PP-T4-DEFERMENT-RELEASE-001`,
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`, PP-T1/PP-T2 DONE contracts,
PP-T3 pattern precedent, ADR-022 lifecycle/writer/staleness rules, and
ADR-018 retry rules.

## 2. Diff inspected

New production (5 files, `app/Translation/`):
`ReferenceExternalTranslationTransport` (seam interface, single method),
`ReferenceExternalTranslationRequest` (outbound DTO),
`ReferenceExternalTranslationResponse` (shaped success/failure),
`ReferenceExternalTranslationFailureKind` (7 vendor-neutral kinds),
`ReferenceExternalTranslationProvider` (adapter). Amended:
`TranslationProviderResolver` (scoped `external_reference` branch +
`fixtureReference()` + boot-validation allowlist + consts) and
`config/translation.php` (comment only, no keys). New test support:
`tests/Support/FakeReferenceExternalTranslationTransport.php`. New tests:
2 files, 40 tests. No frozen file modified (interfaces, DTOs, taxonomy,
validator, writer, lifecycle, jobs, transcription path all untouched —
verified by inspection; no `ReferenceExternalTranslation` reference exists
outside the 5 production files + resolver binding convention + tests; no
migration; no queue/lifecycle change).

## 3. Independently rerun evidence

- Focused PP-T4 suites: 40/40 pass, 151 assertions (reproduced).
- PP-T1/PP-T2 translation + resolver + PP-T3 suites: 81/81 pass (reproduced).
- Translation persistence/orchestration/retry/fence/job suites: 48/48 pass
  (reproduced).
- Staleness filter: 15/15 pass (reproduced). Revision filter: 143/143 pass
  on rerun (reproduced; first-run single failure in unrelated
  `RevisionHistoryActivationTest` confirmed order-dependent flake — passes
  in isolation 12/12, no PP-T4 code in its path). Export filter: 45/45 pass
  (reproduced).
- Full suite: 1213 tests, 1208 passed, 5 pre-existing skips, 0 failures
  (reproduced, clean first run).
- Pint: clean after 1 auto-fix (reproduced via `--test` semantics).
  PHPStan: 0 errors (reproduced).

## 4. AC matrix (each on executed evidence)

```text
AC1  PASS — implements TranslationProvider; resolves only via T2 resolver
            (fixture binding translation.providers.external_reference);
            repo-wide scan: no construction call site outside tests;
            arbitrary external_* still rejected; unbound/invalid fixture
            binding fails closed.
AC2  PASS — exact outbound fields asserted (auth/baseUrl/key/model/
            request/translation ids/target/segments/timeout); zero network
            (source scan + in-process fake; no Http/curl/socket in boundary
            sources); suite green with empty env secrets.
AC3  PASS — per-target (ms/ta), und-source echo, mixed source proven
            through the frozen validator; provider/model identity asserted.
AC4  PASS — count/index/timestamp/echo/target mismatches fail closed with
            MissingSegments/MalformedOutput; no partial persist; row
            untouched.
AC5  PASS (codes) — all 7 seam kinds mapped per §9 table with isRetryable()
            asserted; contradictory hint proven ignored; transport throw →
            ProviderFailed; empty-secret construction → ConfigurationError;
            unsupported source → UnsupportedSource; HTTP rows pinned via
            frozen failureFromCode (see PP-T4-REV-02 INFO).
AC6  PASS (partial — see MEDIUM below) — success/failure logs carry the
            checklist; token/full-text absent. Validator-rejection runs
            leave no adapter record (finding PP-T4-REV-01).
AC7  PASS — segment + char ceilings: boundary-equal passes, boundary-exceed
            → InvalidRequest pre-dispatch, zero dispatches.
AC8  PASS — suite green with empty worker-token env/config; boundary
            sources contain no env() reads.
AC9  PASS — kill-switch engaged → self-hosted, zero dispatches; invalid
            value preserves PP-T2 fail-closed.
AC10 PASS — invocation requestId reused; two explicit attempts → exactly
            two dispatches (no hidden retry); failure → one dispatch.
AC11 PASS — rerun byte-identical (serialize equality).
AC12 PASS — same-token persist on a stale-marked row preserves staleness
            (no currency restore); machine source untouched.
```

Additionally verified beyond ACs: writer-compatibility (adapter output
persists atomically → Completed + provider/model + 2 segments +
source-language echo from DB rows); timeout default/explicit/over-ceiling;
boot validation accepts the fixture value and still rejects vendors;
transcription resolver untouched.

## 5. Frozen-surface / protection checks

T1 translation interfaces/DTOs/taxonomy/validator/writer/lifecycle
unchanged; PP-T2 default/kill-switch/fail-closed/identity intact (existing
resolution suites green, 81/81 incl. PP-T2 rejection tests); transcription
path + PP-T3 suites green; no fallback/ranking/routing/queue/lifecycle/
schema/durable-table code; PP-T5/PP-T6 files untouched and unauthorized.
The only frozen-surface change is the contracted H-3 scoped resolver
amendment. PASS.

## 6. Findings

### PP-T4-REV-01 (MEDIUM) — Validator-rejection runs leave no adapter audit record

`ReferenceExternalTranslationProvider::translate()` logs ceiling breaches,
transport throws, shaped failures, and successes — but `validatedResult()`
throws (`MissingSegments` / `MalformedOutput` / `UnsupportedSource`, i.e.
the entire AC4 class) with no `logOutcome()` call, and the
`catch (TranslationException) { throw $e; }` passthrough is likewise
unlogged. Against reconciled §11 ("logs-only per-request records sufficient
to reconstruct any run — every record carrying outcome/failure category")
and the T3 precedent (every terminal chunk outcome logged), a run ending in
validator rejection is invisible at the adapter audit layer. The failure
code itself is correct in every case; this is observability completeness,
not mapping.

Disposition: CHANGES_REQUESTED — log the validator-rejection (and any
rethrown TranslationException) as a `failed` outcome carrying the mapped
`failure_category` before rethrowing; add a test asserting the failed-log on
at least one validator-rejection path. No contract change required (§11
already demands it).

### PP-T4-REV-02 (INFO) — HTTP-status table rows are seam-inapplicable by design

The §9 rows for 429/5xx/504/auth-status/422 have no distinct seam trigger
(the 7 vendor-neutral kinds are the only transport shapes, mirroring T3);
their mapped *codes* are all proven via kind mappings + the frozen
`failureFromCode` pin test. The firewall rule forbids leaking HTTP shapes
through the fixture boundary, so finer trigger granularity belongs to the
future vendor addendum. Harmless; no action.

### PP-T4-REV-03 (INFO) — `failureKind` nullable arm is defensive-only

`mapFailure()` handles `null` though the `failure()` factory always sets a
kind. Harmless defensive arm; no action.

## 7. Verdict

```text
PP-T4 = CHANGES_REQUESTED (cycle 1)
```

One MEDIUM (PP-T4-REV-01, §11 audit completeness) requires a corrective
cycle. No BLOCKER. No HIGH. AC codes all PASS; scope audit clean.
Builder (unchanged implementation owner) fixes PP-T4-REV-01 under this task
and resubmits for cycle-2 independent re-review; no VERIFIED/DONE transition
is authorized yet.
