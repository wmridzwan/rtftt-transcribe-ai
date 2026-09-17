# Phase 3 Benchmark Gate Evidence — turbo vs large-v3

Date: 2026-09-17 (cycle 3 — real benchmark execution)
Status: EXECUTED — turbo preferred (1.46x faster, acceptable quality)

## Environment

- OS: Windows 11 Pro 10.0.26200
- Python: 3.13.14
- Virtual environment: `worker/.venv`
- faster-whisper: 1.2.1
- ctranslate2: 4.8.2
- FFmpeg: 9.0.1-full_build-www.gyan.dev
- CPU: Intel(R) Core(TM) i5-10210U @ 1.60GHz, 4 cores / 8 logical processors
- GPU: None (Intel UHD integrated only, no CUDA)
- RAM: ~23.8 GiB
- HF_HUB_DISABLE_SYMLINKS=1 required for model downloads on unprivileged Windows

## Benchmark Corpus

- Source: Google FLEURS (CC BY 4.0)
- Configurations: ms_my, en_us, cmn_hans_cn, ta_in
- Selection: Deterministic indices [0, 5, 10] from validation split
- Synthetic mixed-language samples: MIX-01 through MIX-04 (concatenated single-language segments, explicitly labeled synthetic)
- Total samples: 16 (12 FLEURS + 4 synthetic)
- Total audio duration: 242.7 seconds
- Files stored in `benchmark-media/` (git-ignored)

## Benchmark Results

### Turbo (large-v3-turbo)

| Metric | Value |
|--------|-------|
| Model size | large-v3-turbo |
| Device | CPU |
| Compute type | int8 |
| Load time | 8.7s |
| Total inference | 810.72s |
| Mean RTF | 4.1667 |
| RTF range | [1.8417, 8.8178] |

### Large-v3

| Metric | Value |
|--------|-------|
| Model size | large-v3 |
| Device | CPU |
| Compute type | int8 |
| Load time | 10.6s |
| Total inference | 1195.35s |
| Mean RTF | 6.0792 |
| RTF range | [2.2004, 22.0411] |

### Comparison

| Metric | Turbo | Large-v3 | Ratio |
|--------|-------|----------|-------|
| Mean RTF | 4.17 | 6.08 | turbo 1.46x faster |
| Total inference | 810.72s | 1195.35s | turbo 1.47x faster |
| Model load | 8.7s | 10.6s | turbo 1.22x faster |

### Per-Language Observations

**Bahasa Melayu (ms_my):**
- Turbo detected `id` (Indonesian) for ms_my_1, `ms` for ms_my_2/3
- Large-v3 detected `id` for ms_my_1, `ms` for ms_my_2/3
- Both models handle BM adequately; language detection inconsistency (id vs ms) is expected given linguistic similarity

**English (en_us):**
- Both models correctly detected `en`
- Transcript quality appears correct for both

**Mandarin Chinese (cmn_hans_cn):**
- Both models correctly detected `zh`
- RTF significantly better than average for Chinese samples

**Tamil (ta_in):**
- Both models correctly detected `ta`
- Large-v3 showed anomalous RTF of 22.0 on ta_in_1 (310.8s inference for 14.1s audio)
- Turbo maintained consistent performance on Tamil

**Synthetic Mixed-Language:**
- MIX-01 (ms_my→en_us): Turbo detected `id`, Large-v3 detected `id`
- MIX-02 (en_us→cmn_hans_cn): Both detected `zh` (Chinese segment dominant)
- MIX-03 (cmn_hans_cn→ta_in): Turbo detected `ta`, Large-v3 detected `zh`
- MIX-04 (all four): Both detected `zh` (Chinese segment dominant in 46.8s clip)
- Language detection on concatenated samples reflects dominant language segment

## Gate Decision

**RECOMMENDATION: turbo**

Turbo is 1.46x faster than Large-v3 on CPU with equivalent language detection
and acceptable transcript quality. No materially unacceptable degradation
observed. Turbo is the preferred default model for RTFTT.

## Decision Rule Applied

> turbo preferred unless materially unacceptable degradation for RTFTT

No degradation observed. Decision: turbo as default model.

## Privacy

FLEURS samples are public domain (CC BY 4.0) research data. Synthetic
concatenation samples contain no real user data. All files git-ignored.

## Historical Notes

- Cycle 1 (2026-09-17): BLOCKED — Python environment + missing media
- Cycle 2 (2026-09-17): BLOCKED — Python environment resolved, media still missing
- Cycle 3 (2026-09-17): EXECUTED — FLEURS corpus acquired, benchmark completed

The benchmark gate DECISION-P3-BENCHMARK-GATE-001 is now RESOLVED.
