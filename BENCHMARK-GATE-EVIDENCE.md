# Phase 3 Benchmark Gate Evidence — turbo vs large-v3

Last Updated: 2026-09-18 (HPO final model decision + corrected analysis)

## Gate Status

Initial benchmark execution = COMPLETED
Expanded benchmark execution = COMPLETED
Final model-selection gate = **DECIDED — large-v3 selected**

`DECISION-P3-BENCHMARK-GATE-001` = DECIDED.

## Final HPO Decision

**INITIAL PHASE 3 MODEL = large-v3.**

Turbo is **NOT** the default model. Turbo remains available only as a
non-default optional/experimental fast model profile (selectable via
`RTFTT_WHISPER_MODEL`), with no new UX/model-selection scope.

Rationale: turbo was materially faster (1.67x) but showed repeated
cross-script hallucination/corruption on real Tamil audio at approximately
20% of the expanded real Tamil sample set (3/15). The canonical Phase 3 rule
was "turbo preferred unless evidence demonstrates materially unacceptable
degradation." The HPO determined turbo's repeated real-Tamil corruption is
materially unacceptable for the initial default model.

## Evidence-Integrity Correction (IMPORTANT)

An earlier revision of this document claimed "15/15 real Tamil samples CLEAN
for turbo" and "the original Cycle-3 corruption was isolated, does not
repeat." **Those claims were incorrect.** They were produced by a defective
script-corruption detector that only checked CJK presence and a Tamil-script
ratio, and auto-passed empty transcripts as clean. It failed to detect
Hebrew, Cyrillic, Korean (Hangul), Arabic, Gurmukhi, Greek, and other scripts.

The defect was identified by the post-escalation independent review
(`reviews/PHASE3-BATCH1-post-escalation-independent-review.md`, BLOCKER-1)
and is corrected here using the reworked detector
(`scripts/benchmark/script_integrity.py`, with tests in
`scripts/benchmark/tests/test_script_integrity.py`).

The raw per-sample transcripts were preserved unchanged; only the derived
script-integrity analysis was regenerated. No model inference was re-run to
correct the analysis.

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
- HF_HUB_DISABLE_SYMLINKS=1 required for model downloads

## Expanded Benchmark Corpus

### Tamil Samples (15 real FLEURS)

Selection rule: Deterministic indices from `google/fleurs` `ta_in` validation
split. Initial: [0, 5, 10]. Expanded: [15, 20, 25, 30, 35, 40, 45, 50, 55,
60, 65, 70]. All indices selected before model execution. No cherry-picking.
All 15 have FLEURS reference transcriptions (3 backfilled from the FLEURS API).

### Non-Tamil FLEURS (9 samples)

ms_my, en_us, cmn_hans_cn: indices [0, 5, 10] each.

### Synthetic Mixed-Language (12 samples)

All labeled: SYNTHETIC MULTILINGUAL TRANSITION SAMPLE

| Alias | Composition | Duration |
|-------|-------------|----------|
| MIX-01 | ms → en | 14.3s |
| MIX-02 | en → cmn | 24.9s |
| MIX-03 | cmn → ta | 32.5s |
| MIX-04 | ms → en → cmn → ta | 46.8s |
| MIX-05 | en → ms | 14.3s |
| MIX-06 | cmn → en | 24.9s |
| MIX-07 | ta → en | 20.6s |
| MIX-08 | en → ta | 20.6s |
| MIX-09 | ms → ta | 21.8s |
| MIX-10 | ta → ms | 21.8s |
| MIX-11 | en → ms → ta → cmn | 46.8s |
| MIX-12 | ta → cmn → en → ms | 46.8s |

Total corpus: 36 samples (15 Tamil + 9 non-Tamil FLEURS + 12 synthetic mixed)

## Performance Results (preserved, unchanged)

| Metric | Turbo | Large-v3 | Ratio |
|--------|-------|----------|-------|
| Mean RTF | 4.6269 | 7.7306 | turbo 1.67x faster |
| Load time | 9.7s | ~10s | — |
| Device / compute | CPU / int8 | CPU / int8 | — |

Turbo's performance advantage is real and preserved as a known tradeoff.

## Tamil Quality Results (corrected)

### Turbo — 3 of 15 real Tamil samples corrupted

| Alias | Unexpected scripts | Note |
|-------|-------------------|------|
| ta_in_1 | Korean (2), Hebrew (5), Cyrillic (3) | Same sample that failed in Cycle 3 — **corruption reproduced** |
| ta_in_11 | Arabic (2), Korean (3), Cyrillic (4) | Multi-script hallucinated gibberish |
| ta_in_13 | Arabic (2) | Script contamination |

Turbo produced fluent multi-script hallucinated gibberish injected into
otherwise-Tamil output on 3 of 15 real Tamil samples (~20%).

### Large-v3 — 1 of 15 real Tamil samples corrupted

| Alias | Unexpected scripts | Note |
|-------|-------------------|------|
| ta_in_15 | Gurmukhi (91 chars, entire output) | Output rendered entirely in Gurmukhi (Punjabi) script; zero Tamil characters |

Large-v3's real-Tamil corruption rate is materially lower than turbo's
(1/15 vs 3/15), and its failure mode is a single wrong-script rendering
rather than fluent multi-script fabrication.

## Mixed-Language Results (corrected)

### Turbo — 5 of 12 synthetic mixed samples flagged

| Alias | Unexpected scripts |
|-------|-------------------|
| MIX-07 (ta→en) | Thai (2) |
| MIX-09 (ms→ta) | Cyrillic (2), Greek (1), Korean (1) |
| MIX-10 (ta→ms) | Greek (2) |
| MIX-11 (en→ms→ta→cmn) | Cyrillic (3), Devanagari (1) |
| MIX-12 (ta→cmn→en→ms) | Devanagari (1), Greek (200) |

### Large-v3 — 2 of 12 synthetic mixed samples flagged + 1 empty

| Alias | Observation |
|-------|-------------|
| MIX-07 (ta→en) | Cyrillic (4) |
| MIX-11 (en→ms→ta→cmn) | Cyrillic (4) |
| MIX-09 (ms→ta) | **Empty transcript** — total content omission (0 segments) |

Large-v3 remains cleaner than turbo on synthetic mixed samples (2 flagged +
1 empty vs turbo's 5 flagged), but is not perfect.

## Known Limitations (recorded honestly)

- Large-v3 is slower on CPU (~7.73 RTF vs turbo's ~4.63).
- Large-v3 still has occasional wrong-script/hallucination behavior
  (ta_in_15 rendered in Gurmukhi; MIX-09 produced empty output).
- Synthetic concatenated samples do not prove natural conversational
  code-switch accuracy. Naturalistic code-switch verification remains a
  P3-008 requirement.
- Phase 3 does not establish a WER SLA. No numerical accuracy threshold is
  defined by this decision.
- Script-integrity detection is heuristic; it flags unexpected scripts and
  empty output but does not prove semantic correctness. Names, numbers,
  acronyms, and legitimate borrowing may appear in other scripts.
- The earlier evidence document overstated turbo's Tamil quality due to a
  defective detector; this is corrected here and preserved as history.

## Model Gate Conclusion

```text
BENCHMARK REQUIRES HPO MODEL DECISION  ->  DECIDED: large-v3
```

The HPO selected large-v3 as the initial canonical Phase 3 model.
Turbo is non-default / experimental only.

## Decision Rule Applied

Canonical rule: turbo preferred unless evidence demonstrates materially
unacceptable degradation for RTFTT's multilingual workload.

Turbo's repeated real-Tamil cross-script corruption (3/15, ~20%) is
materially unacceptable for the initial default model. Therefore large-v3
was selected.

## Privacy

FLEURS samples are public-domain (CC BY 4.0) research data. Synthetic
concatenation samples contain no real user data. All media files git-ignored.

## Historical Notes

- Cycle 1 (2026-09-17): BLOCKED — Python environment + missing media
- Cycle 2 (2026-09-17): BLOCKED — Python resolved, media still missing
- Cycle 3 (2026-09-17): initial 16-sample benchmark; provisional turbo selection
- Cycle 3 (2026-09-17): REOPENED after Tamil corruption found
- Cycle 4 (2026-09-18): governance review; H5 fixed, H6 open
- HPO escalation (2026-09-18): narrow H6 remediation authorized
- Expanded benchmark (2026-09-18): 36 samples, both models
- Post-escalation review (2026-09-18): BLOCKER-1 — detector defect found;
  turbo Tamil corruption reproduced at 3/15; large-v3 ta_in_15 Gurmukhi;
  large-v3 MIX-09 empty
- HPO final decision (2026-09-18): **large-v3 selected**; turbo non-default
