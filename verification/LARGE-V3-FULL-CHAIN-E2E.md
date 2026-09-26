# STEP C2 — Canonical `large-v3` Full-Chain Verification

Date: 2026-09-25
Task: STEP C2 (targeted continuation of Step C; resolves the canonical-model
portion of TD-001 only)
Authority: `AGENTS.md`; `DECISION-P3-BENCHMARK-GATE-001` (DECIDED — `large-v3`
selected as initial canonical Phase 3 model); `docs/TECHNICAL_DEBT_REGISTER.md`
(TD-001); `docs/PRODUCTION_READINESS_GATE.md` (G-12).
Status: PASS (canonical-model requirement) — overall TD-001 REMAINS OPEN
pending deployed-stack gates (see §12).

Step C evidence (`verification/REAL-MODEL-FULL-CHAIN-E2E.md`) is preserved
unchanged as historical evidence. This artifact has its own traceable lineage;
no Step C `base` artifact is combined with the C2 chain.

## 1. Baseline

- HEAD: `d8a7e01ffe95b8cf5d2c857214ddee65a649c28d` (unchanged from Step C)
- Working tree: Step A/B doc modifications only (`BLOCKERS.md`,
  `CURRENT_STATE.md`, `plan.md`, `docs/`, Step C artifact). No application,
  test, config, migration, or worker-code change for this run.
- Verification user reused: `stepc@example.test` (id 3). No seed/demo data.

## 2. Canonical model authority (verified, not assumed)

- `DECISION-P3-BENCHMARK-GATE-001` = DECIDED: "`large-v3` selected as initial
  canonical Phase 3 model" (`DECISION_QUEUE.md:1773-1777`; verified in
  `reviews/PHASE3-BATCH1-final-hpo-decision-verification.md:91-92,397,418`).
- `worker/config.py:10-14` (`MODEL_NAME` default `large-v3`);
  `config/transcription.php:21` (canonical model config `large-v3`).
- Required production model for this task: faster-whisper `large-v3`
  (`Systran/faster-whisper-large-v3`), self-hosted, CPU/int8 per P3-008
  precedent.

## 3. Model availability and acquisition

- At C2 start, `large-v3` weights were absent: only partial `.incomplete`
  blobs (610/730/959/1087 MB) from Step C's throttled attempts existed.
- Acquisition: `snapshot_download("Systran/faster-whisper-large-v3")` for
  metadata/small files plus direct resumed transfer of `model.bin` from the
  mirror endpoint into the hub blob store.
- Completion proof: `model.bin` sha256 =
  `69f74147e3334731bc3a76048724833325d2ec74642fb52620eda87352e3d4f1`
  (self-verifying: hash equals the hub blob name); snapshot finalized at
  `snapshots/edaa852ec7e145841d8ffdb056a99866b5f0a478` (matches HF main HEAD
  `edaa852e…`), 7/7 files, `model.bin` 2944 MB present with tokenizer/config.
- Stale partial `.incomplete` files were left in place (harmless; not deleted
  per instruction).
- No substitution: no `base`/`small`/`medium`/mock/fake anywhere in this run.

## 4. Runtime identity proof (configured label vs actual model)

Four independent evidences that inference truly executed `large-v3`:

1. Worker process env `RTFTT_WHISPER_MODEL=large-v3` (config default; recorded
   in the worker launch record); faster-whisper resolves the name
   `large-v3` → `Systran/faster-whisper-large-v3` deterministically — the
   `base` cache present on disk is never addressable under that name.
2. Filesystem: `models--Systran--faster-whisper-large-v3/.../model.bin`
   LastAccessTime 03:39:53 local = 5 s after the C2 job started
   (03:39:48 local); the `base` weights were last touched 02:23 (Step C run)
   and untouched during C2.
3. Worker log: `faster_whisper:Processing audio with duration 00:15.542`,
   `Detected language 'en' (p=0.99)`, `rtftt-worker:transcription_complete`.
4. Behavioral + timing signature: job time 121 s (vs 11 s for `base` on the
   same media) and materially better Malay-segment quality (see §8) — both
   consistent with the large model, inconsistent with a silent fallback.
5. Persisted row `model = large-v3` is therefore truthful in this run: the
   Step C TD-009 discrepancy (label said `large-v3`, runtime was `base`) is
   naturally eliminated for C2 lineage. TD-009 itself (config-derived identity
   as a design) is unchanged and remains ACCEPTED.

## 5. Test media

Same sample re-uploaded for comparability: `stepc-sample.mp3`, audio/mpeg,
15.542 s, 249,982 bytes, predominantly English with one Malay sentence,
timestamps expected. Same bytes as Step C (checksum `d34b64fd…` identical).

## 6. Stage results (C2 lineage only)

| Stage | Result | Evidence |
|---|---|---|
| A — Upload | PASS | Real Chromium `/media/upload` → HTTP 200 → MediaFile id 11, owner `stepc@example.test`, status `uploaded`, opaque path, checksum match, physical file 249,982 bytes. (Duplicate media 6,7,8,10 exist from script retries — duplicates allowed by contract; C2 uses 11.) |
| B — Ingestion | PASS | Validation/checksum/promotion executed. NULL `duration/codec` metadata observed again (probe service still unwired) — recorded, does not block execution or alter TD-001. |
| C — `large-v3` transcription | PASS (primary gate) | Transcription 7 Draft → orchestrator → attempt 2 → live Redis `transcription` queue (IDs-only payload) → `queue:work` consumed → real inference → `completed`. No fallback: identity proof §4. 3 segments persisted (0–5.68 s, 6.88–9.52 s, 10.64–14.52 s), language `en`. |
| D — Persistence | PASS | Transcript + segments persisted against media 11 lineage; non-empty; materially matches source. |
| E — Translation | PASS | POST `/transcriptions/7/translations` (`ms`) → Translation 6 queued → real NLLB worker → `completed`, 3 aligned segments. Downstream compatibility with the new `large-v3` transcript established (translation consumed C2 text, not Step C text). Concise verification permitted: translation engine path already proven in Step C/P5-008; this run proves compatibility. |
| F — Workspace | PASS | Real Chromium: `/transcriptions/7` shows the `large-v3` transcript + timestamps; `/transcriptions/7/translations` shows the Malay translation. `showRenameModal` error reproduced (TD-013). |
| G — Export | PASS | TXT transcript export + TXT translation-6 export downloaded via product paths; contents verified against the real `large-v3`-derived text. |

## 7. Lineage (C2 only)

- MediaFile 11 (`34197af4-b0fb-4a0f-b05e-178472315945`)
- Transcription 7 (media_file_id 11)
- ProcessingJob 2 (`1dcd09e2-dfb1-4844-ac10-436eab3e8ec7`, `transcribe`, `completed`, 121 s)
- Translation 6 (transcription 7, target `ms`, `completed`, 3 segments)
- Exports: transcript TXT + translation-6 TXT (contents verified)

## 8. Step C vs Step C2 comparison

| Attribute | Step C | Step C2 |
|---|---|---|
| Actual model | faster-whisper `base` | faster-whisper `large-v3` (proven §4) |
| Media | `stepc-sample.mp3` (15.542 s, 249,982 B) | same file re-uploaded (same bytes) |
| Transcription result | 3 segs; Malay tail mangled ("Salamat Pajai… came as I you erred Kami") | 3 segs; Malay tail materially better ("Salamat pagi, salamat datang ke meziu erit kami") |
| Processing time | 11 s | 121 s (diagnostic only, not a benchmark) |
| Segment count | 3 | 3 |
| Downstream compatibility | translation 5 from base text | translation 6 from large-v3 text; workspace + exports verified |

## 9. TD-001 implication

- Canonical Model Requirement: PASS (actual `large-v3` inference proven, real
  transcript generated, same-run lineage established, downstream chain
  succeeded, zero fakes/mocks/fixtures/seeds).
- Overall TD-001: REMAINS OPEN — the debt explicitly includes deployed-stack
  gates (dev-stack PHP limits sufficed here; production topology,
  supervision, auth posture, data-store, backup, and capacity gates from
  G-01..G-11 were not executed).

## 10. G-12 implication

G-12 requires the full real-model chain with the canonical model: the
chain-with-canonical-model portion is now proven evidence. G-12 as a whole is
PARTIAL: entry criteria unmet (Phase 6 not CLOSED, D7-* undecided, Phase 7 not
authorized) and G-01..G-11 conditions unexecuted. This artifact is prerequisite
evidence for G-12, not satisfaction of it.

## 11. Deviations and failures

1. Duplicate media rows (6,7,8,10) from upload-script retries — allowed by the
   duplicate-tolerant contract; C2 lineage uses 11 exclusively.
2. NULL upload-path media metadata observed again (recorded, non-blocking).
3. PHP dev server needed one restart mid-run (stateless; no data impact).
4. `showRenameModal` console error reproduced (TD-013, non-blocking).
5. No failures in the C2 chain itself; no retries needed downstream.

## 12. Commands and actions (reproducibility)

Same harness as Step C (`verification/REAL-MODEL-FULL-CHAIN-E2E.md` §12) with:
`RTFTT_WHISPER_MODEL=large-v3` (worker default) + `HF_HUB_OFFLINE=1`;
model acquisition per §3; scratch Playwright scripts (`stepc2-*.mjs`,
removed after the run); request via `stepc-request.php 11`;
`queue:work redis` for both queues; translation requested and exported via
product HTTP routes; workspace asserted in real Chromium.

## 13. Verdict

**PASS (canonical-model requirement).** Overall TD-001 stays OPEN pending
deployed-stack gates. G-12 PARTIAL (prerequisite evidence supplied).
