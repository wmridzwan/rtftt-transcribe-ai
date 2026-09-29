# PP-T3 — Independent Review

Date: 2026-09-28. Reviewer pass logically separate from the builder pass:
contract reread, full diff/source/test inspection, independent reruns.
Verdict basis is reproduced evidence, not the builder report.

## 1. Contract reread

Source of truth: `tasks/PP-T3-external-transcription-adapter.md` (reconciled;
AC1–AC10, §§6–14) under `DECISION-PP-T3-EXECUTION-AUTHORIZATION-001`, within
ADR-027, PP-T1/PP-T2 DONE contracts, and ADR-018 retry rules.

## 2. Diff inspected

New production (5 files, `app/Transcription/`): `ReferenceExternalTransport`
(seam interface), `ReferenceExternalChunkRequest` (outbound DTO),
`ReferenceExternalChunkResponse` (shaped success/failure),
`ReferenceExternalChunkFailureKind` (7 vendor-neutral kinds),
`ReferenceExternalTranscriptionProvider` (adapter). New test support:
`tests/Support/FakeReferenceExternalTransport.php`. New tests: 2 files,
23 tests. No frozen file modified (interfaces, DTOs, taxonomy, resolver,
providers, jobs, config keys, translation path all untouched — verified by
status/scan audit; no `ReferenceExternal` reference exists outside the 5
production files; no migration; no queue/lifecycle change).

## 3. Independently rerun evidence

- Focused PP-T3 suites: 23/23 pass, 166 assertions (reproduced).
- PP-T1/PP-T2 suites: 52/52 pass (reproduced).
- Combined transcription-provider files: 55/55 pass (reproduced).
- Retry-family filter (`--filter="etry"`): 90/90 pass (reproduced) —
  covers the existing `failed → queued` new-attempt action behind AC10.
- Full suite: 1172 tests, 1167 passed, 5 pre-existing skips, 0 failures
  (reproduced; 4 warnings confined to pre-existing non-PP-T3 files:
  P6009FinalGateTest, IngestionCompensationContractTest,
  MediaUploadContractTest, TranscriptRevisionAwareExportTest).
- Pint `--test`: clean (reproduced). PHPStan: 0 errors (reproduced).

## 4. AC matrix (each on executed evidence)

```text
AC1  PASS — implements TranscriptionProvider; resolves only via T2 resolver;
            repo-wide scan: no construction call site outside tests.
AC2  PASS — exact outbound fields asserted (auth/baseUrl/key/model/offset/
            request/attempt/timeout/language); zero network (source scan +
            in-process fake; no Http/curl/socket/api in boundary sources).
AC3  PASS — offsets absolutized, order deterministic, distinct occurrences
            kept, Tamil + und-fallback proven, exact text asserted.
AC4  PASS — drop-injected chunk → InvalidWorkerResponse, non-retryable, zero
            new segments, transcription row untouched.
AC5  PASS — all 7 kinds mapped per §9 table with isRetryable() asserted;
            contradictory advisory hint proven ignored.
AC6  PASS — request/chunk/provider/model/attempt+seq/outcome/timing present;
            token/Bearer/text/storage-key absent from log payloads.
AC7  PASS — limit+1 → MediaRejected pre-dispatch, zero dispatches; exact
            limit proceeds.
AC8  PASS — suite green with empty worker-token env/config; adapter source
            contains no env() reads.
AC9  PASS — kill-switch engaged → self-hosted resolved, zero dispatches.
AC10 PASS — same transcription across attempts 7/8: distinct chunk ids,
            seq stays 0, invocation requestId reused on every request;
            operator new-attempt semantics owned by the existing retry
            action (90/90 green).
```

Additionally verified beyond ACs: writer-compatibility (adapter output
persists atomically through `TranscriptionResultWriter` → Completed +
2 segments + full text); malformed shapes (6 cases) fail closed with exactly
one dispatch (no retry/fallback continuation); speech-without-segments fails
closed; no-speech form preserved; 300 s ceiling honored, 600 s refused at
construction, 60 s carried through; empty-token construction refused.

## 5. Frozen-surface / protection checks

T1 interfaces/DTOs/taxonomy unchanged; PP-T2 resolver/fail-closed/kill-switch/
identity intact (52/52); translation resolver returns self-hosted, untouched;
no fallback/ranking/routing/queue/lifecycle/schema/durable-chunk code;
PP-T4–PP-T6 files untouched and unauthorized. PP-T2 fail-closed not loosened
for fixtures (ctor-injected credentials only).

## 6. Findings

No BLOCKER. No HIGH. No MEDIUM.

- PP-T3-REV-01 (LOW): §8 "ctor takes scalars only" lists config scalars but
  not the `ReferenceExternalTransport` dispatch seam (the sole non-scalar
  ctor dependency, required by §§6/14 fake-transport). Implementation is
  faithful to the contract as a whole; wording gap only. Accepted
  non-blocking; no contract churn.
- PP-T3-REV-02 (INFO): `durationMs` on the success shape is currently
  informational/reserved (duration derives from invocation media + segment
  span). Harmless.
- PP-T3-REV-03 (INFO): defensive `catch (TranscriptionException) { throw $e; }`
  passthrough is intentional (preserve mapped codes). Harmless.
- PP-T3-REV-04 (INFO): overlap trim + dedup operate on the globally sorted
  list (behaviorally the contracted rule; deterministic total order proven by
  ordering assertions). No action.

## 7. Verdict

```text
PP-T3 = VERIFIED
```

AC1–AC10 PASS on independently reproduced evidence; scope audit clean; no
BLOCKER/HIGH/MEDIUM. Non-blocking notes PP-T3-REV-01 (LOW), REV-02..04
(INFO) preserved above; no follow-up task warranted (all are documentation/
observation class, resolved inline).
