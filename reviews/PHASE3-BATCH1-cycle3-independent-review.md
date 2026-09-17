# REVIEW — Phase 3 Batch 1 — Independent Review (Cycle 3)

## Review Status

CHANGES_REQUESTED

## Task

Task Files:
- tasks/P3-001-transcription-domain-contract.md
- tasks/P3-002-provider-worker-transport-contract.md
- tasks/P3-003-python-ffmpeg-fasterwhisper-provider.md

Implementation Owner: OpenCode
Reviewer: Claude Code (independent, cycle 3 of the three-cycle escalation policy)

This is cycle 3 of the batch review defined by the Phase 3 Batch-Execution
Exception (`.ai/guidelines/orchestration-policy.md`). It independently
re-reviews the correction cycle recorded in commits after cycle 2
(`reviews/PHASE3-BATCH1-cycle2-independent-review.md`) against all prior
findings.

## Review Scope

Independently reconstructed from Git history and current repository state
(no reliance on chat/agent summaries):

- `git diff` from cycle 2 baseline to current HEAD
- Current codebase: `app/Transcription/`, `worker/`, `config/transcription.php`,
  `tests/Unit/Transcription/`, `tests/Feature/HttpTranscriptionProviderTest.php`,
  `worker/tests/`
- `BENCHMARK-GATE-EVIDENCE.md`
- `DECISION_QUEUE.md`

## Findings

### H5 — Segment Language Contract Violation (HIGH)

**Finding:** The segment-level language derivation relies on a fictional
`segment.language` attribute that does not exist in the faster-whisper API.

The faster-whisper `Segment` dataclass fields are: `id`, `seek`, `start`,
`end`, `text`, `tokens`, `avg_logprob`, `compression_ratio`, `no_speech_prob`,
`words`, `temperature`. There is no `language` field.

Current code at `worker/transcription.py:64`:
```python
seg_lang = getattr(seg, "language", None) or info.language or "und"
```

This always falls back to `info.language` (the transcript-level dominant
language), meaning every segment receives the same language label regardless
of actual content. This violates OD-01: every normalized transcript segment
must contain one dominant / best-supported language value independently
derived for that segment.

The existing test `test_segment_language_not_copied_from_transcript` mocks
`seg.language` to simulate per-segment language — this is a false-assurance
test that passes against fictional API behavior and would fail against the
real faster-whisper API.

**Required:** Implement genuine per-segment language identification using
`WhisperModel.detect_language()` on each segment's audio span.

### H6 — Model Quality / Tamil Evidence (HIGH)

**Finding:** The turbo model benchmark showed cross-script corruption on
Tamil sample `ta_in_1` (RTF=5.9, anomalous processing time). The original
Tamil benchmark set contained only 3 real samples — insufficient for a
model-selection gate decision.

The previous statement "No materially unacceptable degradation observed"
must be withdrawn/corrected.

**Required:** Expanded Tamil benchmark (≥15 real samples), expanded
mixed-language benchmark (8+ additional transition samples), re-evaluation
of turbo vs large-v3 with script-corruption analysis.

### S1 — Decision Queue Vocabulary (STRUCTURAL)

**Finding:** `DECISION-P3-BENCHMARK-GATE-001` was changed to
`Status: RESOLVED`. The canonical queue statuses are OPEN, DECIDED,
WITHDRAWN. `RESOLVED` is not a valid status token.

Additionally, because model selection has been reopened by this HPO
escalation decision, the benchmark/model decision should remain OPEN
until expanded evidence reaches a final HPO-supported gate outcome.

### S2 — CURRENT_STATE (STRUCTURAL)

**Finding:** `CURRENT_STATE.md` contains stale current-tense statements
that misrepresent the task state. It references the benchmark as having
a "valid outcome" when the HPO escalation has reopened model selection.

## Three-Cycle Escalation Conclusion

Cycle 1 = CHANGES_REQUESTED
Cycle 2 = CHANGES_REQUESTED
Cycle 3 = CHANGES_REQUESTED

Three consecutive CHANGES_REQUESTED cycles trigger escalation to the
Human Product Owner under the three-cycle escalation policy.

**BATCH 1 NOT VERIFIED**

## Required Changes

1. H5: Implement genuine per-segment language identification using
   `WhisperModel.detect_language()` on each segment's audio span
2. H5: Replace false-assurance test with tests matching real faster-whisper
   API shape
3. H5: Measure segment-LID overhead on representative mixed samples
4. H6: Expand Tamil corpus to ≥15 real FLEURS samples
5. H6: Expand mixed-language corpus with 8+ additional transition samples
6. H6: Run expanded benchmarks (turbo + large-v3) with script-corruption
   analysis
7. H6: Produce model gate decision (A: turbo acceptable, or B: requires
   large-v3 / HPO decision)
8. S1: Fix Decision Queue vocabulary (RESOLVED → OPEN/DECIDED)
9. S2: Update CURRENT_STATE.md to reflect HPO escalation remediation
10. INFO: Fix tempfile.mktemp race condition in worker/ffmpeg.py
11. BENCHMARK-GATE-EVIDENCE.md: Correct to disclose Cycle-3 observations
