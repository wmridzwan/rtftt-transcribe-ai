# DISC-B/C/D/K/L/M/P + N/O — Client feasibility, cost, ops, UX

Research date: 2026-09-27. [VENDOR CLAIM] vs community reports vs [UNKNOWN]. Default-model change NOT authorized.

## Desktop (B)

Windows/macOS/Linux: whisper.cpp (CPU/Metal/CUDA/Vulkan, CoreML-encoder flag, Q5 ~0.5–1 GB medium/large) + faster-whisper (CTranslate2, server-GPU class) = FEASIBLE direction [VENDOR CLAIM + community benches]; large-v3 needs ~10 GB VRAM class / 5 GB+ RAM [community reports, UNVERIFIED here]. macOS: split Intel vs Apple Silicon (Metal/CoreML/NPU) — Apple Silicon materially better [VENDOR CLAIM]. Linux desktop can reuse server worker most directly (INFERENCE). Packaging/updates/model-download/background/sandbox per-OS work remains [UNKNOWN].

## Mobile (C)

iOS (CoreML/ANE/Metal, WhisperKit pre-converted weights; background audio limits; tiny/base only on watch-class) + Android (NNAPI fragmented, ORT EP per-device) = small models feasible; large-v3 = MATRIX RISK (memory, thermal, background kill) without per-device benchmark [VENDOR CLAIM + docs]. Supported-model strategy (large-v3/turbo/medium/small/quantized): evidence only; any default change HPO-controlled.

## Browser (D)

WASM SIMD ~2–3×RT tiny/base; WebGPU/ONNX turbo-large ~2.7 GB download; tab-suspend kills long jobs; FF ≤256 MB file cap noted [VENDOR CLAIM: whisper.cpp.wasm caps at small; browser-whisper lists large-turbo without perf guarantee]. large-v3 in-tab = MATRIX RISK (download, RAM, suspend). Mobile browsers (Safari iOS vs Chrome Android) strictly worse than desktop (INFERENCE).

## Capability (K) / Large-media (L) / Failure (M)

- Reliable signals (FACT, server-observable): media limits, language vocab, contract_version, canonical model, queue/timeout/stale thresholds, claim/expiry state.
- Unreliable (INFERENCE): browser-reported memory/codec/network — hints only; acceptance re-verified server-side. No client-capability endpoint exists.
- Benchmark-vs-static classification: short local benchmark is viable alternative to static detection (proposal only).
- Local preprocessing (extract→transcribe vs extract→upload): bandwidth direction favors local extract; needs WASM FFmpeg proof (UNKNOWN).
- Interruption (FACT): upload XHR retry same attempt; queue `tries=1`, job 330s < retry_after 420s, provider 300s, stale threshold timeout+60s, manual retry only. Chunk-granularity idempotency does NOT exist — new design would reuse claim+expiry+CAS.
- Cloud failure: timeout/429/5xx/outage/partial/invalid → map into 12/10-case taxonomies; idempotency/billing per provider [UNKNOWN specifics].

## Cost (N) — relative characteristics, NOT firm quotes

- 10 min/d (~5h/mo): ext-STT cents–single-$; CPU VPS flat dominates; GPU wasteful; local free marginal.
- 1 h/d (~30h/mo): ext-STT ~$5–15/mo order; small CPU VPS comparable; GPU still > usage.
- 10 h/d (~300h/mo): ext-STT ~$50–150/mo class; 24/7 CPU VPS cheaper if hot; rented GPU (L4/A40 ~$0.4–0.5/hr+idle) crosses only at sustained load.
- 100 h/d / 1000 h/mo: ext scales linearly; rented GPU/reserved ($1.4–4+/hr) or owned wins IF hot; hybrid offloads share to zero marginal + pays client QA matrix.

## Operations (O)

SH: model-dist + image + FFmpeg; queue depth/GPU/VRAM/OOM; OOM/regression incidents; pinned weights reproducible. EXT: keys + poll; quota/latency budgets; outage/chunk faults; drifting checkpoints. AGG: gateway + pinning; routing + cost-anomaly per effective model; silent re-route/drift. OD: per-OS build + weight bundle; sparse telemetry; device-matrix support; store-gated rollouts.

## UX (P) — requirements only, no final UI

Mode selector (Auto/ThisDevice/Server/Cloud/LocalOnly labels conceptual), Auto explanation, Local-Only guarantee text, cloud consent, resource estimates, model download progress, background progress, interruption/fallback/outage states. Terminology HPO decision later.
