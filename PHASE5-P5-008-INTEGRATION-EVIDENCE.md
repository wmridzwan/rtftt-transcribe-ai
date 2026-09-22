# P5-008 — Phase 5 Integration Verification — Evidence / Blocker Record

Date: 2026-09-22
Task: P5-008 (promoted `BACKLOG → READY`; `DECISION-P5-008-AUTHORIZATION-001`)
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

Worker virtualenv (`worker/.venv`, Python 3.13.14) — required translation runtime
absent:

```
import transformers -> ModuleNotFoundError: No module named 'transformers'
import torch        -> ModuleNotFoundError: No module named 'torch'
import sentencepiece-> ModuleNotFoundError: No module named 'sentencepiece'
```

Installed relevant packages (pip list): `ctranslate2 4.8.2`, `faster-whisper
1.2.1`, `onnxruntime 1.30.0`, `torchcodec 0.16.0` — **no `torch`,
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
required real-model integration evidence" (HPO authorization §3; ADR-022 D5-09;
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
full suite 624/623; two-process claim/retry/request races 3/3 × 3 runs; Pint
clean; PHPStan 0; worker pytest 42 passed; `schedule:list` confirmed. These do
not satisfy P5-008 and are not represented as doing so.

## Requested HPO decision

See `BLOCKERS.md` B-004 and `PHASE5-CLOSURE-REPORT.md` for the options.
P5-008 remains BLOCKED; Phase 5 is not closed.