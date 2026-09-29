# DISC-A03 / H01 / H02 — Abstraction options, normalization, chunking

Research date: 2026-09-27. Discovery only, no selection made.

## A03 — Shared vs separate (FACT base, INFERENCE comparison)

- FACT transcription: `transcribe(Invocation): NormalizedTranscript`; Invocation = transcriptionId+attemptId+requestId(UUID)+media+options; lifecycle 7-state with preparing+cancelled; 12-case failure enum; queue `transcription`; timeouts 300/330/420 + stale-`running` recovery; model `large-v3`; contract `1.0`.
- FACT translation: `translate(Invocation): TranslationResult`; Invocation text-only (transcriptionId+requestId+target+segments[]); lifecycle 5-state; 10-case failure enum; queue `translation`; same timeout invariant + stale-`translating` recovery; NLLB distilled-600M; contract `1.0`; strict validator (count/index/timestamps ±0.0005s/source-echo; alignment from invocation, no resegmentation).
- INFERENCE: domain (audio→segments vs text→aligned-segments), lifecycle, payload, retry sets, observability all argue contracts are intentionally non-unifiable today.

Options (no selection):
- (A) Keep separate public contracts; share only cross-cutting shapes (timeout invariant, lifecycle-guard, manual-retry pattern). Lowest risk to frozen Phase 3/5/6.
- (B) Generic `ProcessingProvider<I,R>` iface + per-domain adapters (unifies dispatch/observability, keeps DTOs distinct).
- (C) Shared abstract base/traits (queue-config, stale-recovery, UUID requestId) with no unified public type.
- HPO decision required: abstraction shape. (B)/(C) reduce duplication at cost of touching closed contracts.

## H01 — Normalization compatibility matrix (FACT existing, INFERENCE gaps)

| Capability | Existing RTFTT (FACT) | Gap for local/browser/mobile/external |
|---|---|---|
| Timestamps | finite, start≥0, end≥start; P6 ms precision, overlap legal, zero-length legal-never-active, no cross-segment monotonicity | Chunk-relative/rounded timestamps must be absolutized before persist or breach ±0.0005s translation check |
| Segments | segmentIndex unique non-neg; positions unique+contiguous; nav by revision identity/position | Resegmenting providers incompatible with no-resegmentation + never-remap rules |
| Language | one tag/segment + transcript dominant; `und` fallback; single-tag per segment even if code-switched | Providers without per-segment tags → `und`, never guess |
| Confidence | No field on NormalizedTranscript/SegmentData (absent — FACT) | Must be dropped or out-of-band; adding field = contract change → escalation |
| Word-timing | No field (absent — FACT) | Same; word→segment rollup rules UNDEFINED (UNKNOWN) |
| Chunk output | No chunk/offset/overlap fields; WorkerContract = version const only | All chunk metadata resolves at adapter boundary, never leaks into result |
| Error states | First-class no-speech (speechDetected=false, empty text/segments, `und`); authoritative Failure enums; worker retryable advisory-only | External/local codes map into 12-case/10-case taxonomies; silent-partial prohibited by count check |

## H02 — Chunk recomposition requirements (INFERENCE; no chunk fields exist today)

1. Offsets: absolute = chunk_offset + relative; re-validate start≥0, end≥start, finite, ms precision.
2. Overlap: single drop-vs-merge window; survivors must not duplicate segmentIndex (constructor-enforced uniqueness).
3. Dedup: deterministic exact/near-duplicate resolution in overlap zones; one index, contiguous positions.
4. Language transitions: transition = segment boundary; indecisive spans → `und`.
5. Per-chunk retry: IDs-only payload + UUID requestId + attempt identity; never append duplicates; single atomic persist; stale-attempt recovery (provider+60s) only reclamation path.
6. UNKNOWN: whether per-chunk attempts get auditable rows (ADR-018 B3-04 pattern) — undecided, HPO input.
