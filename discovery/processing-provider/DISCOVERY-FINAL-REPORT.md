# ProcessingProvider Discovery — Final Report (DISCOVERY ONLY)

Date: 2026-09-27. Authorization: HPO Discovery Execution (research only). No production code touched. Spikes NOT executed. No vendor selected. No default changed.

## A. Scope executed

P0: A01, A02, A03, H01, H02, I01/I02/I03, J01/J02/J03, E01, D03, Q01 — complete. P1: B01–B04 (design), C01–C04 (design), D01/D02, E02/E03, F01/F02, G01/G02 (design), K01–K03, L01–L03, M01–M03, N, O, P — research complete, measurements deferred to spikes. Artifacts in `discovery/processing-provider/`.

## B. Architecture boundaries

Transcription: Orchestrator → IDs-only queue → claim → Http provider → internal `/transcribe` → validator → NormalizedTranscript → atomic writer; manual retry + stale recovery. Narrowest seam: `TranscriptionServiceProvider::register()` (construction+mapping only). See `CURRENT-TRANSCRIPTION-BOUNDARY.md`.
Translation: Orchestrator → `translation` queue → token claim → text-only Http provider → strict validator → TranslationResult → atomic writer; machine-source-only + additive staleness. Narrowest seam: Request/Validator/error-map + Result shape. See `CURRENT-TRANSLATION-BOUNDARY.md`.

## C. Abstraction options (no selection)

(A) Separate public contracts, shared cross-cutting shapes — lowest risk. (B) Generic `ProcessingProvider<I,R>` + per-domain adapters. (C) Shared base/traits, no unified type. See `PROVIDER-ABSTRACTION-OPTIONS.md`. HPO picks later.

## D. Client feasibility

Desktop: feasible direction (whisper.cpp/faster-whisper; Apple Silicon > Intel; Linux reuses server most). Mobile: small/quantized feasible; large-v3 MATRIX RISK. Browser: tiny/base/short feasible; large-v3 MATRIX RISK; mobile browsers worse. 500 MiB whole-file + no-resume + 2–3× WASM memory = key constraint. See `CLIENT-FEASIBILITY-COST-OPS-UX.md`, `PRIVACY-DATA-MOVEMENT.md`.

## E. External landscape

Direct (OpenAI/Deepgram/AssemblyAI/hyperscalers) vs managed Whisper (Groq/Fireworks) vs aggregator (OpenRouter: pin via order+no-fallback+require_parameters+ZDR or accept opacity). Per-lang ms/ta + code-switch fidelity + retention/region are the UNKNOWNs per candidate. See `EXTERNAL-LANDSCAPE.md`.

## F. Normalization findings

Matrix + recomposition rules in `PROVIDER-ABSTRACTION-OPTIONS.md`. Hard rules: absolutize chunk timestamps; no resegmentation; unknown lang → `und`; confidence/words/chunks never leak into result; external codes map into 12/10-case taxonomies; per-chunk retry reuses attempt-identity + atomic persist. Word→segment rollup + per-chunk audit rows UNKNOWN.

## G. Trust model

Token + checksum + nonce/expiry + claim row + version + ownership + revision guard; 8-threat table. See `CLIENT-RESULT-TRUST-MODEL.md`. Lifetimes/caps need later contract.

## H. Privacy model

Mode×data matrix; Local/Private minimum proposal; 4 consent options (no selection). Telemetry/crash/backup/translation-switch egress risks listed. See `PRIVACY-DATA-MOVEMENT.md`.

## I. Failure/recovery

Upload XHR same-attempt retry; queue tries=1, 300/330/420 invariant, stale timeout+60s, manual retry only. No chunk-level idempotency exists. Cloud faults map into existing taxonomies. See feasibility file.

## J. Cost model

Relative curves only (ext linear vs flat-infra-if-hot vs hybrid offload). No firm quote. See feasibility file.

## K. Operational complexity

SH/EXT/AGG/OD matrix (deploy/monitor/incident/support/versioning/model-dist). Pinned weights reproducible; hosted drifting; on-device store-gated. See feasibility file.

## L. UX requirements

Selector + Auto explanation + Local-Only guarantee + consent + resource/download/progress/interruption/outage states. Labels conceptual. HPO terminology decision later.

## M. Compatibility result

PASS with two escalation candidates (chunk-artifact identity; per-chunk audit/long-audio timeouts). No STOP contradiction found. See `PHASE17-COMPATIBILITY-REVIEW.md`.

## N. Known unknowns

Per-device RTF/RAM/thermal/background-kill; browser sustained behavior; external timestamp/code-switch ms/ta fidelity; chunk-boundary loss; reliable capability signals; true egress per mode; scale costs; prod queue/worker env values; prod UI entry point.

## O. Proposed spikes

SPIKE-01..05 proposal-only, NOT executed. See `SPIKE-PROPOSALS.md`.

## P. Owner decisions required

Abstraction shape; trust model + caps; Local semantics + fallback consent; retention; mode labels; client scope; vendor/provider; any model-default change; spike execution; architecture selection; chunk-identity/audit rows.

## Q. Three architecture options (no selection)

### Option 1 — Server-first + pinned external overflow (lowest risk)
Topology: keep current self-hosted CPU path canonical; add one pinned direct-STT overflow + one hosted-translation fallback behind existing seams; policy layer owns Auto routing; no client inference. Privacy: server + declared overflow egress only; LocalOnly = stay on self-hosted. Failure: existing manual retry + provider map; overflow faults map into taxonomies. Compat: FULL PASS (no contract change; chunking inside preparing/transcribing). Infra: Linux CPU + API keys + pinning config. Cost: flat base + linear overflow. Ops: low-medium. Risks: vendor outage/limits; timestamp drift if unpinned. Migration: additive adapter + policy, no lifecycle change.

### Option 2 — Local-first desktop + server fallback (privacy-led)
Topology: desktop native worker (whisper.cpp/faster-whisper reuse) primary for opted devices; server CPU canonical fallback; no mobile/browser inference initially; translation stays server NLLB. Routing: capability + explicit ThisDevice/Server/Auto + consent-gated fallback. Privacy: LocalOnly enforceable on desktop (no egress; audited); fallback requires explicit consent. Failure: local crash/sleep/kill → resumable chunk retry (new chunk protocol reusing claim/CAS); server path unchanged. Compat: PASS w/ escalation items (chunk identity + audit rows need HPO contract). Infra: per-OS build + model dist + server as-is. Cost: near-zero marginal for local share; pays client QA. Ops: medium-high (matrix + store/dist). Risks: device variance; 500 MiB local memory; support burden. Migration: largest (chunk protocol + trust binding + desktop app).

### Option 3 — Hybrid server + client + aggregator with strict pinning (max optionality)
Topology: server CPU + desktop local + OpenRouter-aggregator overflow, all behind separate Transcription/Translation contracts; per-row effective-model provenance; translation NLLB default + hosted option. Routing: policy/resolver owns all decisions; transcription/translation independent. Privacy: per-mode matrix enforced; aggregator path always consent-gated + ZDR/pinned. Failure: full matrix (local interrupt + cloud fault + chunk retry) with idempotency keys. Compat: CONDITIONAL (needs chunk + provenance + audit contracts; else escalation). Infra: all of 1+2 + gateway config. Cost: most complex, minimizes paid minutes at max QA cost. Ops: highest (two-vendor triage, silent re-route guards). Risks: opacity, base64 overhead, dual retention, determinism loss. Migration: phased (Option 1 first, then 2, then aggregator).

## R. Verdict

`DISCOVERY_COMPLETE — OWNER_ARCHITECTURE_DECISION_REQUIRED`
(P0 evidence complete; P1 research complete at design level; measurements await separately-authorized spikes.)

## S. Next legal action

Present the three options + owner-decision register to the HPO. No implementation or spike execution until separately authorized. Phase 1–7 contracts unmodified (FACT: no production file touched).
