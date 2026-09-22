# P5-008 — Independent Phase 5 Integration Review

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-23
Scope: `tasks/P5-008-phase-integration-verification.md` only, over the frozen
Phase 5 implementation and the committed corrective evidence
(`PHASE5-P5-008-INTEGRATION-EVIDENCE.md`; `verification/p5-008/`). Does not
implement fixes, does not modify implementation code, does not mark P5-008 DONE,
does not close Phase 5, does not authorize Phase 6 or Phase 7.

Verdict: **P5-008 = VERIFIED** — no BLOCKER, HIGH, or MEDIUM findings. Eligible to
return to the Human Product Owner for closure consideration. Phase 5 remains NOT
CLOSED until an explicit HPO closure decision.

## 1. Executive Summary

This is the fresh independent review required after the P5-008 corrective
(`DECISION-P5-008-CORRECTIVE-001`; ADR-024). The first review had returned
`CHANGES_REQUESTED` (BLOCKER: the real gate ran on an undeclared/unauthorized
runtime and the model cache was corrupt). The corrective adopted the proven
canonical runtime, reconciled all declarations to one runtime, re-provisioned a
clean canonical NLLB cache, committed a reproducible real-gate harness, executed
a true browser-to-real-model flow, and re-ran the canonical gate.

The independent review confirmed, on the committed evidence and artifacts:

- canonical runtime provenance (`transformers==5.17.0`, `torch==2.14.0`,
  `sentencepiece==0.2.2`) is the single declared/gated runtime;
- the exact dependency pins match the runtime that executed the gate;
- a valid canonical NLLB cache exists at the pinned revision;
- real, non-empty model inference was produced (not a mock/double);
- the committed P5-008 harness is reproducible;
- a real browser → Laravel → Redis → worker → NLLB → persistence → browser
  end-to-end flow was executed;
- all required targets `ms`, `en`, `zh`, `ta` completed over the real path;
- code-switched source handling was exercised;
- alignment, timestamps, and source-language markers were preserved;
- the source transcript remained immutable;
- ownership isolation held;
- translated TXT/SRT/VTT/DOCX exports were produced;
- queue/runtime safety invariants held;
- the real-model evidence is separated from contract/concurrency evidence.

No BLOCKER, HIGH, or MEDIUM finding was raised.

## 2. Authorization / Scope

- P5-008 was promoted `BACKLOG → READY` and authorized by
  `DECISION-P5-008-AUTHORIZATION-001`; the corrective was authorized by
  `DECISION-P5-008-CORRECTIVE-001` and ADR-024.
- P5-008 dependencies (P5-004, P5-005, P5-006, P5-007) are DONE; P5-006 browser
  governance is ADR-021; the real-provider requirement is ADR-022 D5-09.
- The review covers only the integration gate and its evidence. It does not
  reopen frozen Phase 3/4/5 contracts and does not authorize Phase 6/7 work.

## 3. Canonical Runtime Provenance

- `worker/requirements.txt` declares exact pins
  `transformers==5.17.0`, `torch==2.14.0`, `sentencepiece==0.2.2`; the stale `>=`
  ranges and the superseded 4.x pins are corrected/marked superseded
  (ADR-024).
- Guards exist and pass: `worker/tests/test_requirements.py` and
  `tests/Unit/Translation/TranslationRuntimePinTest.php`.
- The gated runtime matches the declared runtime; the earlier
  undeclared-runtime BLOCKER is resolved.

## 4. Canonical Model Cache

- Model identity unchanged: `facebook/nllb-200-distilled-600M`.
- Clean cache re-provisioned on `C:` at pinned revision
  `f8d333a098d19b4fd9a8b18f94170487ad3f821d`; `config.json`, `tokenizer.json`,
  and `pytorch_model.bin` hashes recorded in the evidence.
- JSON assets valid; tokenizer resolves `zsm_Latn`/`eng_Latn`/`zho_Hans`/
  `tam_Taml` to real NLLB ids; the model loads and returns non-empty text.
- The corrupt `D:` cache (B-006) is recorded as an environment defect and is no
  longer in the gated path. Weights are not committed; provisioning is
  documented in `verification/p5-008/README.md`.

## 5. Real Inference and Real End-to-End Path

- Authenticated worker `/translate` (en→ms) returned
  `{"provider":"self-hosted","model":"facebook/nllb-200-distilled-600M",
  "text":"Selamat pagi, selamat datang ke mesyuarat.", ...}`; unauthenticated
  `/translate` returned `401`.
- Browser-to-real-model proof (`verification/p5-008/browser-evidence.json`):
  real Chromium → real Laravel → real Redis queue → real `ProcessTranslation` →
  real authenticated worker → real NLLB → persisted translation; the page
  observed `completed` after polling; all five segments non-empty and the
  English segment translated (not identity); source unchanged; one real `-ms.txt`
  export downloaded and non-empty.
- No worker double substitutes for the real-model evidence. The deterministic
  double used by the accepted P5-006 browser harness is not relied upon here.

## 6. Multilingual / Code-Switch Gate

Over one code-switched source (segments `ms,en,zh,ta,und`) all four contract
targets `ms`/`en`/`zh`/`ta` completed via the real queued path; `aligned` and
`non_empty`; segment indices `0..4`, `decimal(12,3)` timestamps, and
source-language markers preserved; `source_unchanged: true`; non-owner view
denied (`ownership_isolated: true`).

## 7. Translated Exports

Real persisted translation (target `ta`): TXT/SRT/VTT/DOCX each returned `200`
with target-suffixed filenames; SRT/VTT inherit authoritative source timestamps
(first `00:00:00,000`); cross-user export denied (authoritative
`TranslationExportTest` non-owner case). No provider invocation occurs during
export.

## 8. Queue / Runtime Safety

Redis reachable; effective queue connection `redis`, queue `translation`; timeout
invariant `provider 300 < job 330 < retry_after 420`; stale threshold 360; stale
recovery scheduled every minute, token-fenced. Redis payload contains the
translation id and no media path/binary. No consistency violation observed.

## 9. Separation of Real-Model and Contract Evidence

The real gate exercises the successful path. Attempt-token fencing, stale
recovery, failure/retry/recovery, and queue-timeout behaviour remain proven by
the contract-level and two-process concurrency suites. This separation is
explicitly stated in the evidence and is confirmed here, so the real-model
evidence is never represented as covering destructive/failure scenarios.

## 10. Regression / Quality (reproduced by the evidence)

- Worker pytest: `46 passed`.
- Translation suite: `193 passed / 733 assertions`.
- Full PHP suite: `626 tests, 625 passed, 1 skipped, 2 warnings, 0 failures`.
- Concurrency/fencing/recovery/export focused: `24 passed / 103 assertions`.
- Pint clean; PHPStan 0 errors.
- Canonical real gate: `node verification/p5-008-real-gate.mjs` → all checks
  `ok: true`.

## 11. Retained Non-Blocking Debt (LOW / INFO)

No BLOCKER/HIGH/MEDIUM. The following remain non-blocking and are carried
forward:

- redis-payload leakage check is heuristic rather than structural;
- command-level export isolation evidence is weaker than HTTP-level evidence;
- persisted model identity remains config-derived (does not validate the
  worker's self-reported model);
- `und → eng_Latn` remains the documented worker fallback;
- Laravel and worker default model labels differ unless explicitly configured;
- non-translation worker dependency ranges remain outside the Phase 5 canonical
  runtime trio;
- unrelated PHPUnit warnings remain informational;
- B-006 / `D:` corruption hazard remains documented operational evidence.

## 12. Verdict

```text
P5-008 = VERIFIED
BLOCKER/HIGH/MEDIUM = none
Phase 5 = NOT CLOSED (requires explicit HPO closure)
Phase 6 / Phase 7 = NOT authorized by this review
```

The implementation owner must not self-close; closure as DONE and Phase 5
closure remain Human Product Owner actions.