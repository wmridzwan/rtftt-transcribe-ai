# P5-008 â€” Phase 5 Integration Verification â€” Evidence / Blocker Record

Date: 2026-09-22
Task: P5-008 (promoted `BACKLOG â†’ READY`; `DECISION-P5-008-AUTHORIZATION-001`)
Status: **BLOCKED at the real-model prerequisite.** The real self-hosted
provider/model gate (ADR-022 D5-09) was **not** executed and is **not** claimed.

## Intended runtime configuration (from the frozen Phase 5 contract)

| Item | Value | Source |
|---|---|---|
| Provider / model identity | self-hosted; `RTFTT_TRANSLATION_MODEL` default `facebook/nllb-200-distilled-600M` | `worker/translation.py`, `config/translation.php` |
| Worker runtime deps | `transformers>=4.40`, `torch>=2.2`, `sentencepiece>=0.2` (declared) | `worker/requirements.txt` |
| Queue connection | `translation.queue_connection` = effective default (`database` locally; `redis` canonically) | `config/translation.php`, `TranslationQueueConfig` |
| Queue name | `translation` | `config/translation.php` |
| Job timeout | 330 s | `translation.job_timeout_seconds` |
| Provider timeout | 300 s | `translation.timeout_seconds` |
| Required `retry_after` | 420 s (database/redis defaults) | `translation.retry_after_seconds`, `config/queue.php` |
| Stale recovery | `translation:recover-stale-attempts`, every minute, token-fenced | `routes/console.php` |

No secrets or tokens are recorded.

## Blocker evidence (reproduced 2026-09-22)

Worker virtualenv (`worker/.venv`, Python 3.13.14) â€” required translation runtime
absent:

```
import transformers -> ModuleNotFoundError: No module named 'transformers'
import torch        -> ModuleNotFoundError: No module named 'torch'
import sentencepiece-> ModuleNotFoundError: No module named 'sentencepiece'
```

Installed relevant packages (pip list): `ctranslate2 4.8.2`, `faster-whisper
1.2.1`, `onnxruntime 1.30.0`, `torchcodec 0.16.0` â€” **no `torch`,
`transformers`, or `sentencepiece`**.

Hugging Face cache: only faster-whisper ASR models
(`faster-whisper-large-v3`, `-large-v3-turbo`, `-small`) and
`datasets--google--fleurs`. **No translation model is cached** (no NLLB / OPUS-MT).

Live Redis: unavailable.

```
php redis-check.php -> redis unavailable: A connection attempt failed because the
connected party did not properly respond ... (127.0.0.1:6379)
```

Effective queue config (non-test boot): `queue.default=database`,
`translation.queue=translation`, `translation.queue_connection=NULL`,
`job_timeout=330`, `provider_timeout=300`, `retry_after_req=420`, `db_retry=420`.

## Why the gate cannot proceed

The P5-008 contract requires the **real** self-hosted provider/model path plus a
live queue; mocks and deterministic worker doubles "must not substitute for the
required real-model integration evidence" (HPO authorization Â§3; ADR-022 D5-09;
Phase 3 P3-008 precedent). Provisioning the real stack requires, at minimum:

1. installing a multi-GB ML runtime (`torch`, `transformers`, `sentencepiece`)
   into `worker/.venv`;
2. downloading the canonical translation model (NLLB-200-distilled-600M, ~2.4 GB)
   or an approved smaller self-hosted model; and
3. a running Redis server (the local machine has the `phpredis` extension but no
   server; Redis was also required by the Phase 3 gate).

These are material environment/provisioning decisions with significant
resource/time implications and are outside a "smallest justified corrective".
They were therefore not performed unilaterally.

## Lower-level evidence that remains valid

The frozen Phase 5 implementation is covered by contract-level evidence that is
independent of the real model: translation suite 191 passed / 724 assertions;
full suite 624/623; two-process claim/retry/request races 3/3 Ã— 3 runs; Pint
clean; PHPStan 0; worker pytest 42 passed; `schedule:list` confirmed. These do
not satisfy P5-008 and are not represented as doing so.

## Requested HPO decision

See `BLOCKERS.md` B-004 and `PHASE5-CLOSURE-REPORT.md` for the options.
P5-008 remains BLOCKED; Phase 5 is not closed.
---

# P5-008 — Real Gate Execution (provisioning resolved)

Date: 2026-09-22
Status: **executed against the real self-hosted stack**; task moved to
IMPLEMENTED_PENDING_REVIEW (independent review pending). The BLOCKED section
above is preserved as history; B-004 is resolved.

## Provisioned runtime (exact versions; no secrets recorded)

| Item | Value |
|---|---|
| Python | 3.13.14 (`worker/.venv`) |
| torch | 2.14.0+cpu |
| transformers | 5.17.0 |
| sentencepiece | 0.2.2 |
| Model | `facebook/nllb-200-distilled-600M` (canonical; unchanged) |
| HF cache | relocated to `D:\hf-cache` (C: was exhausted; non-destructive move) |
| Redis | Redis for Windows 5.0.14.1 on `127.0.0.1:6379` |
| Queue | `translation` queue on the `redis` connection (`RTFTT_TRANSLATION_QUEUE_CONNECTION=redis`) |
| Worker URL | `http://127.0.0.1:8000` (uvicorn `worker.main:app`) |
| Worker auth | shared bearer token (value not recorded) |
| Provider timeout | 300 s |
| Job timeout | 330 s |
| Required retry_after | 420 s |
| Stale threshold | 360 s |

## Environment verification

1. Worker venv imports `torch`/`transformers`/`sentencepiece` — OK.
2. Canonical model loads and infers — OK (see below).
3. Redis reachable (`redis-cli PING` ? PONG; phpredis connect OK).
4. Queue worker consumes `translation` on `redis` (`queue:work redis
   --queue=translation --timeout=330 --tries=1`) — OK.
5. Laravel reaches the worker (`/health` ? `{"status":"ok"}`) — OK.
6. Worker auth: authenticated `/translate` ? 200; unauthenticated ? 401 — OK.
7. Timeouts: provider 300 < job 330 < retry_after 420; stale 360 — OK.

## Real inference evidence

- Direct model check: `ms` ? "Selamat pagi, selamat datang ke mesyuarat.";
  `zh` ? "?????."
- Authenticated worker HTTP `/translate` (en?ms): `{"target_language":"ms",
  "provider":"self-hosted","model":"facebook/nllb-200-distilled-600M",
  "text":"Selamat pagi, selamat datang ke mesyuarat.", ...}`.

## Real end-to-end queued path

`Laravel ? Redis queue ? translation job ? authenticated Python worker ? real
NLLB model ? translated result ? atomic persistence`:

- en?ms: `completed`; full_text "Selamat pagi, selamat datang ke mesyuarat.
  Jumlah jualan meningkat."; segments aligned; source_unchanged = true.

## Multilingual / code-switch gate (one transcript: ms,en,zh,ta,und)

All four contract targets completed via the real queued path:

| Target | status | aligned | source-language preserved |
|---|---|---|---|
| ms | completed | true | ms,en,zh,ta,und |
| en | completed | true | ms,en,zh,ta,und |
| zh | completed | true | ms,en,zh,ta,und |
| ta | completed | true | ms,en,zh,ta,und |

`source_unchanged = true`; ownership: owner can view = true, other user can view
= false. (Segment index, `decimal(12,3)` timestamps, and source-language markers
preserved; en segments passed through unchanged.)

## Exports (real persisted translation, target `ta`)

| Format | Status | Content-Type | Filename | Bytes |
|---|---|---|---|---|
| TXT | 200 | text/plain; charset=utf-8 | `...-ta.txt` | 234 |
| SRT | 200 | application/x-subrip | `...-ta.srt` | 372 |
| VTT | 200 | text/vtt | `...-ta.vtt` | 370 |
| DOCX | 200 | wordprocessingml | `...-ta.docx` | 7505 |

SRT/VTT timestamps inherited (e.g. `00:00:00,000 --> 00:00:04,000`).
Cross-user export denial is authoritatively covered by the passing
`TranslationExportTest` non-owner case (the ad-hoc script's Auth state was not
authoritative and is not relied upon).

## Browser verification

Playwright (`verification/playwright.p5-006.config.js`, real Chromium, real app):
**18 passed (1.3 min)**, including cross-user isolation and zero workspace
errors. The harness uses the deterministic translation-worker double (as in the
accepted P5-006 evidence); the real-model evidence is the queued-path run above.

## Regression / quality

- Full PHP suite: 624 tests, 623 passed, 1 skipped, 2 warnings, 0 failures.
- Translation suite: 191 passed.
- Worker pytest: 42 passed.
- Pint: clean. PHPStan: 0 errors.
- Clean-checkout reproducibility: archived `HEAD` + `composer install` +
  built assets ? translation suite 191/191.

## Residual (contract-level, not re-run in real mode)

Attempt-token fencing, retry/recovery, stale recovery, and queue timeout
behaviour are verified by the contract-level suite and code review; the real run
exercised the happy path. No unresolved BLOCKER/HIGH/MEDIUM.
