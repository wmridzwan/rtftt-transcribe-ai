# Phase 5 — Final Closure Report

Date: 2026-09-23
Authority: Human Product Owner (`DECISION-P5-008-CLOSURE-001`,
`DECISION-PHASE5-CLOSURE-001`, `DECISION-PHASE5-DEBT-CARRYFORWARD-001`)
Status: **Phase 5 = CLOSED (2026-09-23).**
Supersedes: the Phase 5 closure-boundary report dated 2026-09-22 (which recorded
Phase 5 as NOT closed and P5-008 as BLOCKED). The 2026-09-22 boundary report and
its blocker history are preserved as historical truth; this report is the final
canonical closure record.

This record summarizes Phase 5 closure. It does not replace the detailed task
contracts, independent reviews, corrective provenance, or integration evidence;
those remain the authoritative artifacts.

## A. Final task states

```text
DONE
  P5-001   Translation Domain Contract
  P5-001A  Translation DTO Input Guards
  P5-002   Translation Persistence / Atomic Writer
  P5-002A  Translation Writer Correctness
  P5-002B  Translation Writer Attempt-State Hardening
  P5-003   Translation Provider Boundary
  P5-004   Translation Queue / Lifecycle Orchestration
  P5-004B  Pre-UI Translation Hardening
  P5-004C  Operational Pre-Flight
  P5-005   Translation Failure / Retry / Recovery
  P5-006   Translation Workspace UI
  P5-007   Translation Export Foundation
  P5-008   Phase 5 Integration Verification (closed 2026-09-23)
```

No Phase 5 task remains BACKLOG/READY/IN_PROGRESS/REVIEW/VERIFIED/BLOCKED.

## B. Final HEAD / branch state

- Branch: `phase5-7/parallel-2026-09-21`.
- Corrective HEAD on which the canonical real gate was executed and independently
  verified: `a77852c` (`P5-008: corrective - canonical runtime, clean cache,
  committed real gate (ADR-024)`).
- This closure report is committed in the Phase 5 closure governance commit; the
  working tree is clean and the repository remains reviewable.
- Phase 3/4 baseline committed (B-001/B-002 resolved).

## C. P5-008 independent verdict

- Fresh independent review: `reviews/P5-008-independent-review.md` →
  **P5-008 = VERIFIED**, no BLOCKER/HIGH/MEDIUM.
- Accepted and closed DONE by `DECISION-P5-008-CLOSURE-001`.
- Preserved: the prior `CHANGES_REQUESTED` review history (undeclared runtime;
  corrupt cache), the corrective decision `DECISION-P5-008-CORRECTIVE-001`, ADR-024,
  and `PHASE5-P5-008-INTEGRATION-EVIDENCE.md`. No history rewritten.

## D. Real-model evidence

- Model identity: `facebook/nllb-200-distilled-600M` (canonical; unchanged).
- Clean cache: revision `f8d333a098d19b4fd9a8b18f94170487ad3f821d`; assets
  validated (JSON valid; tokenizer resolves `zsm_Latn`/`eng_Latn`/`zho_Hans`/
  `tam_Taml`); non-empty inference.
- Authenticated worker `/translate` (en→ms):
  `{"provider":"self-hosted","model":"facebook/nllb-200-distilled-600M",
  "text":"Selamat pagi, selamat datang ke mesyuarat.", ...}`; unauthenticated →
  `401`.
- Real Redis queued path: Laravel → Redis → `ProcessTranslation` →
  authenticated worker → real NLLB → atomic persistence. Redis payload present
  (`queues:translation`), contains the translation id, no media path/binary.
- Multilingual/code-switch: one source with `ms,en,zh,ta,und`; all four contract
  targets `ms`/`en`/`zh`/`ta` completed, `aligned`, `non_empty`; indices `0..4`,
  `decimal(12,3)` timestamps and source-language markers preserved;
  `source_unchanged: true`; non-owner view denied (`ownership_isolated: true`).
- Exports (real persisted translation, target `ta`): TXT/SRT/VTT/DOCX each `200`
  with target-suffixed filenames; SRT/VTT inherit authoritative timestamps; no
  provider invocation during export; cross-user export denied.

## E. Real browser E2E evidence

- `verification/p5-008/browser-evidence.json`: real Chromium → real Laravel →
  real Redis queue → real `ProcessTranslation` → real authenticated worker →
  real NLLB → persisted translation → open page observed `completed` after
  polling; provider `self-hosted`, model `facebook/nllb-200-distilled-600M`; all
  five segments non-empty; the English segment translated (not identity); source
  unchanged; one real `-ms.txt` export downloaded and non-empty.
- No worker double was used for the real-model evidence. The P5-006
  deterministic double is confined to the accepted P5-006 browser harness and is
  not represented as real-model evidence.

## F. Runtime / dependency versions

| Item | Value |
|---|---|
| Python | 3.13.14 (`worker/.venv`) |
| `transformers` | `5.17.0` |
| `torch` | `2.14.0` (CPU wheel `2.14.0+cpu`) |
| `sentencepiece` | `0.2.2` |
| Model | `facebook/nllb-200-distilled-600M` |
| Redis | Redis for Windows 5.0.14.1 on `127.0.0.1:6379` |
| Worker URL | `http://127.0.0.1:8000` (uvicorn `worker.main:app`) |

Superseded pins (`transformers==4.57.6`, `torch==2.9.1`, `sentencepiece==0.2.1`)
were never successfully real-model gated; ADR-024 supersedes them. Single
declaration: `worker/requirements.txt`, guarded by
`worker/tests/test_requirements.py` and
`tests/Unit/Translation/TranslationRuntimePinTest.php`.

## G. Model revision

- `facebook/nllb-200-distilled-600M` revision
  `f8d333a098d19b4fd9a8b18f94170487ad3f821d`.
- `config.json` sha256 `f9b4081d…db51fbc5`; `tokenizer.json` sha256
  `e316b82d…01bca665`; `pytorch_model.bin` sha256 `c266c2cf…83eefcc42`
  (2 460 457 927 bytes). Weights are not committed; reproducible provisioning is
  documented in `verification/p5-008/README.md`.

## H. Queue / runtime configuration

- Queue connection: `redis`; queue name: `translation`.
- Job timeout 330 s; provider timeout 300 s; required `retry_after` 420 s;
  stale threshold 360 s.
- Invariant `provider 300 < job 330 < retry_after 420` holds.
- Stale recovery: `translation:recover-stale-attempts`, every minute,
  token-fenced.
- No secrets or tokens are recorded.

## I. Regression / static results

- Worker pytest: `46 passed`.
- Translation suite: `193 passed / 733 assertions`.
- Full PHP suite: `626 tests, 625 passed, 1 skipped (pre-existing 2FA),
  2 warnings, 0 failures`.
- Concurrency/fencing/recovery/export focused: `24 passed / 103 assertions`.
- Pint: clean. PHPStan: 0 errors.
- Canonical real gate: `node verification/p5-008-real-gate.mjs` → all checks
  `ok: true`.
- Clean-checkout reproducibility: PHP translation suite reproduced on a clean
  checkout; worker runtime reproduced by
  `pip install -r worker/requirements.txt`; model cache reproduced by the
  pinned-revision provisioning in the runbook.
- The 2 warnings are the pre-existing baseline; unrelated PHPUnit warnings
  remain informational.

## J. Deferred LOW/INFO debt (non-blocking)

Carried forward under `DECISION-PHASE5-DEBT-CARRYFORWARD-001`. Phase 5 is not
represented as defect-free; it is not reopened to clean these items.

- redis-payload leakage check is heuristic rather than structural;
- command-level export isolation evidence is weaker than HTTP-level evidence;
- persisted model identity remains config-derived (does not validate the
  worker's self-reported `model`);
- `und → eng_Latn` remains the documented worker fallback;
- Laravel and worker default model labels differ unless explicitly configured;
- non-translation worker dependency ranges remain outside the Phase 5 canonical
  runtime trio;
- unrelated PHPUnit warnings remain informational;
- B-006 / `D:` corruption hazard remains documented operational evidence
  (relocated to `C:`; hazard documented, not erased).

Additional retained review debt:

- P5-002A MEDIUM (`translationId` not validated when a completed same-target row
  exists) — structurally prevented by the unique index; LOW findings retained.
- P5-002B L-1/L-2; P5-003 L-1..L-4; P5-004 M-1..M-3/L-1..L-4; P5-005 M-1/L-1.
- P5-004B R-2 (awaiting-dispatch UI label), P4B-6 (hand-built race schema).
- P5-007 L-1/L-2 (multilingual test strength; filename collision).
- P5-006 P6-3/P6-4 (retry dead-end copy; awaiting-dispatch/clipboard caveats).
- P5-004C LOW: full-suite warning count variance; runbook trailing newline.
- Zero-segment product decision; `PERSISTENCE_FAILED` UI dead-end note.

Phase 7 owns productionization (data store, queue supervision/Horizon, storage,
observability, security, backup/restore, retention, capacity).

## K. Real-model vs contract/concurrency separation

The real gate exercises the successful path. Attempt-token fencing, stale
recovery, failure/retry/recovery, and queue-timeout behaviour remain proven by
the contract-level and two-process concurrency suites. The real-model evidence is
never represented as covering destructive/failure scenarios.

## L. Phase boundary / Phase 6–7

- Phase 5 closure does not authorize general Phase 6 or Phase 7 implementation.
  Those remain governed by ADR-023 and
  `PHASE5-7-EXECUTION-CLASSIFICATION.md`.
- Phase 6 is now the primary product-development path and may be authorized by a
  separate HPO decision; Phase 7 early-hardening may run in parallel only where
  the existing contracts prove independence.
- Phase 6 cannot close before Phase 5 (now satisfied); Phase 7 cannot close
  before Phase 6; P7-012 must not run before Phase 6 is CLOSED.

## M. Explicit HPO closure decision

```text
DECISION-P5-008-CLOSURE-001  -> P5-008 VERIFIED accepted; P5-008 = DONE
DECISION-PHASE5-CLOSURE-001  -> Phase 5 = CLOSED (2026-09-23)
DECISION-PHASE5-DEBT-CARRYFORWARD-001 -> LOW/INFO debt carried non-blocking
```

## N. Confirmations

```text
No self-reviewed task was marked VERIFIED.
No phase was closed automatically.
No unauthorized Phase 6/7 work was performed.
No frozen contract was changed without authorization.
No integration gate was over-claimed using mocks.
```