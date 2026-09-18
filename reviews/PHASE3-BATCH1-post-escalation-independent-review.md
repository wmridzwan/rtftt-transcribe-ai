# REVIEW — Phase 3 Batch 1 — Post-Escalation Independent Review

## A. Post-Escalation Verdict

**CHANGES_REQUESTED.**

This is not a normal review cycle and does not trigger a fifth autonomous
implementation cycle. It is returned to the Human Product Owner. H5 is
independently reconfirmed as genuinely fixed. The escalation-governance
correction is reconciled. H6's expanded benchmark evidence, however,
contains a BLOCKER-level evidence-integrity defect: the script-corruption
detector used to produce the "15/15 Tamil clean" claim only checks for CJK
characters, a Tamil-script ratio, and Latin-only output. It does not detect
Hebrew, Cyrillic, Korean (Hangul), Arabic, or Gurmukhi contamination, and it
auto-passes empty transcripts as "clean." Independent inspection of the
committed raw JSON results (not just the summary markdown) found:

- Turbo: 3 of 15 real Tamil samples (`ta_in_1`, `ta_in_11`, `ta_in_13`) contain
  severe multi-script hallucinated gibberish (Hebrew, Cyrillic, Korean,
  Arabic characters mixed into the Tamil output) — all recorded as
  `"clean": true`. `ta_in_1` is the *same sample* that showed cross-script
  corruption in Cycle 3; **the corruption reproduced**, and reproduced on two
  additional real Tamil samples, contradicting `BENCHMARK-GATE-EVIDENCE.md`'s
  central claim that "the original Cycle-3 corruption was isolated, does not
  repeat" and "0/15 real Tamil samples show script corruption for turbo."
- Large-v3: `ta_in_15` output is rendered entirely in Gurmukhi (Punjabi)
  script (91 Gurmukhi characters, zero Tamil characters) — not Tamil, not
  Malayalam script. The evidence document dismisses this as merely "a
  language-detection quirk (detected as Malayalam), not script corruption,"
  which is not an accurate characterization of the actual output text.
- Large-v3: `MIX-09` (a 21.84s ms→ta mixed sample) produced a completely
  empty transcript (`segment_count: 0`, `transcript: ""`), which the
  detector's `if not transcript: return {"clean": True}` short-circuit
  silently passes as clean. `BENCHMARK-GATE-EVIDENCE.md`'s claim "Large-v3:
  All 12 mixed samples: CLEAN" does not disclose this total content-omission
  failure.

These are not disputes over acceptable-degradation judgment calls (which
would properly belong to the HPO); they are factual misstatements of what
the reviewer's own committed evidence file shows, discoverable by reading
the raw JSON the markdown table is supposedly summarizing. The Benchmark
Gate cannot be verified on this evidence. See Sections J–Q and Finding
BLOCKER-1.

## B. Reviewer Independence

Confirmed. This session began from a cleared context with no memory of any
prior conversation. Before drawing conclusions, I independently verified via
`git show --stat` that this session did not author commits `abc5a3a` (H5),
`82ef690` (H6 expanded evidence), `6a76992` (escalation governance),
`022a75b` (Cycle 4 review), or `05a93a1` (reconciliation) — all were
authored by `wmridzwan` outside this session. No implementation code, test,
or governance artifact was modified by this review; only this file was
created.

## C. Git Reconstruction

Actual relevant commit range (oldest → newest), independently walked via
`git log --oneline`:

```
3ae43a4 Record Phase 3 Batch 1 cycle-3 independent review (CHANGES_REQUESTED)
afdd597 benchmark: execute turbo vs large-v3 gate, record real evidence
b331182 governance: normalize task states to IN_PROGRESS, update Decision Queue
b77c671 Record Phase 3 Batch 1 cycle-2 independent review (CHANGES_REQUESTED)
9af8e22 Governance: record Python retry outcome, narrow B1 to missing benchmark media
7ae874a Python correction: H4 conftest import-root fix + real-execution bug fixes
d6b5935 PHP correction: L5 Windows forward-slash drive-letter path rejection
a8e34cb governance: S1 fix Decision Queue vocabulary, S2 update CURRENT_STATE
abc5a3a H5: genuine per-segment language detection via detect_language() + INFO tempfile fix
022a75b Record Phase 3 Batch 1 cycle-4 independent review (CHANGES_REQUESTED)
6a76992 governance: apply BLOCKED per three-cycle escalation rule (HPO Option 2)
82ef690 H6: expanded benchmark evidence, gate decision, governance correction
05a93a1 reconciliation: escalation-option fix, evidence audit, script cleanup   (HEAD)
```

Verified via `git show --stat` on each of `abc5a3a`, `022a75b`, `6a76992`,
`82ef690`, `05a93a1`:

- `abc5a3a` touches only `worker/config.py`, `worker/ffmpeg.py`,
  `worker/tests/test_ffmpeg.py`, `worker/tests/test_transcription.py`,
  `worker/transcription.py` — scoped exactly to H5.
- `022a75b` adds exactly one file: the Cycle 4 review artifact.
- `6a76992` touches `CURRENT_STATE.md`, `DECISION_QUEUE.md`, and the three
  P3-00x task files (state field only) — no application code.
- `82ef690` touches `BENCHMARK-GATE-EVIDENCE.md` and adds
  `scripts/benchmark/analyze_results.py` / `backfill_refs.py`.
- `05a93a1` touches `.gitignore`, `CURRENT_STATE.md`, `DECISION_QUEUE.md`,
  and adds five benchmark scripts (`audit_evidence.py`, `expand_mixed.py`,
  `expand_tamil.py`, `resume_benchmark.py`, `run_expanded_benchmark.py`).

`git diff --stat 022a75b..HEAD` excluding `reviews/`, `DECISION_QUEUE.md`,
`CURRENT_STATE.md`, `tasks/`, `BENCHMARK-GATE-EVIDENCE.md`,
`scripts/benchmark/`, `.gitignore` returns **empty** — confirming no
application/worker/test code changed after Cycle 4 review other than what
H6 remediation and governance correction required. No Batch 2/3 paths
(`app/` transcript-persistence, Redis, Horizon), no unrelated Phase 1/2
files, and no undisclosed implementation entered the reconciliation
commits. `git status` is clean; `git clean -ndx` shows only standard
Laravel/Node/Python gitignored artifacts (`node_modules/`, `vendor` cache,
`storage/framework/views/*`, `.env`, `benchmark-media/`) — no stray
benchmark scripts or `__pycache__` outside `.gitignore`.

## D. Escalation Governance

```text
ESCALATION GOVERNANCE RECONCILED
```

Verified ordering: `6a76992` (BLOCKED transition + original, later-corrected
"HPO Option 2 (Defer)" decision text) was committed **before** `82ef690`
(the H6 remediation work it authorizes). The authorization therefore
precedes the work it licenses, not the reverse — the specific process
defect Cycle 4 flagged as Finding G1 (implementation continuing without a
prior recorded HPO decision) does not recur here. `05a93a1` then corrects
the decision's *label* — "Option 2 — Defer" did not accurately describe
what happened (none of the four canonical options fit a "keep BLOCKED but
authorize narrow remediation" action) — to an explicit "HPO Exception,"
without deleting or rewriting the original text out of `DECISION_QUEUE.md`;
the current entry narrates the original wording and explains why it was
wrong, and the original wording remains independently recoverable via
`git show 6a76992:DECISION_QUEUE.md`. `DECISION-P3-BATCH1-001` and Batch 2
authorization are untouched.

One residual, non-blocking observation: as with every prior HPO decision in
this repository's history (e.g. `DECISION-P2-CONCURRENCY-002`), the
"HPO Exception" is recorded by the same git author as the remediation
commits, with no separate identity distinguishing the human decision from
agent-authored commits. This is the repository's established
decision-recording convention throughout its history, not a new anomaly
introduced here, but it means this reviewer can confirm the *record* is
internally consistent and properly sequenced, not that a live human
decision independently occurred at that moment. Noted as INFO, not a
blocker.

## E. Task-State Review

Confirmed via `grep` on the task files' `## Status` fields:

```
P3-001 = BLOCKED
P3-002 = BLOCKED
P3-003 = BLOCKED
```

`CURRENT_STATE.md` and `DECISION_QUEUE.md` agree. Cycle 1–4 verdicts are
preserved unmodified as review-history (`reviews/PHASE3-BATCH1-cycle{1,2,3,4}-independent-review.md`
all still present, none rewritten) and are not represented as unofficial
lifecycle states — `CHANGES_REQUESTED`/`BLOCKED` are treated as history and
current state respectively, consistent with the canonical lifecycle. The
current BLOCKED state correctly represents "independently unverified
escalated work awaiting HPO release" — and, per Section A/Q below, remains
the *correct* state, because the new evidence does not yet support release.

## F. Previous Finding Regression Matrix

| Finding | Status | Evidence this cycle |
|---|---|---|
| H1 — bearer token | RESOLVED, no regression | No files in scope changed since Cycle 4; full Pest suite re-run passes (Section U) |
| H2 — requested_language | RESOLVED, no regression | Same; `test_requested_language_forwarded`/`test_auto_detect_when_null` still pass |
| H3/H4 — Python tests/bootstrap | RESOLVED, no regression | 31/31 Python tests independently re-executed (Section V) |
| H5 — segment-language fiction | RESOLVED, independently reconfirmed | Sections G–I |
| M1 — real IDs | RESOLVED, no regression | `HttpTranscriptionProviderTest.php` unchanged since Cycle 4 |
| M2 — lifecycle/retry/retranscription/ownership | RESOLVED, no regression | `tests/Unit/Transcription/*` unchanged since Cycle 4 |
| M3 — HTTP provider tests | PARTIALLY RESOLVED (unchanged) | Malformed-2xx-response gap and timeout-assertion gap flagged in Cycle 4 remain open; out of the HPO-authorized narrow H6 scope, so not a new regression — carried forward, non-blocking |
| L1/L5 — Windows paths | RESOLVED, no regression | `test_path_escape_rejected`, drive-letter regex tests unchanged and passing |
| L2 — configured timeout | RESOLVED, no regression | Unchanged |
| L3 — safe error messages | RESOLVED, no regression | Unchanged |
| L4 — implementation-note attribution | RESOLVED, no regression | Unchanged |

No regression found in any previously-resolved finding.

## G. H5 API Verification

Independently confirmed against the actually-installed package (not
assumed from memory):

```
worker/.venv: faster-whisper 1.2.1
inspect.signature(WhisperModel.detect_language) =
  (self, audio: Optional[ndarray]=None, features: Optional[ndarray]=None,
   vad_filter: bool=False, vad_parameters=None,
   language_detection_segments: int=1,
   language_detection_threshold: float=0.5)
  -> Tuple[str, float, List[Tuple[str, float]]]
```

`worker/transcription.py:_detect_segment_language` (lines 78–117) calls
`model.detect_language(audio=segment_audio, language_detection_threshold=LANGUAGE_CONFIDENCE_THRESHOLD)`
and unpacks `language, probability, _` — this matches the real signature
exactly. Independently confirmed the real `faster_whisper.transcribe.Segment`
namedtuple's fields via `Segment._fields`:
`['id', 'seek', 'start', 'end', 'text', 'tokens', 'avg_logprob', 'compression_ratio', 'no_speech_prob', 'words', 'temperature']`
— **no `language` field**, confirming the original H5 defect
(`getattr(segment, "language", None)`) was genuinely fictional API surface,
and that the fix now calls a real method with a real signature. This is not
a fictional-API repeat.

## H. H5 Semantic Verification

Per-segment language is derived from `_read_audio_segment()` slicing the
*actual* segment's `[start, end]` audio span from the prepared WAV
(`worker/transcription.py:39-75`), padded to a 0.5s minimum before
detection — not copied from `info.language` (the transcript-level value).
`requested_language` is forwarded to `model.transcribe(language=...)` as a
hint only (line 143) and is **not** passed into
`_detect_segment_language()`'s decision logic — segment language reflects
only the detected result, confirmed by
`test_requested_language_does_not_force_segment_language`. Confidence
gating: `probability >= LANGUAGE_CONFIDENCE_THRESHOLD (0.5)` else `"und"`
(lines 111–114); exceptions during detection also fall back to `"und"`
(lines 116–117); an empty/silent audio span returns `"und"` without calling
the model at all (lines 95–96). No transcript-level fallback fabricates
per-segment certainty. This matches the canonical contract (dominant
per-segment language, independently derived, `und` on low confidence,
`requested_language` non-forcing).

## I. H5 Test / Real Evidence

`worker/.venv/Scripts/python.exe -m pytest -q` from `worker/`: **31 passed**
(0.69s), independently executed by this reviewer, matching the reported
baseline exactly. Test-by-test inspection of `worker/tests/test_transcription.py`
confirms mocks match the real API shape (`mock_model.detect_language.return_value
= (lang, probability, [...])`, a 3-tuple, not a fictional attribute) and that
required semantics are directly tested: per-segment independent detection
across three different mocked languages within one transcript
(`test_segment_language_not_copied_from_transcript`), `und` fallback on low
confidence and on exception, empty-segment short-circuit, and
`requested_language` non-forcing.

**Real-inference smoke test performed by this reviewer** (distinguished from
artifact inspection): ran `WhisperModel('large-v3-turbo', device='cpu',
compute_type='int8').transcribe('benchmark-media/ta_in_1.wav', vad_filter=True)`
directly against the local model cache. Result: `lang=ta`, 1 segment,
transcript largely matching the FLEURS reference with one minor garbled
fragment ("மத்திetuseller"), and `hasattr(segment, "language") == False` —
independently reconfirming the real `Segment` object exposes no `language`
attribute, and reproducing genuine model execution (not fabricated
evidence). This run produced a different segment count (1, vs. 2 recorded
in the committed evidence) and a much less severe artifact than the
committed evidence's Hebrew/Cyrillic/Korean-laced version of the same
sample — consistent with faster-whisper's beam-search decoding being
non-deterministic across runs on this hardware/quantization, and
reinforcing (not undermining) Section A's finding: turbo's real-Tamil
hallucination behavior is a recurring, non-deterministic risk under CPU
int8 execution, not a one-off artifact isolated to a single historical run.

## J. Expanded Tamil Corpus

`benchmark-media/manifest.json` (git-ignored per `.gitignore` but present
locally) records 15 `ta_in` entries with `dataset: "google/fleurs"`,
`config: "ta_in"`, `split: "validation"`, deterministic `sample_id`
(FLEURS validation-split index as a string), and a non-empty
`reference_transcription` for all 15 (`ta_in_1` reference: "இந்த விதிகள்
திருத்தப்படுவதற்கு முன்னர் அனைத்து மாநிலங்களின் ஒருமித்த ஒப்புதலையும்
கோரியது..."). Indices `[0, 5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60,
65, 70]` match the required deterministic set exactly; `audit_evidence.py`
(committed, executable) independently re-derives `15/15` Tamil-with-reference
and both models' `36` total samples from the same files. Sample media
itself (`benchmark-media/*.wav`) is confirmed git-ignored and not committed.
No evidence of post-result index selection — the index set is fixed
independent of any model's output. **Provenance and construction are sound.**
The defect is not in corpus construction; it is in the *quality analysis*
applied to the outputs (Section A, Q).

## K. Expanded Mixed Corpus

`benchmark-media/manifest.json` records exactly 12 `mixed` entries
(`MIX-01`–`MIX-12`) with recorded `source_languages`/`source_files` (e.g.
`MIX-01: ["ms_my","en_us"] / ["ms_my_1.wav","en_us_1.wav"]`) and
`synthetic_label: "SYNTHETIC — concatenated single-language segments"`.
Compositions independently confirmed from `BENCHMARK-GATE-EVIDENCE.md` and
the manifest match the required set: en→ms (MIX-05), cmn→en (MIX-06),
ta→en (MIX-07), en→ta (MIX-08), ms→ta (MIX-09), ta→ms (MIX-10), and two
four-stage transitions (MIX-11, MIX-12), in addition to the original
MIX-01–04. Construction is deterministic (fixed source files per manifest)
and no natural-code-switch claim is made anywhere in
`BENCHMARK-GATE-EVIDENCE.md`. **Construction is sound**, same caveat as
Section J: analysis quality is the defect (Section N, Q).

## L. Raw Benchmark Integrity

Read the actual raw JSON files directly (not just the markdown summary):
`benchmark-media/results/expanded_benchmark_turbo.json` (36 samples) and
`expanded_benchmark_large-v3.json` (36 samples) — both models ran the
identical 36-sample corpus (15 Tamil + 9 non-Tamil FLEURS + 12 mixed),
confirmed by alias-set comparison. No sample present in one file is absent
from the other. Recomputed aggregates directly from the raw per-sample `rtf`
arrays:

```
Turbo:    36 samples, total_audio=596.1s, total_inference=2269.07s,
          mean_rtf = 2269.07/... per-sample mean = 4.6269  (matches)
Large-v3: 36 samples, total_audio=596.1s, total_inference=4322.41s,
          mean_rtf = 7.7306  (matches)
Ratio: 7.7306 / 4.6269 = 1.6706x  → "turbo 1.67x faster" is arithmetically correct.
```

No sample is excluded from either aggregate — `len(samples) == 36` in both
files and `aggregate.total_samples == 36` in both, confirmed programmatically.
Arithmetic claims in `BENCHMARK-GATE-EVIDENCE.md` are correct. **The
arithmetic is not in dispute; the corruption/cleanliness labeling attached
to the arithmetic is unreliable** (Section A, M, N).

## M. Turbo Results

Mean RTF 4.6269, range [1.5803, 9.054], 74 total segments — independently
recomputed and matches. Per-sample script-integrity flags in the raw JSON
mark 4 mixed samples corrupted (`MIX-07`, `MIX-09`, `MIX-11`, `MIX-12`,
all CJK-related) and **0 of 15 real Tamil samples** flagged corrupted.
Independent re-scan of the raw transcript text for Hebrew (U+0590–05FF),
Cyrillic (U+0400–04FF), Arabic (U+0600–06FF), Greek (U+0370–03FF), and
Hangul (U+AC00–D7A3 / U+1100–11FF) code points found:

```
ta_in_1:  Hebrew=5, Cyrillic=3, Hangul=2   (flagged_clean=True)
ta_in_11: Cyrillic=4, Arabic=2, Hangul=3   (flagged_clean=True)
ta_in_13: Arabic=2                        (flagged_clean=True)
```

`ta_in_1`'s turbo transcript diverges sharply from its own recorded FLEURS
reference transcription in its second half, replacing genuine Tamil content
with untranslatable multi-script noise ("...ஒப்புதலை கோரியது மற்றும் பெரிம்பாலும்
அ jabarak letsunderQue Div 번째 அங்கு இல்லாத்தால் ... deploy לשachusetts
goggומר மаже Golfordum") — this is hallucination confirmed against ground
truth, not merely a heuristic false-positive. `MIX-04` and `MIX-11`'s
"clean:false" flags also understate severity: `MIX-11`'s flagged reason is
"44 CJK characters," but the actual failure mode is a large fabricated
Greek-language passage with no Greek audio anywhere in the sample's source
composition — the detector caught the CJK symptom but not the underlying
hallucination.

## N. Large-v3 Results

Mean RTF 7.7306, range [2.0057, 23.6032], 56 total segments — independently
recomputed and matches. `tamil_corrupted: 0` and `mixed corrupted: 0/12` per
the raw JSON's own script-integrity flags. Independent findings the flags
missed:

- `ta_in_15`: output is 91 Gurmukhi-script characters, 0 Tamil characters —
  not a "detection quirk," an actual wrong-script hallucination on real
  Tamil audio.
- `MIX-09`: `segment_count: 0`, `transcript: ""` — total content omission on
  a 21.84-second sample, silently passed "clean" by the detector's
  empty-string short-circuit.

No comparable multi-script gibberish (Hebrew/Cyrillic/Korean/Arabic mixed
into otherwise-Tamil output) was found anywhere in large-v3's 15 real Tamil
transcripts — on the specific real-Tamil-audio corruption axis Cycle 3
originally raised, large-v3's genuine failure mode (wrong-script/omission on
isolated samples) is qualitatively different from, and appears less severe
than, turbo's (fluent multi-script gibberish injected into otherwise-correct
sentences on 3 of 15 samples).

## O. Tamil Comparative Assessment

`BENCHMARK-GATE-EVIDENCE.md`'s claim "Turbo Tamil = 15/15 clean, Large-v3
Tamil = 15/15 clean" and "the original Turbo corruption failed to reproduce"
is **not supported** by the raw evidence the document itself cites.
Independent inspection against the manifest's own reference transcriptions
shows the Cycle-3 corruption on `ta_in_1` reproduced, and reproduced on two
further real Tamil samples (`ta_in_11`, `ta_in_13`) — a 3/15 (20%) rate on
the very sample class (real Tamil audio) the Cycle-3 escalation was raised
to re-examine. Large-v3 also has a genuine, undisclosed real-Tamil quality
defect (`ta_in_15`), but it is a single sample versus turbo's three, and
turbo's failures are qualitatively worse (fabricated multi-script content
mixed into otherwise-fluent Tamil, which a downstream reader/consumer would
be more likely to trust than an obviously-wrong Gurmukhi block or an
outright missing transcript).

## P. Mixed-Language Comparative Assessment

Turbo's CJK fragments on `MIX-07`/`MIX-09`/`MIX-12` are largely legitimate
given real Chinese audio is present in those compositions' upstream mixed
chains (`MIX-11`, `MIX-12` chain through `cmn`), consistent with the
review's evaluation guidance not to treat expected-language script as
corruption by default. However, `MIX-11`'s 44-CJK-character flag
undersells the actual defect: a substantial fabricated Greek passage
appears with no Greek source audio anywhere in the sample chain — a
genuine hallucination the detector's "CJK count" metric does not surface at
all. Large-v3's "0/12 corrupted" headline conceals `MIX-09`'s complete
transcript dropout (an empty-string false-clean). Neither model's mixed-set
"clean" tally is a reliable single number; both undercount real defects for
the same structural reason (the detector only watches two narrow signals).

## Q. Benchmark Gate Conclusion

```text
BENCHMARK GATE NOT VERIFIED — HPO MODEL DECISION STILL REQUIRED
```

The expanded evidence is directionally useful (36-sample corpus,
deterministic construction, real reference transcriptions, correct
arithmetic, honest disclosure of turbo's CJK-on-mixed limitation) but its
central quality claim — "0/15 real Tamil corruption for both models,
original corruption isolated" — is factually contradicted by the raw data
it is built from. This is not a request for a hard WER threshold (none is
invented here); it is that the specific claim used to justify the gate
decision does not hold up against independent inspection, on the exact
sample (`ta_in_1`) and exact failure mode (cross-script real-Tamil
hallucination) the prior escalation was raised over. The HPO needs either
(a) a corrected script-integrity/quality analysis that also checks for
non-CJK foreign scripts and empty-transcript failures, re-run over the
existing 36-sample corpus (no new inference required — the raw transcripts
are already committed), or (b) a decision made with explicit awareness that
turbo shows real, reproducing cross-script hallucination on Tamil audio at
roughly a 1-in-5 rate in this sample, weighed against its 1.67x speed
advantage. Either path is a legitimate HPO call; what is not acceptable is
proceeding on the current document's claim that the corruption "does not
repeat," which is false.

## R. Benchmark Tooling / Reproducibility

All 12 scripts listed as durable tooling are tracked and present:
`download_fleurs.py`, `build_manifest.py`, `create_mixed_samples.py`,
`run_benchmark.py`, `expand_tamil.py`, `expand_mixed.py`,
`resume_benchmark.py`, `analyze_results.py`, `backfill_refs.py`,
`audit_evidence.py`, `benchmark_gate.py`, `run_expanded_benchmark.py`
(`git ls-files scripts/benchmark/` confirms all present; none are stray).
The temporary scripts Cycle 4 flagged as untracked (`check_partial.py`,
`init_benchmark.py`, `retry_tamil.py`) are confirmed removed. Sample
selection is reconstructable end-to-end from `download_fleurs.py` →
`build_manifest.py` (deterministic indices) → `expand_tamil.py`/
`expand_mixed.py` (deterministic expansion) → `run_expanded_benchmark.py`
(the script whose `analyze_script_integrity()` function, lines 30–70, is
the source of the detector-gap described throughout this review — it only
implements Tamil-ratio/CJK/Latin-only checks, no other script ranges, no
reference-based comparison). `benchmark_gate.py` and
`run_expanded_benchmark.py` both call `faster_whisper.WhisperModel`
directly, bypassing `worker/transcription.py` entirely — meaning the
benchmark evaluates only the underlying model's raw output, never exercises
`_detect_segment_language()` or the production per-segment pipeline. This
is an appropriate scope for a model-selection benchmark but means it
provides no timing evidence for H5's per-segment-detection overhead, a gap
already disclosed as a known limitation in `BENCHMARK-GATE-EVIDENCE.md`
("Per-segment language identification overhead not measured (LOW, INFO)")
— correctly disclosed, not silently dropped.

## S. Governance / Decision Queue

`grep '^Status:' DECISION_QUEUE.md` returns only `DECIDED` (x6) and one
`OPEN — expanded evidence produced; awaiting independent post-escalation
verification` — no `RESOLVED` token anywhere. S1 (Cycle 3's vocabulary
finding) remains fixed and has not regressed.
`DECISION-P3-ESCALATION-001` = DECIDED (HPO Exception, Section D).
`DECISION-P3-BENCHMARK-GATE-001` = OPEN, correctly so — per Section Q, this
review does **not** confirm the gate, so it is **not** technically eligible
for HPO closure in the "evidence-confirmed" sense; it is eligible for HPO
*review and decision* (the HPO may still choose to accept turbo with the
known limitation now made explicit, which is the HPO's call, not this
reviewer's) but not for closure on the basis that independent verification
confirmed "15/15 clean," because it did not.

## T. CURRENT_STATE

`CURRENT_STATE.md` (Last Updated 2026-09-17, header notwithstanding
same-day commits) accurately states: Phase 3 Batch 1 = BLOCKED,
P3-001/002/003 = BLOCKED, Cycle 1–4 = CHANGES_REQUESTED history preserved,
H5 = FIXED, H6 = "expanded evidence produced; awaiting independent
verification," Batch 1 = NOT VERIFIED, Batch 2/3 = NOT AUTHORIZED. This
matches actual repository state. One accuracy note for the HPO going
forward: H6 is not simply "awaiting verification" in the sense of a
complete-and-correct package awaiting a rubber stamp — independent
verification found a substantive defect in the evidence itself (Section Q),
so "H6 = expanded evidence produced, quality-analysis defect found in
independent review, requires correction or HPO acceptance-with-limitations"
would be the more precise state once this review lands.

## U. PHP Verification

Independently executed:

```
php artisan test --compact
{"tool":"pest","result":"passed","tests":291,"passed":290,"assertions":866,
 "duration_ms":21249,"skipped":1,"warnings":2}

vendor/bin/pint --test --format agent
{"tool":"pint","result":"passed"}

vendor/bin/phpstan analyse --no-progress
{"tool":"phpstan","result":"passed","errors":0}
```

Exactly matches the reported baseline (291 total / 290 passed / 1 skipped /
866 assertions, Pint clean, PHPStan 0 errors).

## V. Python Verification

Independently executed from `worker/`:

```
worker/.venv/Scripts/python.exe -m pytest -q
31 passed in 0.69s
```

Matches reported baseline. Test quality inspected directly (Section I), not
merely counted — mocks match the real 3-tuple `detect_language()` return
shape, and the required H5 semantics (independent per-segment detection,
non-forcing hint, `und` fallback, exception handling, empty-segment
short-circuit) are each individually asserted, not just smoke-tested.

## W. Security / Filesystem / FFmpeg

`worker/ffmpeg.py` uses `tempfile.mkstemp()` (line 26) instead of the
previously-flagged racy `mktemp`, confirmed via `test_uses_mkstemp_not_mktemp`
passing (part of the 31). FFmpeg invocation remains an argument array (no
shell interpolation), has a timeout, and cleans up its output file on
failure/timeout. No changes to `worker/media.py`'s path-traversal/absolute-path
rejection since Cycle 4's independent verification; no regression (Section F).

## X. Batch Boundary

Confirmed via `head` on each task file's `## Status` section:

```
P3-004 = BACKLOG
P3-005 = BACKLOG
P3-006 = BACKLOG
P3-007 = BACKLOG
P3-008 = BACKLOG
```

No transcript/segment persistence, Redis orchestration, Horizon, or
translation code exists anywhere in the diff range inspected (Section C).

## Y. Phase 2 Preservation

`CURRENT_STATE.md` continues to state, unchanged: Phase 2 =
COMPLETE_WITH_DEFERRED_DEBT; P2-004A / P2-004A1 = BLOCKED; P2-004A2 = DONE;
P2-007 = DONE; Option D in force. No Phase 1/2 application file appears in
`git diff --stat 022a75b..HEAD` (Section C). No regression.

## Z. Findings

**BLOCKER-1 — Benchmark evidence's Tamil "0/15 corrupted" claim is
factually false; original Cycle-3 corruption reproduced.**
File: `BENCHMARK-GATE-EVIDENCE.md` (lines ~118, 141–142, 191–197, 201–210);
raw data: `benchmark-media/results/expanded_benchmark_turbo.json`
(`ta_in_1`, `ta_in_11`, `ta_in_13`).
Evidence: independent Unicode-range scan of the raw transcripts (Section M)
finds Hebrew/Cyrillic/Korean/Arabic contamination in 3 of 15 real Tamil
turbo transcripts, all marked `"clean": true` by
`analyze_script_integrity()` (`scripts/benchmark/run_expanded_benchmark.py:30-70`),
because that function only checks CJK-character count and Tamil-script
ratio. `ta_in_1`'s output diverges from its own recorded FLEURS reference
transcription in exactly the region the gibberish appears, confirming
hallucination rather than a mere heuristic false-positive.
Impact: the gate decision's stated rationale ("original corruption was
isolated, does not repeat") is not true; the gate cannot be closed on this
evidence as currently characterized.
Required HPO/action: correct the script-integrity analysis (check
additional Unicode ranges; flag empty transcripts as failures, not
"clean") and re-summarize `BENCHMARK-GATE-EVIDENCE.md` from the
already-committed raw JSON (no new inference required), or have the HPO
make the turbo/large-v3 decision with explicit, accurate awareness of the
~20% real-Tamil cross-script hallucination rate found.

**HIGH-1 — Large-v3's mixed-corpus "0/12 corrupted" and Tamil "15/15 clean"
claims each conceal one undisclosed genuine defect.**
File: `BENCHMARK-GATE-EVIDENCE.md` (lines 163–166, 182–183); raw data:
`expanded_benchmark_large-v3.json` (`ta_in_15`, `MIX-09`).
Evidence: `ta_in_15` output is 91 Gurmukhi-script characters (not Tamil, not
Malayalam), mischaracterized as a "language-detection quirk, not script
corruption." `MIX-09` produced a fully empty transcript for a 21.84s sample,
passed "clean" by the detector's empty-string short-circuit.
Impact: large-v3 is not the unambiguous "cleaner" baseline the document
implies; it has its own real, undisclosed failure modes, of a different
character than turbo's (omission/wrong-script vs. fabricated
multi-script-gibberish).
Required HPO/action: correct the per-sample tables and the "large-v3
cleaner" framing in `BENCHMARK-GATE-EVIDENCE.md`'s Model Gate Decision
rationale.

**MEDIUM-1 — No reference-based (WER/exact-match) comparison was run
despite reference transcriptions being available for all 15 Tamil
samples.**
File: `scripts/benchmark/run_expanded_benchmark.py`,
`scripts/benchmark/audit_evidence.py`; `benchmark-media/manifest.json`
(`reference_transcription` populated for all 15 Tamil entries).
Evidence: quality assessment relies solely on the Unicode-heuristic
`analyze_script_integrity()`; no script computes similarity/WER against the
already-present reference text.
Impact: a materially more reliable quality signal was available and unused;
this is why BLOCKER-1/HIGH-1 went undetected before this review.
Required HPO/action: not blocking on its own, but recommended before this
data is relied on for a final HPO decision.

**LOW-1 (carried forward, unchanged since Cycle 4) — M3-residual:
`HttpTranscriptionProviderTest.php` still has no malformed-2xx-response
case; the timeout test does not assert the configured value was applied.**
Not part of the HPO-authorized narrow H6 scope; no regression; still open.

**INFO-1 — Escalation decision recorded by the same author/session chain as
the work it authorizes (Section D).** Consistent with this repository's
established decision-recording convention throughout its history; not a
new anomaly, not blocking.

**INFO-2 — Segment-LID overhead remains unmeasured (Section R).** Already
disclosed as a known limitation in `BENCHMARK-GATE-EVIDENCE.md`; not
silently dropped; not blocking.

## AA. Batch 1 Release Eligibility

```text
BATCH 1 REMAINS BLOCKED
```

## AB. Benchmark Decision Eligibility

```text
TURBO MODEL DECISION NOT READY FOR CLOSURE
```

## AC. Batch 2

Confirmed unauthorized. P3-004 through P3-008 remain BACKLOG (Section X).
No Batch 2 work of any kind was found, implemented, or recommended by this
review.

## AD. Files Changed

This review added exactly one file:
`reviews/PHASE3-BATCH1-post-escalation-independent-review.md` (this file).

## AE. Explicit Non-Actions

- No implementation code modified.
- No tests modified.
- No migrations created.
- No task-state changes made by this reviewer (task files already read as
  BLOCKED; this reviewer did not alter them).
- No Batch 2 implementation.
- No Redis orchestration, Horizon, or translation work performed.
- No task marked DONE by this reviewer.
- No Batch 2 authorization granted or implied.
- No autonomous Cycle 5 initiated or recommended; findings are returned to
  the Human Product Owner per the post-escalation review's terms.
