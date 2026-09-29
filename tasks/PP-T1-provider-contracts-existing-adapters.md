# PP-T1 — Provider Contracts & Existing Adapter Migration

## 1. Status

`DONE — CLOSED BY HPO (DECISION-PP-T1-CLOSURE-001)`

Reconciliation record (2026-09-27, HPO `DECISION-PP-T1-READY-PROMOTION-001`):
independent contract review returned `CONTRACT_VERIFIED — READY_ELIGIBLE`
(1 HIGH governance finding, 2 MEDIUM wording findings, LOW/INFO notes; no
implementation defect, no compatibility contradiction). HIGH resolved by
persisting ADR-027 (`DECISION-PROCESSING-PROVIDER-OPTION1-001`); MEDIUMs
reconciled in §§12–13 below; review artifact itself unchanged. History
preserved, not rewritten.

Execution record (2026-09-27): readiness `READY_CONFIRMED`; HPO execution
authorization granted (`DECISION-PP-T1-EXECUTION-AUTHORIZATION-001`);
lifecycle `READY → IN_PROGRESS → REVIEW`. PP-T2–PP-T6 remain unauthorized.

Builder verification (2026-09-27): new suites 10/10 pass; full suite 1108
(1103 passed, 5 pre-existing skips, 0 failures on fresh rerun — one
order-dependent LogContextTest flake on first run, passes in isolation
and rerun, unrelated to provider bindings); Pint clean; PHPStan 0 errors.
Implementer claims neither VERIFIED nor DONE.

Independent review (2026-09-27, `reviews/PP-T1-INDEPENDENT-REVIEW.md`):
`PP-T1 = VERIFIED` on independently reproduced evidence (AC1–AC8 PASS;
forbidden-scope audit clean; no BLOCKER/HIGH). Findings: PP-T1-REV-01
(LOW, translation failure-path asymmetry — accepted non-blocking),
PP-T1-REV-02 (INFO, redundant `implements` — harmless),
PP-T1-REV-03 (INFO, LogContextTest flake not reproduced — unrelated).
Lifecycle `REVIEW → VERIFIED` evidenced by the review artifact.

Closure (2026-09-27, HPO `DECISION-PP-T1-CLOSURE-001`): HPO accepts PP-T1;
canonical transition `VERIFIED → DONE`. Full lifecycle history:
`READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`. PP-T2–PP-T6 remain
BACKLOG; closure authorizes no later work.

## 2. Objective

Freeze `TranscriptionProvider` / `TranslationProvider` contracts and migrate existing self-hosted faster-whisper `large-v3` and NLLB paths behind them with zero behavior change.

## 3. Why

Discovery A01/A02 proved the narrowest seam is construction+mapping behind the ServiceProvider bindings. T1 creates the durable abstraction for Option 1 without touching routing, vendors, chunking, or clients.

## 4. Dependencies

Requires: HPO architecture direction (Option 1); `ARCHITECTURE-PLAN-OPTION1.md` §§1–6; discovery `CURRENT-TRANSCRIPTION-BOUNDARY.md`, `CURRENT-TRANSLATION-BOUNDARY.md`. Implementation dependency: none (first in wave).

## 5. Inputs / frozen contracts

- `app/Transcription/TranscriptionProvider.php`, `TranscriptionInvocation/Media/Options`, `NormalizedTranscript`, `TranscriptionFailure`, `TranscriptionLifecycle`, `TranscriptionResultWriter`, `TranscriptionRetry`, queue `transcription`, timeouts 300/330/420.
- `app/Translation/TranslationProvider.php`, `TranslationInvocation/Request/Result`, `TranslationResponseValidator`, `TranslationFailure`, `TranslationLifecycle`, `TranslationResultWriter`, queue `translation`.
- `config/transcription.php` (`large-v3`, `contract_version=1.0`), `config/translation.php` (NLLB distilled-600M, `1.0`).
- Phase 3/5/6 contracts frozen; `PHASE17-COMPATIBILITY-REVIEW.md`.

## 6. Scope

- Freeze both provider interfaces + DTOs (no signature change without ADR).
- Create `SelfHostedTranscriptionProvider` (rename-or-wrap of `HttpTranscriptionProvider`, same behavior) and `SelfHostedTranslationProvider` (same for translation).
- Rewire `TranscriptionServiceProvider` (transcription binding) and
  `AppServiceProvider` (translation binding) to resolve via interface;
  orchestration depends on interface, not concrete worker client.
- Preserve envelope `1.0`, model identity persistence, Bearer auth, IDs-only payloads, text-only translation path.

## 7. Non-scope

External providers; vendor selection; chunking; fallback; Auto; client-side; external translation traffic; schema changes (escalate if claimed required); queue topology changes.

## 8. Architecture / behavioral contract

- `Transcription Orchestration → TranscriptionProvider → SelfHostedTranscriptionProvider → existing worker client → NormalizedTranscript`; mirror for translation/NLLB.
- Public ctors take scalars only; adapter-internal helpers (shape-builder/validator/error-map) private.
- Domain throws only `TranscriptionException` / `TranslationException`; worker `retryable` stays advisory-only.
- No routing logic in providers.

## 9. Failure semantics

Unchanged: manual-only retry (`tries=1`), 12-case/10-case taxonomies authoritative, stale recovery timeout+60s, terminal skip + newer-attempt guard, token/claim fencing. Unknown codes map into frozen taxonomies, never new states.

## 10. Security/privacy constraints

Ownership/authorize fences unchanged; no new egress (loopback worker only); no secrets in repo; no media bytes in queue/logs; translation stays text-only.

## 11. Observability/audit requirements

Preserve existing correlation (`request_id`/transcription/attempt/token) and provider/model identity logging. No new durable fields in T1; logs must not carry secrets or media content.

## 12. Compatibility requirements

Phase 2/3/5/6/7 unchanged: media identity/checksum/storage, normalized results, lifecycles, revision/staleness/export, ops audit.

Observable behavior parity rule (reconciled 2026-09-27): for the same input
and same existing self-hosted execution path, T1 must preserve the same
externally/domain-observable behavior. Where current outputs are
deterministic and directly comparable, exact equality is expected. Semantic
equality is permitted only for non-domain, non-observable representation
details introduced by interface/DTO wrapping. "Semantic parity" must not
change: normalized transcript content, timestamps, language metadata,
translation content, lifecycle states, retry/failure behavior,
revision/staleness behavior, or persistence semantics. No provider/model
code change is authorized by T1.

## 13. Acceptance criteria

- [ ] Both provider interfaces frozen with documented signatures; no signature drift vs cited files.
- [ ] `SelfHostedTranscriptionProvider` implements `TranscriptionProvider`; existing faster-whisper path passes through it.
- [ ] `SelfHostedTranslationProvider` implements `TranslationProvider`; existing NLLB path passes through it.
- [ ] Orchestration resolves the provider contract/interface and the
  self-hosted implementation is bound to that contract; the existing
  self-hosted worker behavior remains the only runtime path in T1; no
  direct concrete-worker dependency remains at the approved seam except
  within the self-hosted adapter; no routing/external-provider behavior
  is introduced (valid whether implementation uses rename, wrapper, or
  adapter).
- [ ] Parity suite: recorded self-hosted responses produce normalized outputs
  satisfying the §12 observable-behavior parity rule (exact equality on
  transcript content, segments, timestamps, language metadata, translation
  content, lifecycle/failure/revision/staleness/persistence observables;
  assert equality on text/segments/timestamps/language).
- [ ] Existing lifecycle/failure suites green; Phase 3/5/6 regression suites pass.
- [ ] No routing branch introduced (grep: no `external`/fallback selector in providers).
- [ ] No new external network call added (only loopback worker URLs in config/tests).

## 14. Testing requirements

Contract tests (interface conformance), parity fixtures (recorded worker envelopes), validator strictness tests, error-map exhaustiveness, IDs-only payload assertions, full suite + Pint + PHPStan. No live vendor calls.

## 15. Risks

Alias-vs-rename container breakage → pin with binding tests. Silent behavior drift → parity suite gates. Taxonomy flattening → exhaustiveness test.

## 16. Regression concerns

Phase 3 transcription, Phase 5 translation, Phase 6 revision/staleness/export (F-001 precedent), Phase 7 queue/timeout invariants. Any regression → STOP, fix within T1, no contract rewrite.

## 17. Rollback expectations

Code revert to pre-T1 bindings; no schema rollback (no schema change). Config unchanged.

## 18. Governance / authorization boundary

CONTRACT AUTHORING ONLY. No implementation authorized. Contract review
completed (`CONTRACT_VERIFIED — READY_ELIGIBLE`); HPO granted READY
promotion (`DECISION-PP-T1-READY-PROMOTION-001`). Implementation still
requires separate HPO execution authorization. Closes nothing.

## 19. READY eligibility conditions

Satisfied 2026-09-27: independent contract review clean (no
BLOCKER/HIGH on the contract; HIGH governance finding resolved via
ADR-027); no unresolved abstraction-shape decision; no Phase 1–7
contradiction; explicit HPO `BACKLOG → READY` promotion recorded.
T2/T3/T5 dependency: none introduced. Schema/vendor/client decisions:
none required for T1.

## 20. Exact next legal action

Perform PP-T1 readiness confirmation, then obtain separate HPO execution
authorization before implementation begins. No provider implementation
until then.
