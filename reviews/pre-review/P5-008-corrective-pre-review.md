# P5-008 — Builder Pre-Review Handoff (corrective)

Date: 2026-09-22
Task: P5-008 — Phase 5 Integration Verification
Status: `IMPLEMENTED_PENDING_REVIEW`
Authority: `DECISION-P5-008-CORRECTIVE-001`; ADR-024
Reviewer: Claude Code (fresh independent review required)

## Why this corrective exists

The first independent P5-008 review returned `CHANGES_REQUESTED` with a BLOCKER:
the real gate ran on an undeclared/unauthorized runtime (`transformers 5.17.0`,
`torch 2.14.0`, `sentencepiece 0.2.2`) while the committed contract pinned
`transformers==4.57.6` / `torch==2.9.1` / `sentencepiece==0.2.1`, and no
authorization existed. The model cache was also found corrupt.

## What changed

1. **Decision:** ADR-024 + `DECISION-P5-008-CORRECTIVE-001` adopt the proven
   runtime and supersede the 4.x pins.
2. **Single declaration:** `worker/requirements.txt` now exact-pins
   `transformers==5.17.0` / `torch==2.14.0` / `sentencepiece==0.2.2`; stale `>=`
   and 4.x statements corrected. Guards:
   `worker/tests/test_requirements.py`,
   `tests/Unit/Translation/TranslationRuntimePinTest.php`.
3. **Clean cache:** canonical `facebook/nllb-200-distilled-600M` re-provisioned
   on `C:` at pinned revision `f8d333a0…`; assets validated; weights not
   committed. Procedure: `verification/p5-008/README.md`.
4. **Committed harness:** `verification/p5-008-real-gate.mjs` +
   `app/Console/Commands/Phase5IntegrationVerification.php` (hidden; modes
   `preflight`/`seed`/`dispatch`/`redis-payload`/`assert`/`export`) +
   worker `GET /runtime` provenance endpoint.
5. **Real browser proof:** `verification/playwright.p5-008.config.js` +
   `verification/p5-008/real-model-e2e.spec.js` (no worker double).
6. **Re-ran the canonical gate:** all checks `ok: true`. Evidence in
   `verification/p5-008/` and `PHASE5-P5-008-INTEGRATION-EVIDENCE.md`.

## Requested fresh-review focus

- canonical dependency pins match the gated runtime;
- clean provisioning reproduces the runtime (`pip install -r worker/requirements.txt`);
- the model cache is valid (JSON assets, revision, hashes);
- the committed harness works and is reproducible;
- the browser-to-real-model evidence is genuine (not the P5-006 double);
- no mock is represented as real-model evidence;
- source immutability, alignment/source-language preservation, ownership
  isolation, and all four exports.

## Quality (this corrective)

- Worker pytest: 46 passed. Translation suite: 193 passed / 733 assertions.
- Full PHP suite: 626 tests / 625 passed / 1 skipped / 2 warnings / 0 failures.
- Concurrency/fencing/recovery/export focused: 24 passed / 103 assertions.
- Pint clean; PHPStan 0 errors.
- Canonical real gate: `node verification/p5-008-real-gate.mjs` → all `ok: true`.

## Explicit scope separation

The real gate proves the successful path. Attempt-token fencing, stale recovery,
retry/recovery, and queue-timeout behaviour remain proven by the contract-level
and two-process concurrency suites, not by destructive real-model scenarios.

P5-008 is not VERIFIED and Phase 5 is not closed. The implementer must not
self-verify.
