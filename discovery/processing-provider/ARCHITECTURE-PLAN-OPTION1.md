# Architecture Plan — Option 1: Server-first + Pinned Direct Overflow (PLANNING ONLY)

Status: PLANNING ONLY. No production code, migrations, vendor calls, spikes, or model-default changes authorized.
HPO direction: separate TranscriptionProvider / TranslationProvider; self-hosted canonical; deterministic routing; no silent fallback; no client/Auto in wave 1.

Legend: DECIDED = HPO/discovery-closed. PROPOSED = planning recommendation, needs HPO. UNKNOWN = needs spike/vendor docs.

## 1. Provider interfaces

- DECIDED: freeze `transcribe(Invocation): NormalizedTranscript` and `translate(Invocation): TranslationResult` plus Invocation/Result/Failure/lifecycle/writer/retry DTOs — byte-frozen.
- DECIDED: SelfHosted impls own current behavior (IDs-only payload, `1.0` envelope, Bearer, current timeouts); External impls own vendor transport + mapping only.
- PROPOSED: adapter-internal change limited to private shape-builder/validator/error-map; public ctor takes scalars only (baseUrl, token, timeout, pinned identity). Domain throws only `TranscriptionException`/`TranslationException`.

## 2. Resolver/policy boundary

- DECIDED: independent per-domain selection; providers never call each other.
- PROPOSED: `ProcessingPolicy` (deterministic config `provider=self_hosted|external_x`) → per-domain `ProviderResolver` at orchestrator seam (before dispatch/claim; jobs stay IDs-only + UUID `request_id` + attempt identity) → bound provider. One resolver per domain beside ServiceProvider, no generic type.
- PROPOSED: orchestrator mints `request_id` once; carried in Invocation, outbound call, logs, persisted row (`provider/model/request_id`); chunk retry reuses attempt identity, never appends duplicates.
- UNKNOWN: per-request flag vs per-queue/config switch; per-attempt audit row reuse (ADR-018 B3-04).

## 3. Existing adapter migration

- PROPOSED: rename-or-wrap `Http*Provider` → `SelfHosted*Provider` (+ deprecated alias one release); zero behavior change; existing tests pin envelope/`large-v3`/`distilled-600M`/`1.0`.
- PROPOSED: one class per external vendor (`ExternalX*Provider`), never shared generic HTTP. ServiceProvider switches on `config(provider)`; unknown name fails boot fast; `contract_version` stays `1.0`.
- UNKNOWN: alias vs pure-rename mechanics (container/test-ref spike).

## 4. External adapter boundary

- PROPOSED per adapter (all internal): outbound builder (auth, pinned model, absolute-ms ts); strict validator (count/index/ts-echo, finite, start≥0, end≥start); error map into 12-case/10-case taxonomies (taxonomy authoritative, vendor flag advisory); provider/model identity capture.
- DECIDED: vendor-struct firewall — no vendor JSON/headers/confidence/words/offsets leak past adapter; silent-partial prohibited.

## 5. Configuration

- PROPOSED keys (queue/timeout invariant `provider<job<retry_after` untouched): `*.provider=self_hosted|external_x`, `*.external_x.base_url/token/timeout_seconds/pinned_model/reported_identity`.
- PROPOSED env: `RTFTT_TRANSCRIPTION_PROVIDER`, `RTFTT_TRANSCRIPTION_EXTERNAL_X_{URL,TOKEN,TIMEOUT,MODEL}`, same `TRANSLATION_*`; tokens empty-default, never committed.
- PROPOSED boot validation: unknown name / missing URL+token when external / invariant violation / unpinned model → boot exception.
- UNKNOWN: vendor list + per-vendor timeout/model values.

## 6. Normalized mapping (DECIDED rules)

Absolutize chunk-relative ts (protects ±0.0005s check); ms precision; no resegmentation (unique/contiguous); allowlist miss → `und`; drop confidence/words (new field = escalation); no-speech form preserved; alignment from invocation/DB, never provider.

## 7. Chunk lifecycle (transcription-only)

Adapter-internal fan-out → one NormalizedTranscript → single atomic persist; no chunk fields leak. Offset `absolute = chunk_offset_ms + relative_ms`, re-validate; single overlap policy (default drop tail-of-k); deterministic exact-then-near-duplicate dedup; language transition = segment boundary else `und`. Retry: `(transcription_id, attempt, chunk_index, attempt_seq)` identity, bump seq, recompose-then-persist once. Chunk ID `chk_{short}_{idx:04d}_{seq:02d}` logs/traces only; segment identity untouched; original sha256 stays authoritative.
UNKNOWN: derived-chunk checksum/storage identity; word→segment rollup; long-audio timeout calibration.

## 8. Audit/observability

RECOMMENDED (HPO decides): ephemeral structured chunk logs + unchanged parent attempt rows (option A) over durable `chunk_attempts` table (option B, deferred). Identity fields `provider_key`/`model_pinned`/`contract_version`; correlation `request_id/transcription_id/attempt(+token)`; metrics per-chunk latency/outcome, recompose counts, absolutization rejections; health metadata out-of-band.
UNKNOWN: idempotency-key surfacing; log-forwarder egress; `report()` destination.

## 9. Privacy/security

Explicit selection ⇒ attributable egress; fail closed (no fallback); derived audio server-temp `ephemeral`, evict on terminal; off-host backup = full egress; ownership/authorize/claim/CAS unchanged; translation text-only; logs carry ids/timings/provider/outcome only (no media bytes, no full text); secrets env-only, loopback bearer reused. 500 MiB unchanged.

## 10. Retry/idempotency

Manual-only (`tries=1`) preserved; taxonomy mapping authoritative; token/claim fencing; duplicate → idempotent return; stale `provider+60s` only reclamation; chunk-retry composes under same guarantees; parent failure → mapped code, operator manual retry.

## 11. Schema implications (PROPOSED additive-only, NO migration written)

P1: nullable `provider_key`/`model_pinned`/`contract_version default '1.0'` on attempt rows (drop-safe, plain varchar, SQLite/Postgres safe). P2 (only if HPO picks durable chunks): new `chunk_attempts` table + unique `(transcription_id, attempt, chunk_index, attempt_seq)`, no FK changes. P3 non-change: no columns on machine-source/segment/revision tables. All need HPO + Phase-7 ops review.

## 12. Compatibility migration

Additive only behind existing seams; existing rows work unchanged; machine source immutable; P6 staleness/export unchanged; resolvers default self-hosted; unknown codes map into frozen taxonomies; rollback = config flip. No backfill redesign.

## 13. Test strategy

Contract tests (validator, error-map exhaustiveness, normalization); adapter units with recorded self-hosted + synthetic external fixtures (NO live calls, identical normalized output); IDs-only payload tests; manual-retry + stale-recovery (300/330/420, +60s); two-process CAS precedent; resolver determinism + kill-switch + provenance logging. UNKNOWN until spike/vendor auth: ms/ta fidelity, code-switch WER, drift magnitude, RTF/cost, retention/region, caps, device behavior.

## 14. Rollout/flags

Deterministic per-domain flags, pinned model, `allow_fallbacks=false` equivalent; default self-hosted; staged off → shadow/log-compare (optional) → canary → full (each HPO-gated); one-flag kill-switch to self-hosted; no Auto/client/aggregator in wave 1.

## 15. Task decomposition (proposed)

T1 Contract freeze (interfaces, results, error maps, normalization; non-scope: SDKs, resolver). T2 Resolver+policy (deterministic selection + provenance; non-scope: queues, Auto, client). T3 Self-hosted migration (parity; non-scope: external, behavior change). T4 External boundary (stubbed/contract-only, fakes+fixtures; non-scope: credentials, traffic, vendor choice). T5 Config/observability (flags, pinning, kill-switch, provenance logs, alerts; non-scope: billing, audit schema). T6 Verification gate (parity + failure-matrix + rollback rehearsal, FINAL_GATE_ONLY pattern; non-scope: spikes, prod traffic).

## 16. Acceptance criteria (vendor-neutral)

T1: contract tests pass; unknown→frozen taxonomy; fixtures cover ts/words/chunks/`und`. T2: same input+config → same provider 100%; default self-hosted; provenance per request. T3: parity green; machine source immutable; retry/stale/CAS unchanged. T4: external-shaped fixtures normalize; invalid/partial mapped; no egress in tests; builds credential-less. T5: kill-switch → self-hosted in one flag refresh; provenance queryable; synthetic-fault alerts fire. T6: fresh PASS report; rollback rehearsed; FAILs preserved with remediation filed.

## 17. Risks → mitigation + trigger

Timestamp drift → pin+absolutize+alert (trigger: tolerance decision). Resegmentation → hard rule+reject (trigger: chunk-identity decision). Silent fallback → no-fallback+provenance+cost alert (trigger: policy decision). Taxonomy gaps → map-unknown+tests (trigger: extension decision). Chunk duplication → identity+atomic+CAS (trigger: audit decision). Audit gaps → additive-nullable only (trigger: rows decision). Cost/rate-limit → budgets+backoff+kill-switch (trigger: ceiling decision). Retention/region → no traffic until DPA/region/ZDR confirmed (trigger: vendor decision).

## 18. Owner decisions still required

Vendor(s); external translation in wave 1 or not; config model; chunk identity; per-chunk audit; payload/chunk limits; Local future semantics; Auto future; client priority; spikes SPIKE-01..05.

## Readiness checklist for task-contract authoring

- [ ] Wave-1 boundary restated per contract (no client/Auto/aggregator).
- [ ] Frozen Phase 1–7 inputs listed as non-scope, no-modification clause.
- [ ] Contract tests + fixtures enumerated before implementation auth.
- [ ] Deterministic resolver + kill-switch AC, vendor-neutral.
- [ ] UNKNOWNs marked deferred, not assumed.
- [ ] Decision register linked; no decision pre-empted.
