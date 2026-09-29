# DISC-E/F — External provider landscape (time-sensitive, no selection)

Research date: 2026-09-27. Labels: [VENDOR CLAIM] vs [INDEPENDENTLY VERIFIED] vs [UNKNOWN]. No vendor selected. No data sent anywhere.

## E01 — Bounded STT landscape

1. OpenAI direct (gpt-transcribe/whisper-1/gpt-4o-transcribe): [VENDOR CLAIM] seg+word timestamps on whisper-1 via timestamp_granularities; detected languages[] + hints; 99+ langs; 25 MB limit; per-min ($0.006 whisper/gpt-4o, $0.0045 transcribe, $0.003 mini). ms/en/zh/ta listed supported; code-switch prompt/keyword hints, no published ms-mix WER [UNKNOWN].
2. Deepgram Nova-3: [VENDOR CLAIM] word timestamps, diarization, smart-format; detect_language pre-record dominant only (includes ms+zh, NO ta in detect list); multilingual Nova-3 in-stream; ~$0.0077 mono / $0.0092 multilingual per min. Retention/region/SLA enterprise-gated [UNKNOWN].
3. AssemblyAI Universal-3.5: [VENDOR CLAIM] word timestamps, 99+ langs, 18 native code-switch, diarization; async $0.21/hr, realtime $0.45/hr + add-ons; 99.9–99.95% SLA; ~10h files. Per-lang ms/ta accuracy + retention/region [UNKNOWN].
4. Managed Whisper (Groq LPU whisper-large-v3 $0.111/hr, turbo $0.04/hr, 100 MB max; Fireworks whisper-v3/turbo verbose_json + granularities, srt/vtt) [VENDOR CLAIM]. ~217–228x RT via reseller roundups [INDEPENDENTLY VERIFIED as cited claims only, not bench-verified here]. ms/ta/code-switch inherits Whisper limits [UNKNOWN — needs fixture].
5. Hyperscalers (Azure $1/hr RT / $0.18/hr batch; Google Chirp ms-MY/ta-IN/zh + word ts + alternates; AWS ms-MY/ta-IN/zh-HK + word ts + PII redact) [VENDOR CLAIM]. Long files via batch/async; caps per request. Latency/retention/SLA tenant-dependent [UNKNOWN].

## E02 — OpenRouter specifically (no selection)

[VENDOR CLAIM 2026-04/05 docs]: POST /v1/audio/transcriptions, base64 audio (wav/mp3/flac/m4a/ogg/webm/aac); models incl. whisper-large-v3, gpt-4o-transcribe/mini, Chirp 3, Groq fast-Whisper, mai-transcribe-2; verbose_json → text/usage/language/duration/segments/words. Routing defaults load-balance by price; order[]/allow_fallbacks/require_parameters/max_price alter it. Effective fidelity = hidden provider's [UNKNOWN unless pinned]. Payload max size/duration not stated [UNKNOWN; OpenAI 25 MB does NOT transfer]. Controls: order, allow_fallbacks=false, data_collection=deny, zdr, max_price, BYOK, EU/US residency [VENDOR CLAIM]. Billing per-model (per-sec vs per-token); billed on model used. Downstream retention per provider [UNKNOWN]. Audio-POST idempotency [UNKNOWN].

## E03 — Direct vs aggregator

Direct: one schema/SLA/DPA, stable timestamps; cost = lock-in + per-vendor integration. Aggregator: optionality + fallback + unified billing; cost = opacity (hidden model changes behavior), base64 bloat, dual billing/retention surface, weaker determinism. Pinning (order+no-fallback+require_parameters+ZDR) recovers control but surrenders most aggregator benefit; still needs per-row provenance logging.

## F01/F02 — Translation + LLM risk

- NLLB-200 self-host (600M–54B MoE) [INDEPENDENTLY VERIFIED paper]: 200 langs incl. ms/en/zh/ta; deterministic given weights+beam+seed; long docs need app segmentation; mixed-lang needs pre-segmentation [quality on ms-mix UNKNOWN without fixture].
- Google ($20/M chars, 130+ langs), Azure (50K chars/req, doc batch ≤40/250 MB, mixed-lang sentences may not translate), DeepL (ms/ta API support conflicting/beta — treat as UNKNOWN/BETA) [VENDOR CLAIM].
- LLM-translation risk: alignment loss breaks active-revision timing + P6-005 staleness ledger; sampling/version drift breaks history; reword explosion; edit-after-translate flag needs persisted per-row translation (P6-007 MEDIUM precedent). Any LLM path must emit segment-aligned versioned output or it is incompatible with frozen P6-001/P6-002.
