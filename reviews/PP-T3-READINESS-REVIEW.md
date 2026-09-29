# PP-T3 — Readiness Review (Step 1)

Date: 2026-09-28. Scope: governance/readiness only; no implementation.

## A. Baseline reconstructed

Source of truth: working-tree `AGENTS.md` (ProcessingProvider track paragraph),
`tasks/PP-T*.md`, `DECISION_QUEUE.md`, `DECISIONS.md` (ADR-027), `CURRENT_STATE.md`
(ProcessingProvider track §), `discovery/processing-provider/ARCHITECTURE-PLAN-OPTION1.md`,
live source (`app/Transcription/*`, `config/transcription.php`, `config/processing.php`).

| Task | State before this step |
|---|---|
| PP-T1 | DONE (`DECISION-PP-T1-CLOSURE-001`; independently VERIFIED, no BLOCKER/HIGH) |
| PP-T2 | DONE (`DECISION-PP-T2-CLOSURE-001`; corrective cycle 1 independently VERIFIED, no BLOCKER/HIGH) |
| PP-T3 | BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED (candidate; no implementation) |
| PP-T4 | BACKLOG — DEFERRED FROM WAVE 1 / NOT AUTHORIZED |
| PP-T5 | BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED |
| PP-T6 | BACKLOG — CONTRACT_AUTHORED / NOT AUTHORIZED (FINAL_GATE_ONLY) |

Matches the prompt's expected confirmation; no discrepancy found.

Authoritative constraints applied: ADR-027 (Option 1, server-first + pinned
direct overflow; separate provider contracts; self-hosted canonical; no silent
fallback; no Wave-1 Auto/client; external translation deferred; chunk identity
execution-only; per-chunk audit logs-only initially; vendor selection deferred;
Phase 1–7 frozen); `DECISION-PROCESSING-PROVIDER-OPTION1-001`;
`DECISION-PP-T2-KILL-SWITCH-001`; `DECISION-PP-T2-CONFIG-NAMING-001`;
ADR-018 (manual-only retry, same-transcription retry = new `ProcessingJob`,
completed protected, stale rules); PP-T1 §12 parity rule; PP-T2 §§6/8/11
(resolver seam, request identity, logging).

No premature PP-T3 implementation exists: no `External*TranscriptionProvider`
production class; only PP-T2 test fakes (`Pp2FakeExternalTranscriptionProvider`,
test-only). Frozen interfaces verified live: `TranscriptionProvider::transcribe()`,
`TranscriptionInvocation` (UUID `requestId` minted once in `::create()`),
`NormalizedTranscript` (+ `noSpeech` form), `TranscriptSegmentData`
(start≥0, end≥start), `LanguageIdentifier` (ms/en/zh/ta/und + `fromBcp47`),
12-case `TranscriptionFailure` + `isRetryable()`, `TranscriptionException`
(carries `TranscriptionFailure`), `TranscriptionProviderResolver`
(per-resolution evaluation, fail-closed, kill-switch, `transcription.providers.*`
binding convention).

## B. Findings

Severity scale: BLOCKER / HIGH / MEDIUM / LOW / INFO. All BLOCKER/HIGH/MEDIUM
affecting readiness must be resolved before promotion.

### H-1 — Adapter plurality contradicts deferred vendor selection (HIGH)

Contract §6 says "one class per future vendor", but ADR-027 defers vendor
selection until after the foundation exists, and §7 lists "Vendor selection" as
non-scope. A builder cannot implement per-vendor classes with no vendor chosen.
Disposition: RESOLVE by reconciliation — PP-T3 builds exactly ONE
fixture-shaped, vendor-neutral reference adapter proving the boundary
(request/response/identity/chunking/errors); per-vendor classes require a
separate provider-specific addendum + HPO authorization (§18 already anticipates
this). Recorded in `DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### H-2 — External adapter configuration contract undefined (HIGH)

Contract requires asserting exact outbound fields (auth/pinned model) and size
ceilings, but no canonical config/ctor contract exists: ARCHITECTURE-PLAN-OPTION1
§5 keys were never decided for T3, and PP-T2 decided only selection keys +
kill-switch. A builder would have to invent config keys.
Disposition: RESOLVE — no new global config keys in the Wave-1 build; the
adapter takes scalar ctor args (baseUrl, token, timeout, pinned identity, size
ceiling); tests inject explicit fixture values; env secrets stay empty. Rationale:
PP-T2 fail-closed + secrets-env-only semantics preserved; config-key decisions
deferred to the future vendor-binding addendum. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### H-3 — Credential-less suite vs auth-field assertions unreconciled (HIGH)

AC2 requires asserting exact outbound auth fields while AC8 requires the suite to
pass with empty provider secrets; PP-T2 fail-closed rejects external selection
without credentials. Unexplained, a builder could weaken PP-T2 validation to
make fixtures pass.
Disposition: RESOLVE — tests inject explicit fixture credentials via ctor (never
via env); env secrets remain empty; PP-T2 boot/selection validation untouched;
fixture adapters are bound under `transcription.providers.<fixture_name>` and
resolved only through the T2 resolver. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-1 — Overlap policy "drop tail-of-k" undefined (MEDIUM)

§8 names a "single overlap policy (default drop tail-of-k)" with no definition
of k, overlap measurement, or trimming vs dropping.
Disposition: RESOLVE — canonical rule: absolutize every chunk-relative timestamp
(`absolute_ms = chunk_offset_ms + relative_ms`, re-validate finite/start≥0/
end≥start/ms precision); for any two recomposed segments whose absolute windows
overlap, keep the earlier chunk's segment and drop the overlapping portion of
the later segment; then deterministic exact-then-near-duplicate dedup
(byte-identical first, then same-text + window within 1 ms). Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-2 — "Silent-partial prohibited (count check)" undefined (MEDIUM)

§8 cites a count check without stating what is counted against what.
Disposition: RESOLVE — chunk-manifest coverage rule: every dispatched chunk must
resolve to exactly one outcome (mapped success or mapped failure); any
unaccounted chunk (e.g. drop-injected fixture) fails closed with a mapped error
and no partial persist. The no-speech form (`NormalizedTranscript::noSpeech`)
is preserved end-to-end. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-3 — Unknown-error default ambiguous (MEDIUM)

§9 allows "safe non-retryable-or-retryable", letting the builder choose the
default mapping for unknown conditions.
Disposition: RESOLVE — canonical error-map table: transport timeout →
`WorkerTimeout` (retryable); HTTP 429 → `WorkerSaturated` (retryable); HTTP
5xx / unreachable → `WorkerUnavailable` (retryable); partial/invalid/unknown →
`InvalidWorkerResponse` (non-retryable); oversized-for-provider →
`MediaRejected` (non-retryable); empty-secret production construction →
`ConfigurationError` (non-retryable, fail closed before dispatch). The frozen
12-case taxonomy stays authoritative; vendor flags advisory only (ADR-018
precedent). Each mapping asserted. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-4 — attemptSeq wording contradicts ADR-018 manual retry (MEDIUM)

§9 says "operator manual retry resubmits same attempt (bump `attemptSeq`)",
but ADR-018 B3-01/B3-03 require operator manual retry to create a NEW
`ProcessingJob` with prior attempts immutable.
Disposition: RESOLVE — parent operator retry always creates a new attempt via
the existing retry action (unchanged); `attemptSeq` bumps ONLY for
adapter-internal chunk recomposition retries within the same parent attempt,
under chunk identity `(transcription_id, processingAttemptId, chunk_index,
attempt_seq)`; recompose-then-persist once through the existing atomic writer.
Recorded in `DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-5 — Timeout behavior valueless (MEDIUM)

§6/§8 cite "timeout behavior" with no value; the queue/timeout invariant
(`timeout_seconds < job_timeout_seconds < retry_after_seconds`, 300/330/420)
must hold.
Disposition: RESOLVE — the adapter honors `config('transcription.timeout_seconds')`
(300) as the provider ceiling via its timeout ctor arg default; no new timeout
keys; job/stale rules unchanged. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-6 — Size-ceiling source and named error undefined (MEDIUM)

§6/§13 cite "request-size ceilings" and rejection "with named error" but give no
source, value relation, or taxonomy code.
Disposition: RESOLVE — adapter-internal ceiling via ctor scalar, default bounded
at/below the 500 MiB product limit (product limit unchanged; provider caps are
adapter ceilings, never product changes); breach rejected before dispatch with
`TranscriptionFailure::MediaRejected` (non-retryable; retry would fail
identically; operator path is the self-hosted route), logged with ceiling +
actual size, zero partial dispatch. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-7 — Log-reconstruction checklist missing though required (MEDIUM)

§19 demands an approved log-reconstruction checklist, but none exists.
Disposition: RESOLVE — checklist embedded in reconciled §11: invocation
`requestId` (reused, never minted), chunk id (`chk_*`), `provider_key`,
`model_pinned`, parent attempt id + `attemptSeq`, outcome, timing ms, failure
category; secrets/media-bytes/full-text absent. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-8 — Kill-switch interplay has no acceptance criterion (MEDIUM)

Contract relies on the T2 resolver (AC1) but never asserts the kill-switch
forces self-hosted over the PP-T3 binding.
Disposition: RESOLVE — add AC: kill-switch engaged → resolution returns
self-hosted even when selection names the PP-T3 binding (assert zero adapter
invocations); reuses the PP-T2 mechanism, no new logic. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-9 — Chunk idempotency key not stated in-contract (MEDIUM)

§14 mentions CAS/idempotency tests but §§8/9 never define the key.
Disposition: RESOLVE — key `(transcription_id, processingAttemptId,
chunk_index, attempt_seq)` stated in §8; recompose-then-persist once through
the single atomic persist path. Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### M-10 — PP-T3/PP-T5 ceiling boundary overlap (MEDIUM)

Both T3 ("ceilings") and T5 ("operator safety ceilings") claim ceilings;
unbounded, T3 could implement T5's runbook/alerts.
Disposition: RESOLVE — PP-T3 owns adapter-internal enforcement + log emission
only; kill-switch procedure/runbook/alerts/spend review stay PP-T5-owned (PP-T5
§6 cross-reference both ways). Recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`.

### L-1 — Fixture corpus not enumerated (LOW)

§19 requires an enumerated fixture corpus; §13 implies but never lists it.
Disposition: RESOLVE — minimal corpus embedded in reconciled §14: offsets,
overlaps, exact + near duplicates, language transitions incl `und`-fallback,
no-speech, empty, invalid payloads, timeout/429/5xx/partial/unknown errors,
oversized. Non-blocking detail, resolved inline.

### L-2 — No-migration rule implicit only (LOW)

§§7/11 imply logs-only but never state "no migration" outright.
Disposition: RESOLVE — explicit clause: no migration in PP-T3; any claimed need
→ STOP + HPO escalation, never a silent additive column (ADR-027 logs-only
rule). Resolved inline.

### L-3 — Translation non-interference asserted only via regression (LOW)

Covered by §16 suites; made explicit as a regression-gate item. Resolved inline.

### I-1 — Fixture binding name (INFO)

The concrete `transcription.providers.<fixture_name>` binding name is an
implementation detail within the contract (test-only binding); no product
decision. Noted, no action.

## C. PP-T1 / PP-T2 compatibility audit

- PP-T1 abstractions (`TranscriptionProvider`, `NormalizedTranscript`,
  12-case taxonomy, parity rule): PP-T3 implements the frozen interface,
  returns the frozen DTO, maps into the frozen taxonomy; vendor structs die
  inside the adapter (firewall rule preserved). No interface change. PASS.
- PP-T2 resolver semantics (per-resolution evaluation, pure selection,
  binding convention, fail-closed, kill-switch, request identity): PP-T3 is
  selected ONLY via the T2 resolver; adds no call site, no routing argument,
  no second request identity (reuses invocation `requestId`; `chk_*` is
  execution-only log correlation). Tests bind fixtures under the PP-T2
  `transcription.providers.*` convention without touching boot validation.
  PASS with reconciled H-3/M-8 constraints.
- Interface-only orchestration boundary: orchestration keeps depending only on
  `TranscriptionProvider`; no provider-to-provider calls; no fallback inside
  the adapter (M-3 table has no fallback arm). PASS.
- Queue/lifecycle/schema: `tries=1` manual-only, stale rules, atomic writer,
  no new queues, no migration (L-2). PASS.
- No authorized extension of T1/T2 surfaces is required; PP-T3 is purely
  additive behind the existing seam.

## D. Scope boundary against PP-T4–PP-T6

Must NOT be implemented in PP-T3: vendor selection and any live
credentials/traffic/customer-media egress; automatic routing/failover/retry
schedulers; external translation (PP-T4, deferred); kill-switch
procedure/runbook/alerts, secrets rotation procedure, spend/billing controls
(PP-T5 — T3 emits fields/logs only); integration-gate execution and verdict
reporting (PP-T6 FINAL_GATE_ONLY); client-side/Auto/Local UI; durable chunk
table; translation-path changes; queue/lifecycle redesign; schema changes.
PP-T4 stays DEFERRED; PP-T5/PP-T6 stay BACKLOG / NOT AUTHORIZED.

## E. Review verdict

`PP-T3 = NOT_READY` on the as-authored 91-line contract (3 HIGH + 10 MEDIUM
require reconciliation). No BLOCKER. All findings are contract-precision gaps;
none reopen a closed decision and none require vendor, schema, or architecture
choices beyond the non-controversial canonical options recorded in
`DECISION-PP-T3-CONTRACT-RECONCILIATION-001`. Proceed to reconciliation (§D of
the Step-1 prompt); then a fresh confirmation must return READY-eligible before
any promotion or authorization.
