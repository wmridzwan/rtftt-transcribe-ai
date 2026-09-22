# Phase 5 — Closure Boundary Report

Date: 2026-09-22
Authority: Human Product Owner (`DECISION-PHASE5-TASK-CLOSURES-001`,
`DECISION-P5-008-AUTHORIZATION-001`)
Status: **Phase 5 is NOT closed.** P5-008 is BLOCKED at the real-model
prerequisite. This report stops at the Phase 5 closure boundary; only the HPO may
declare Phase 5 CLOSED.

## A. Final task states

```text
DONE
  P5-001  Translation Domain Contract
  P5-001A Translation DTO Input Guards
  P5-002  Translation Persistence / Atomic Writer
  P5-002A Translation Writer Correctness
  P5-002B Translation Writer Attempt-State Hardening
  P5-003  Translation Provider Boundary
  P5-004  Translation Queue / Lifecycle Orchestration
  P5-004B Pre-UI Translation Hardening
  P5-004C Operational Pre-Flight
  P5-005  Translation Failure / Retry / Recovery
  P5-006  Translation Workspace UI
  P5-007  Translation Export Foundation

BLOCKED
  P5-008  Phase 5 Integration Verification (promoted READY and authorized;
          blocked on B-004 real runtime/model/Redis)
```

## B. P5-008 verdict

- Promoted `BACKLOG → READY` and execution authorized
  (`DECISION-P5-008-AUTHORIZATION-001`).
- **BLOCKED.** The real self-hosted provider/model gate (ADR-022 D5-09) was not
  executed and is not claimed. Mocks were not substituted.
- Evidence: `PHASE5-P5-008-INTEGRATION-EVIDENCE.md`.

## C. Real-model evidence

**None produced** — the real runtime is absent:

- `worker/.venv` (Python 3.13.14) has **no** `transformers`, `torch`,
  `sentencepiece` (imports fail); only `ctranslate2`, `faster-whisper`,
  `onnxruntime`, `torchcodec` are present.
- No translation model cached (HF cache holds only faster-whisper ASR models).
- No live Redis server on `127.0.0.1:6379`.
- Effective queue config (non-test): `queue.default=database`,
  `translation.queue=translation`, `translation.queue_connection=NULL`,
  `job_timeout=330`, `provider_timeout=300`, `retry_after_req=420`,
  `db_retry=420`.

The intended canonical configuration is recorded in the evidence artifact; no
secrets are recorded.

## D. Browser evidence

- Phase 4 and P5-006 browser evidence exists under ADR-021
  (`P5-006-BROWSER-VERIFICATION-EVIDENCE.md`; P5-006 is DONE).
- **P5-008 browser verification was not run** (depends on the real path).

## E. Unresolved LOW/INFO findings (non-blocking, retained)

- P5-002A: MEDIUM (`translationId` not validated when a completed same-target row
  exists) — structurally prevented by the unique index; LOW findings retained.
- P5-002B L-1/L-2; P5-003 L-1..L-4; P5-004 M-1..M-3/L-1..L-4; P5-005 M-1/L-1.
- P5-004B R-2 (awaiting-dispatch UI label), P4B-6 (hand-built race schema).
- P5-007 L-1/L-2 (multilingual test strength; filename collision).
- P5-006 P6-3/P6-4 (retry dead-end copy; awaiting-dispatch/clipboard caveats).
- P5-004C LOW: full-suite warning count variance; runbook trailing newline.
- Zero-segment product decision; `PERSISTENCE_FAILED` UI dead-end note.

## F. Deferred debt

- B-004 (real translation runtime/model + Redis provisioning).
- Phase 3/4 baseline is committed (B-001/B-002 resolved).
- Phase 7 owns: production data store, queue supervision/Horizon, storage,
  observability, security, backup/restore, retention, capacity.

## G. Repository / commit state

- Branch `phase5-7/parallel-2026-09-21`; HEAD recorded in the commit that adds
  this report.
- Working tree clean; Phase 3/4 baseline committed.

## H. Regression-suite evidence

- Translation suite: 191 passed / 724 assertions.
- Full PHP suite: 624 tests, 623 passed, 1 skipped (pre-existing 2FA), 2 warnings,
  0 failures.
- Two-process races (claim/retry/request): 3/3 × 3 consecutive runs.
- Worker pytest: 42 passed.
- Pint clean; PHPStan 0 errors; `schedule:list` confirms stale recovery every
  minute.

## I. Source immutability and ownership isolation

- Source transcript/segment immutability is asserted by the translation
  persistence tests (before/after row comparison) and the writer design (D5-05);
  verified in the contract-level suite. **Not** re-proven through the real gate.
- Ownership isolation is enforced via `TranscriptionPolicy` and covered by the
  export/workspace tests. **Not** re-proven through the real gate.

## J. Recommendation

1. **Do not close Phase 5.** The mandatory P5-008 real-model gate is unmet.
2. Resolve **B-004** with one of:
   - **Option A (recommended):** provision the canonical stack — install
     `torch`/`transformers`/`sentencepiece` into `worker/.venv`, download the
     canonical NLLB model (or an HPO-approved smaller self-hosted model), start a
     Redis server, then execute P5-008 and obtain a fresh independent review.
   - **Option B:** approve a smaller self-hosted model to reduce download/CPU
     cost (contract change to the model identity), then execute the gate.
   - **Option C:** explicitly defer P5-008 and record the deferral as accepted
     risk; Phase 5 would remain open.
3. On P5-008 success (IMPLEMENTED_PENDING_REVIEW → independent VERIFIED), return
   to the HPO for the Phase 5 closure decision.

## K. Confirmations

```
No self-reviewed task was marked VERIFIED.
No phase was closed automatically.
No unauthorized Phase 6/7 work was performed.
No frozen contract was changed without authorization.
No integration gate was over-claimed using mocks.
```