# P5-008 Canonical Real-Gate Harness — Runbook

Committed verification tooling for the Phase 5 final integration gate
(`DECISION-P5-008-CORRECTIVE-001`, ADR-024). It drives the real self-hosted
translation path end to end:

```
real Chromium -> real Laravel -> real Redis translation queue ->
real ProcessTranslation job -> real authenticated Python worker ->
real facebook/nllb-200-distilled-600M -> persisted translation -> exports
```

It does **not** use the P5-006 deterministic worker double. The P5-006
double-based 18-case suite remains the broad browser regression evidence.

## Prerequisites

- PHP 8.4 (`PHP_BIN`, default Herd `php84`).
- Node + Playwright with Chromium installed.
- A reachable Redis server (`127.0.0.1:6379` by default).
- `worker/.venv` with `worker/requirements.txt` installed (canonical runtime).
- The canonical model provisioned into a clean HF cache (below).

Canonical runtime (ADR-024; the single declared translation runtime):

| Package | Version |
|---|---|
| `transformers` | `5.17.0` |
| `torch` | `2.14.0` (CPU wheel reports `2.14.0+cpu`; accepted) |
| `sentencepiece` | `0.2.2` |
| Python | 3.13.x |

Model identity (unchanged): `facebook/nllb-200-distilled-600M`.

## 1. Provision the worker runtime

```bash
python -m venv worker/.venv
worker/.venv/Scripts/python.exe -m pip install -r worker/requirements.txt
```

Guard tests fail if `worker/requirements.txt` drifts from the canonical pins
(`worker/tests/test_requirements.py`, `tests/Unit/Translation/TranslationRuntimePinTest.php`).

## 2. Provision the canonical model (clean cache; weights are not committed)

Download the canonical model at a pinned revision into a clean Hugging Face
cache on a **reliable volume**:

```bash
worker/.venv/Scripts/python.exe - <<'PY'
from huggingface_hub import snapshot_download
snapshot_download(
    "facebook/nllb-200-distilled-600M",
    revision="f8d333a098d19b4fd9a8b18f94170487ad3f821d",
    cache_dir=r"C:\rtftt-hf-cache",
)
PY
```

Set `HF_HOME=C:\rtftt-hf-cache` for the worker. Recorded revision and artifact
hashes for the gate run:

| Artifact | SHA-256 |
|---|---|
| revision | `f8d333a098d19b4fd9a8b18f94170487ad3f821d` |
| `config.json` | `f9b4081d3d108e9d06532ea8d92b932c04ed3a15ff2f87e6982f8e14db51fbc5` |
| `tokenizer.json` | `e316b82de11d0f951f370943b3c438311629547285129b0b81dadabd01bca665` |
| `pytorch_model.bin` | `c266c2cfd19758b6d09c1fc31ecdf1e485509035f6b51dfe84f1ada83eefcc42` (2 460 457 927 bytes) |

Verify before running the gate:

```bash
worker/.venv/Scripts/python.exe -c "import json; json.load(open(r'C:\rtftt-hf-cache\hub\models--facebook--nllb-200-distilled-600M\snapshots\f8d333a098d19b4fd9a8b18f94170487ad3f821d\config.json', encoding='utf-8')); print('config ok')"
```

> **Storage hazard (recorded):** during the corrective, a prior cache on the
> `D:` volume was found silently corrupt (large files changed bytes while
> keeping their length; `config.json`/`tokenizer.json` were not valid JSON),
> and the `D:`-hosted Redis had disabled writes after RDB save failures. The
> cache was re-provisioned on `C:` and Redis was re-pointed at `C:\rtftt-redis`.
> Use a reliable volume; verify hashes after copying.

## 3. Redis

A reachable Redis is required. For a local gate, persistence is not needed:

```
CONFIG SET stop-writes-on-bgsave-error no
CONFIG SET dir C:\rtftt-redis
CONFIG SET save ""
```

## 4. Run the gate

```bash
node verification/p5-008-real-gate.mjs
```

The orchestrator:

1. starts the real Python worker (`HF_HOME`, verification token, port 8100);
2. runs the artisan preflight (runtime versions, model identity, Redis, worker
   auth, queue/timeout invariant);
3. runs the Playwright browser-to-real-model proof
   (`verification/playwright.p5-008.config.js`);
4. runs the real Redis queued path for `ms`/`en`/`zh`/`ta` with a code-switched
   source, captures the Redis payload, consumes it with a real `queue:work redis`
   worker, then asserts persistence/alignment/immutability/ownership and all
   four exports;
5. writes tracked evidence under `verification/p5-008/`.

Individual pieces:

```bash
# artisan gate only (worker + Redis must be running)
RTFTT_P5008_RUN=1 php artisan test:p5-008-integration --mode=preflight --out=verification/p5-008/preflight.json
# browser proof only (worker + Redis must be running; queue worker is run inline)
npx playwright test -c verification/playwright.p5-008.config.js
```

## 5. Evidence produced

| File | Content |
|---|---|
| `verification/p5-008/gate-summary.json` | consolidated summary |
| `verification/p5-008/preflight.json` | runtime/model/redis/auth/queue/timeout checks |
| `verification/p5-008/browser-evidence.json` | real browser-to-real-model proof |
| `verification/p5-008/redis-payload.json` | real Redis queue payload (small identifiers only) |
| `verification/p5-008/assert.json` | persisted results, alignment, immutability, ownership |
| `verification/p5-008/exports.json` | TXT/SRT/VTT/DOCX verification |

Contract/concurrency tests continue to prove destructive retry/fencing/failure
scenarios that are unsafe or expensive to reproduce through the real model. The
real gate exercises the successful path; that separation is stated explicitly in
`PHASE5-P5-008-INTEGRATION-EVIDENCE.md`.
