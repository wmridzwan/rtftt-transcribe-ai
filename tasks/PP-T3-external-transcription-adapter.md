# PP-T3 — External Transcription Adapter Foundation (vendor-neutral, credential-less)

## 1. Status

`DONE — CLOSED BY HPO (DECISION-PP-T3-CLOSURE-001)`

Step-2 execution record (2026-09-28): lifecycle
`READY → IN_PROGRESS → REVIEW` under
`DECISION-PP-T3-EXECUTION-AUTHORIZATION-001`, strictly within this contract.
Implementation: 5 new production files (`ReferenceExternal*` in
`app/Transcription/`), 1 test-support fake, 2 focused test files (23 tests).
Builder verification: focused 23/23 pass (166 assertions);
PP-T1/PP-T2 suites 52/52; combined transcription-provider 55/55; retry-family
90/90; full suite 1173 (1168 passed, 5 pre-existing skips, 0 failures on fresh
rerun — one unrelated second-boundary flake in
`RevisionHistoryActivationTest` on first run, passes in isolation and rerun);
Pint clean; PHPStan 0 errors (3 findings fixed at cause). AC1–AC10 all PASS on
executed evidence. Scope audit clean (no migration/queue/lifecycle/
translation/fallback/ranking/routing/durable-chunk change; frozen interfaces
untouched; PP-T4–PP-T6 untouched). Builder claims neither VERIFIED nor DONE.

Independent review (2026-09-28, `reviews/PP-T3-INDEPENDENT-REVIEW.md`):
`PP-T3 = VERIFIED` on independently reproduced evidence (AC1–AC10 PASS;
scope audit clean; no BLOCKER/HIGH/MEDIUM). Findings: PP-T3-REV-01
(LOW, §8 ctor-wording gap for the transport seam — accepted non-blocking),
PP-T3-REV-02..04 (INFO, reserved durationMs / defensive passthrough /
sorted-list trim — harmless, no action). Lifecycle `REVIEW → VERIFIED`
evidenced by the review artifact. (Review §3 cites the 1172-test full-suite
run; final rerun 1173/1168/5/0 includes the writer-compatibility test added
during review scrutiny; flake investigation recorded in the closure decision.)

Closure (2026-09-28, HPO `DECISION-PP-T3-CLOSURE-001`): HPO accepts PP-T3;
canonical transition `VERIFIED → DONE`. Full lifecycle history:
`READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`. PP-T4 stays DEFERRED;
PP-T5/PP-T6 stay BACKLOG / NOT AUTHORIZED; closure authorizes no later work.

Step-1 history (2026-09-28; governance/readiness only, no implementation):

- Readiness review `reviews/PP-T3-READINESS-REVIEW.md`: `NOT_READY`
  (0 BLOCKER, 3 HIGH, 10 MEDIUM, 3 LOW, 1 INFO — all contract-precision gaps).
- Reconciliation `DECISION-PP-T3-CONTRACT-RECONCILIATION-001`: all HIGH/MEDIUM
  resolved to non-controversial canonical options below; LOWs resolved inline;
  §§2/6/8/9/10/11/13/14/15/19 amended. Review artifact itself unchanged.
- Fresh confirmation `reviews/PP-T3-READINESS-CONFIRMATION.md`:
  `READY-ELIGIBLE` (no unresolved BLOCKER/HIGH/MEDIUM).
- Promotion `DECISION-PP-T3-READY-PROMOTION-001`: `BACKLOG → READY`.
- Execution authorization `DECISION-PP-T3-EXECUTION-AUTHORIZATION-001`:
  PP-T3 may move `READY → IN_PROGRESS` when work begins, strictly within this
  contract. PP-T4–PP-T6 remain unauthorized.

## 2. Objective

Define and build a vendor-neutral external transcription adapter boundary
(request/response/identity/chunking/errors) as exactly ONE fixture-shaped
reference adapter implementing the frozen T1 `TranscriptionProvider` —
buildable and testable with fixtures and mocked responses, with no live
credentials and no vendor traffic.

Reconciled 2026-09-28 (H-1): "one class per future vendor" in the prior draft
is narrowed — with vendor selection deferred (ADR-027), PP-T3 builds a single
reference adapter proving the boundary. Per-vendor classes require a separate
provider-specific addendum + HPO authorization (§18).

"Deployment-free" carries no T3 meaning (no flag owned here); the kill-switch
mechanism stays PP-T2-implemented, its procedure PP-T5-owned.

## 3. Why

Option 1 needs overflow capability without selecting a vendor or granting live-call authority. T3 proves normalization/chunking/identity while T5 governs enablement.

## 4. Dependencies

Implementation requires PP-T1 DONE + PP-T2 DONE (both satisfied:
`DECISION-PP-T1-CLOSURE-001`, `DECISION-PP-T2-CLOSURE-001`). Planning inputs:
`ARCHITECTURE-PLAN-OPTION1.md` §§4,6–10; `EXTERNAL-LANDSCAPE.md`;
`PROVIDER-ABSTRACTION-OPTIONS.md` (H01/H02).

## 5. Inputs / frozen contracts

T1 `TranscriptionProvider` iface + `NormalizedTranscript` (+ `noSpeech` form) +
`TranscriptSegmentData` (start≥0, end≥start) + `LanguageIdentifier`
(ms/en/zh/ta/und, `fromBcp47`) + 12-case `TranscriptionFailure` +
`TranscriptionException` (carries `TranscriptionFailure`) + `TranscriptionInvocation`
(UUID `requestId` minted once in `::create()`) + atomic `TranscriptionResultWriter`;
T2 resolver + binding convention (`transcription.providers.*`) + identity fields +
kill-switch + fail-closed behavior; 500 MiB product limit; queue/timeout invariant
(`timeout_seconds` 300 < job 330 < retry_after 420); manual-only retry (`tries=1`);
no-resegmentation + `und`-fallback + ms-precision rules.

## 6. Scope

- Request preparation through ONE reference adapter behind the T1 interface,
  with private shape-builder/validator/error-map helpers (public ctor takes
  scalars only — see §8; reconciled H-2).
- Normalized response mapping (timestamps/language/segments/no-speech);
  provider/model identity capture (reusing the invocation `requestId`, §8).
- Canonical error mapping per the §9 table (taxonomy authoritative, vendor flag
  advisory).
- Timeout behavior: honor `config('transcription.timeout_seconds')` (300) as the
  provider ceiling via the timeout ctor arg default; no new timeout keys (M-5).
- Idempotency/retry implications per §9 (chunk identity + adapter-internal
  `attemptSeq` only; parent retry unchanged).
- Adapter-internal request-size ceiling via ctor scalar, default bounded
  at/below the 500 MiB product limit (M-6); product limit unchanged.
- Derived-audio handling: derived chunks are server-temp, ephemeral, evicted on
  terminal state (retention `ephemeral` reaffirmed; no durable chunk store).
- Chunking + recomposition, adapter-internal only: fan-out → absolutize →
  single overlap rule (M-1) → deterministic exact-then-near-duplicate dedup →
  chunk-manifest coverage check (M-2) → one `NormalizedTranscript` → single
  atomic persist path.
- Logs-only per-chunk audit per the §11 checklist (M-7).
- Privacy/egress disclosure for the reference adapter shape (what WOULD leave:
  derived audio/chunks/metadata; never original-media ownership change).

## 7. Non-scope

Vendor selection; live credentials/traffic/customer media egress; automatic
routing/failover/retry schedulers; external translation (PP-T4, deferred);
client-side; Local/Auto UI; kill-switch procedure/runbook/alerts and
spend/billing controls (PP-T5 — T3 emits fields/logs only, M-10); integration
gate execution/verdict (PP-T6 FINAL_GATE_ONLY); durable chunk table (no
migration in PP-T3; any claimed need → STOP + HPO escalation, L-2); translation
path changes; queue/lifecycle redesign; schema changes; new global config keys
(H-2 — deferred to the future vendor-binding addendum).

## 8. Architecture / behavioral contract

- The reference adapter implements the frozen T1 `TranscriptionProvider`;
  vendor structs die inside the adapter (firewall rule). No vendor
  JSON/headers/confidence/words/offsets leak past it.
- Construction (reconciled H-2): public ctor takes scalars only —
  `baseUrl`, `token`, `timeoutSeconds` (default: `config('transcription.timeout_seconds')`),
  `providerKey`, `modelPinned`, `maxRequestBytes` (default at/below 500 MiB).
  No new global config keys; tests inject explicit fixture values (H-3).
  Production construction with an empty token fails closed with
  `ConfigurationError` before any dispatch; tests never set real env secrets.
- Chunking adapter-internal only: fan-out → absolutize
  (`absolute_ms = chunk_offset_ms + relative_ms`, re-validate finite/start≥0/
  end≥start/ms precision) → single overlap rule (reconciled M-1: for any two
  recomposed segments whose absolute windows overlap, keep the earlier chunk's
  segment and drop the overlapping portion of the later segment) →
  deterministic exact-then-near-duplicate dedup (byte-identical first, then
  same-text + window within 1 ms) → chunk-manifest coverage check (reconciled
  M-2: every dispatched chunk resolves to exactly one outcome — mapped success
  or mapped failure; any unaccounted chunk fails closed, no partial persist) →
  one `NormalizedTranscript` → single atomic persist path (existing writer).
- Chunk identity is execution-only (`chk_{short}_{idx:04d}_{seq:02d}` logs/traces,
  I-1 binding name is a test-only detail); it must not replace
  media/transcription/revision/segment identity (ADR-027). Idempotency key:
  `(transcription_id, processingAttemptId, chunk_index, attempt_seq)` (M-9);
  recompose-then-persist once.
- Request identity: reuse the invocation's existing `requestId` (minted once by
  `TranscriptionInvocation::create()`); the adapter mints no second logical
  request identity. `attemptSeq` bumps ONLY for adapter-internal chunk
  recomposition retries within the same parent attempt (reconciled M-4).
- Language transition = segment boundary else `und` (`LanguageIdentifier::fromBcp47`,
  allowlist miss → `Undetermined`); confidence/words dropped; no-speech form
  (`NormalizedTranscript::noSpeech`) preserved end-to-end.
- Binding/selection: the adapter is reachable ONLY via the T2 resolver under
  the `transcription.providers.*` convention (test/fixture binding); no other
  call site may select it; kill-switch engaged → self-hosted regardless (M-8,
  reuses the PP-T2 mechanism, no new logic).

## 9. Failure semantics

Canonical error-map table (reconciled M-3; frozen taxonomy authoritative,
vendor flag advisory only):

| Condition | Mapped code | Retryable |
|---|---|---|
| Transport timeout | `WorkerTimeout` | yes |
| HTTP 429 | `WorkerSaturated` | yes |
| HTTP 5xx / unreachable | `WorkerUnavailable` | yes |
| Partial / invalid / unknown vendor payload | `InvalidWorkerResponse` | no |
| Oversized-for-provider (pre-dispatch ceiling breach) | `MediaRejected` | no |
| Empty-secret production construction | `ConfigurationError` | no (fail closed before dispatch) |

No new terminal state is invented; unknown maps to `InvalidWorkerResponse`,
never to an ad-hoc code. Chunk failure after policy → parent attempt fails with
the mapped code; no partial persist. Operator manual retry always creates a NEW
parent attempt via the existing retry action (ADR-018 B3-01/B3-03 unchanged);
prior attempts stay immutable. Stale rules unchanged. No fallback to another
provider inside the adapter (no-fallback arm exists nowhere in the table).

## 10. Security/privacy constraints

No live egress in build/tests (fake transport, zero network calls); per-adapter
egress disclosure (what would leave: derived audio/chunks/metadata; never
original-media ownership change); secrets env-only and never logged/committed/
asserted-present (assert absence); production empty-secret → fail closed before
dispatch (H-3); ownership/authorize fences unchanged; 500 MiB product limit
unchanged (provider caps are adapter ceilings, not product changes); translation
stays text-only and untouched.

## 11. Observability/audit requirements

Logs-only per-chunk records (ADR-027) sufficient to reconstruct any run —
checklist (reconciled M-7), every record carrying: invocation `requestId`
(reused), chunk id (`chk_*`), `provider_key`, `model_pinned`, parent attempt id
+ `attemptSeq`, outcome, timing ms, failure category. Parent row identity
(`provider_key`/`model_pinned`/`request_id`) stamped via the existing PP-T2
job-layer lines. Secrets, media bytes, and full transcript text absent from all
records (asserted). If review proves logs insufficient → STOP, escalate, no
silent schema (L-2).

## 12. Compatibility requirements

Downstream domains never branch on vendor; Phase 2/3/6/7 invariants hold
(media identity/checksum/storage/500 MiB; normalization/lifecycle/self-hosted
parity; revision/staleness/export incl. the F-001 active-revision guard;
queue/timeout invariants; retention/storage/backup); translation untouched
(assert existing translation suites green, L-3); machine source immutable;
revision/staleness/export unchanged. PP-T1 parity and PP-T2 resolution suites
stay green.

## 13. Acceptance criteria

- [ ] AC1 — Adapter implements `TranscriptionProvider` and is selected only via
  the T2 resolver (assert wiring; assert no other selection call site).
- [ ] AC2 — Fixture request mapping: synthetic provider payloads assert exact
  outbound fields (auth/pinned model/absolute-ms) with zero network calls
  (fixture credentials via ctor, env secrets empty — H-3).
- [ ] AC3 — Normalization: chunked fixtures (offsets/overlaps/duplicates/language
  transitions) recompose to the expected canonical transcript (assert
  text/segments/timestamps/language/order).
- [ ] AC4 — No word loss: drop-injected fixture fails closed with the mapped
  error (assert `InvalidWorkerResponse`, no partial persist — M-2).
- [ ] AC5 — Error mapping table (§9) covers timeout/429/5xx/partial/invalid/
  unknown/oversized/empty-secret, each asserting the mapped 12-case code +
  `isRetryable()` value.
- [ ] AC6 — Identity: logs assert `request_id`/chunk id/provider/model/attempt+
  `attemptSeq`/outcome/timing present; secrets/media-bytes/full-text absent.
- [ ] AC7 — Size ceiling: oversized request rejected before dispatch with
  `MediaRejected` (assert code, zero partial dispatch — M-6).
- [ ] AC8 — No live credentials required to build/test (assert suite passes with
  empty provider env secrets — H-3).
- [ ] AC9 — Kill-switch engaged → resolution returns self-hosted even when
  selection names the PP-T3 binding (assert zero adapter invocations — M-8).
- [ ] AC10 — Operator retry creates a new parent attempt (existing retry action);
  chunk `attemptSeq` never crosses parent attempts (assert — M-4).

## 14. Testing requirements

Fixture + mocked-response suites (no HTTP egress — fake transport);
recomposition property tests (offset/overlap/dedup per M-1); error-map matrix
(AC5); identity/log assertions incl. secret-absence scans (AC6); CAS/idempotency
tests for chunk retry under key `(transcription_id, processingAttemptId,
chunk_index, attempt_seq)` (M-9); kill-switch test (AC9); parent-retry test
(AC10); minimal fixture corpus (reconciled L-1): offsets, overlaps, exact +
near duplicates, language transitions incl `und`-fallback, no-speech, empty,
invalid payloads, timeout/429/5xx/partial/unknown errors, oversized; full suite
+ Pint + PHPStan.

## 15. Risks

Timestamp drift → absolutize + threshold alert. Resegmentation → validator reject. Taxonomy gaps → map-unknown + test (M-3 table). Duplication → identity + atomic persist (M-9). Cost/rate unknowns → ceilings + kill-switch procedure (PP-T5 owns procedure; T3 enforces adapter ceilings only — M-10). Overlap ambiguity → single canonical rule + tests (M-1). Credential-less/auth tension → ctor-injected fixtures, env empty (H-3).

## 16. Regression concerns

T1/T2 suites stay green (parity + resolution incl. fail-closed/kill-switch);
Phase 3/6 suites (normalization, lifecycle, revision/staleness/export F-001
guard); translation suites unchanged (no translation behavior change — L-3);
no queue/lifecycle change. Any regression → STOP, fix within PP-T3, no contract
rewrite.

## 17. Rollback expectations

Resolver flag back to self-hosted; no data migration to undo (fixtures only;
no migration shipped — L-2). Document adapter disablement (remove/rename the
fixture binding; default selection already self-hosted).

## 18. Governance / authorization boundary

Contract authoring complete; execution authorized
(`DECISION-PP-T3-EXECUTION-AUTHORIZATION-001`, 2026-09-28) strictly within this
reconciled contract. Still forbidden without fresh authorization: vendor calls,
customer media egress, spike execution, per-vendor classes (require a
provider-specific addendum + HPO auth), schema/migration, queue/lifecycle
redesign, translation changes, PP-T4–PP-T6 work. Vendor binding needs separate
provider-specific addendum + HPO auth.

## 19. READY eligibility conditions

Satisfied 2026-09-28: PP-T1 DONE + PP-T2 DONE (no combined-wave exception
needed); contract review clean (fresh confirmation `READY-ELIGIBLE`, no
BLOCKER/HIGH/MEDIUM); single-reference-adapter scope reconciled (H-1);
configuration via scalar ctor, no new global keys (H-2); credential-less test
rule reconciled without weakening PP-T2 (H-3); overlap/dedup/coverage rules
exact (M-1/M-2); error table canonical (M-3); retry semantics reconciled with
ADR-018 (M-4); timeout/size-ceiling sources pinned (M-5/M-6); log checklist
embedded (M-7); kill-switch AC added (M-8); idempotency key stated (M-9);
T3/T5 boundary split (M-10); fixture corpus enumerated (L-1); no-migration
explicit (L-2). Promotion recorded (`DECISION-PP-T3-READY-PROMOTION-001`).

## 20. Exact next legal action

Begin PP-T3 — Step 2: Build, Verify & Close (implementation strictly within
this contract; builder moves `READY → IN_PROGRESS → REVIEW`; no VERIFIED/DONE
claim by the builder; PP-T4–PP-T6 remain unauthorized).
