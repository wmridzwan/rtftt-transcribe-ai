# STEP C — Full-Chain Real-Model Production E2E Verification

Date: 2026-09-25
Task: STEP C (TD-001 resolution attempt)
Authority: `AGENTS.md`; `docs/TECHNICAL_DEBT_REGISTER.md` (TD-001 HIGH/YES);
`docs/PRODUCTION_READINESS_GATE.md` (G-12).
Status: PARTIAL PASS — TD-001 REMAINS OPEN (canonical-model inference outstanding).

## 1. Baseline

- HEAD: `d8a7e01ffe95b8cf5d2c857214ddee65a649c28d`
- Working tree at run start: `M BLOCKERS.md, CURRENT_STATE.md, plan.md`
  (Step A doc reconciliation only); `?? docs/` (Step A/B deliverables). No
  application, test, config, or migration change was made for this run. Two
  P6-005 migrations applied via `artisan migrate` (already-committed schema).
- Verification user: `stepc@example.test` (id 3, admin), created for this run
  in the local dev database. No seed/demo data used.

## 2. Environment

| Component | Detail |
|---|---|
| PHP | 8.4.24 NTS; dev server `php -S 127.0.0.1:8123 -t public` |
| PHP upload limits (this machine) | `upload_max_filesize=512M`, `post_max_size=520M` |
| Laravel | app server + SQLite dev DB; `QUEUE_CONNECTION=database` default |
| Redis | Microsoft Archive portable 3.0.504, `127.0.0.1:6379`, no persistence (gate use) |
| Worker | uvicorn `worker.main:app` on `127.0.0.1:8000`, venv packages faster-whisper 1.2.1 / torch 2.14.0+cpu / transformers 5.17.0 / sentencepiece 0.2.2 |
| Shared media root | `<repo>/storage/app/private` (matches `local` disk) |
| FFmpeg / FFprobe | 9.0.1 Gyan full build (media prep + sample creation) |
| Browser | Playwright 1.63.0, Chromium headless (real) |
| Queues used | `transcription` and `translation` on the `redis` connection |

## 3. Media sample (real file, normal upload path)

- File: `stepc-sample.mp3` (from SAPI-spoken WAV → FFmpeg MP3, 128kbit/s)
- Type: audio/mpeg, MP3; duration 15.542 s; size 249,982 bytes
- Content (spoken): English sentences ("Hello. This is a full chain verification
  recording … The quick brown fox jumps over the lazy dog.") + one Malay
  sentence ("Selamat pagi, selamat datang ke mesyuarat kami.")
- Source language: predominantly `en` with an `ms` tail → translation to `ms`
  applicable.
- Timestamps expected: yes (segmented speech with pauses).

## 4. Provider / model identity (no-fake binding)

- Laravel binding: `TranscriptionProvider::class` → `HttpTranscriptionProvider`
  (`app/Providers/TranscriptionServiceProvider.php:13-18`). No fake/mock
  binding exists in `app/`.
- `DemoTranscriptionController` (hard-coded prototype text) was NOT used.
- Requested transcription engine: self-hosted faster-whisper, canonical
  `large-v3`.
- DEVIATION (recorded, affects verdict): `large-v3` weights (~3 GB) could not
  be provisioned in-session — HF CDN throughput from this machine measured
  ~130–460 KB/s with repeated stalls (partial blobs retained in
  `C:\rtftt-hf-cache\models--Systran--faster-whisper-large-v3\blobs`, resume
  capable). The run used faster-whisper **`base`** (same engine family and
  code path, `Systran/faster-whisper-base`, fully downloaded, sha-verified by
  the hub client) via `RTFTT_WHISPER_MODEL=base` on the worker only.
- Consequence: the persisted transcription row records `model = large-v3`
  (Laravel `config('transcription.model')` default) while actual inference ran
  on `base` — a live instance of the accepted TD-009 INFO debt
  (config-derived model identity). No data was misrepresented beyond this
  known, documented gap.

## 5. Stage results

| Stage | Result | Evidence |
|---|---|---|
| A — Upload | PASS | Real Chromium drove `/media/upload` (file chooser + submit); HTTP 200 JSON; app navigated to `/media/9`. MediaFile id 9, owner `stepc@example.test`, status `uploaded`, opaque path, checksum `d34b64fd…`, physical file present at 249,982 bytes (= source size). |
| B — Ingestion | PASS with observation | Same-attempt identity, validation, checksum, private promotion all executed in `MediaUploadController`/`MediaIngestionService`. OBSERVATION (not blocking): `duration_seconds`/`audio_codec`/`sample_rate`/`channels` are NULL after upload — `MediaMetadataProbeService::probeAndUpdate()` exists but nothing in `app/` calls it. Candidate HPO follow-up; transcription unaffected (worker prepares audio independently). No zero-byte/corrupt artifact. |
| C — Real transcription | PASS (model deviation per §4) | Transcription 6 created Draft → `TranscriptionOrchestrator::request()` → attempt 1 → `ProcessTranscription` on live Redis (`transcription` queue; payload = IDs only, no media path/binary). `queue:work redis --once` consumed in 11 s. Worker log: `faster_whisper:Processing audio with duration 00:15.542`, `Detected language 'en' (p=1.00)`, `transcription_complete`. Status → `completed`; no silent retry bypass (single attempt row). |
| D — Persistence | PASS | 3 segments persisted with real timestamps (0–5.76 s, 6.72–9.44 s, 9.44–14.44 s), per-segment language `en`, non-empty text materially matching the source; media/transcription/attempt relationships correct. |
| E — Translation | PASS (after honest failure + recovery) | POST `/transcriptions/6/translations` (`target_language=ms`, product route) → Translation 5 queued. First attempt orphaned when the PHP queue worker was killed mid-inference (harness session limit) → row stuck `translating` → `translation:recover-stale-attempts` recovered it to `failed` (retryable) exactly as designed → POST `/translations/5/retry` → re-queued → NLLB cache blob completed from the verified snapshot (sha256 matched P5-008 record) → worker `translation_complete` → Translation 5 `completed` with 3 aligned `ms` segments. |
| F — Workspace | PASS | Real Chromium: `/transcriptions/6` renders the real English transcript + timestamps; `/transcriptions/6/translations` renders the real Malay translation. `showRenameModal is not defined` console error reproduced (TD-013 live confirmation, non-blocking). |
| G — Export | PASS | TXT export of the transcript + TXT export of translation 5 downloaded via product paths; contents verified to match the real generated text (not fixtures). |

## 6. Lineage (one chain, no mixing)

- MediaFile: id 9, uuid `9c42ffe0-6528-46c7-97b3-101bbada8b3c`
- Transcription: id 6 (media_file_id 9)
- ProcessingJob: id 1, uuid `37083671-90ce-4c9e-8369-6abfe8a3e0e3`, stage `transcribe`, `completed`, 11 s
- Translation: id 5, target `ms`, `completed`, 3 segments
- Exports: transcript TXT + translation-5 TXT (contents verified)

## 7. No-fake verification

- Provider binding is the real HTTP client (cited above); no fake/mock
  provider, fixture payload, seeded transcript, or hard-coded result anywhere
  in the executed path (repo-wide `fake-whisper` term absent; verified).
- Transcription 6 was created empty (`Draft`) and filled only by worker
  inference; translation 5 was created empty (`queued`) and filled only by
  NLLB inference.
- Redis payloads contain small identifiers only (captured verbatim during the
  run).
- The `base`-for-`large-v3` substitution is the sole engine deviation and is
  declared in §4 (it exercises the identical provider/worker code path with a
  real model, but is not the canonical production model).

## 8. Quality sanity (no benchmark claimed)

- English segments near-verbatim vs source audio. Malay source tail mangled by
  the `base` model ("Salamat Pajai…") — expected from a small model on SAPI
  speech, and precisely why the canonical-model run remains outstanding.
- Malay translation recognizably related to the source ("Rubah coklat cepat
  melompat atas anjing malas.").
- Timestamps broadly correspond to the 15.5 s media (segments span 0–14.44 s).

## 9. Failures, retries, deviations (nothing hidden)

1. Model-download stalls: `large-v3` unprovisonable in-session (network).
   → Remediation: retry provisioning with better connectivity; partial blobs
   retained and resume-capable. No product task implicated.
2. First translation attempt orphaned by killed PHP worker → recovered by the
   designed stale-recovery command → retried via product route → completed.
   Proves failure handling; not a product defect.
3. `base`-for-`large-v3` substitution (§4) + config-derived model label
   (TD-009 live instance).
4. Upload-path media metadata NULL (Stage B observation) — candidate HPO
   follow-up; does not block this chain.
5. `showRenameModal` console error reproduced (TD-013).
6. PHP dev server needed one restart mid-run (connection refused); stateless
   dev server, no data impact.

## 10. Interaction with other debt

- TD-002: not encountered (this machine's PHP limits 512M/520M); production
  stack still unproven.
- TD-003: real Redis queues consumed successfully; supervision/dead-letter
  still absent (dev run, manually supervised).
- TD-004: loopback Redis, no auth — acceptable for this gate; posture decision
  still open.
- TD-005: no flake encountered in this run's browser steps; flake debt stands.
- TD-007: no cleanup executed (Option D respected); staging accumulation from
  this run (one attempt dir) left in place.
- No debt item was remediated except the environment-level NLLB blob
  completion (byte-identical to the verified snapshot; not an app change).

## 11. Verdict

**PARTIAL PASS — TD-001 REMAINS OPEN.**

The integrated product path works end to end with a real engine and zero
fakes/doubles/seeds. The outstanding required production stage is the
identical run (or a dedicated gate run) with the canonical `large-v3` model
plus the deployed-stack conditions in `docs/PRODUCTION_READINESS_GATE.md`
(G-01..G-13). TD-001 must not be closed on this evidence.

## 12. Commands and actions used (reproducibility)

- Media synthesis: SAPI `SpeechSynthesizer` → WAV; FFmpeg → 128k MP3.
- `php artisan migrate --force`; verification user + all DB reads via
  throwaway `Temp\opencode\stepc-*.php` bootstrap scripts (outside repo).
- Servers: `php -S 127.0.0.1:8123 -t public`; portable Redis 3.0.504
  (`--save '' --appendonly no`); `uvicorn worker.main:app 127.0.0.1:8000`
  with `RTFTT_SHARED_MEDIA_ROOT=<repo>/storage/app/private`,
  `RTFTT_WHISPER_MODEL=base`, `cpu`/`int8`, worker bearer token.
- Browser: Playwright Chromium scripts for login/upload/translation-request/
  retry/workspace/exports (scratch copies removed after the run; steps above).
- Dispatch: `stepc-request.php` (Transcription create + orchestrator request,
  `RTFTT_TRANSCRIPTION_QUEUE_CONNECTION=redis`).
- Workers: `queue:work redis --queue=transcription --once`;
  `queue:work redis --queue=translation --once`; manual
  `translation:recover-stale-attempts` for the orphaned attempt.
- Model provisioning: `snapshot_download` (base via `HF_ENDPOINT` mirror);
  NLLB blob completed by copying the sha-verified snapshot file into the blob
  store (hash `c266c2cf…eefcc42` matches P5-008 record).
