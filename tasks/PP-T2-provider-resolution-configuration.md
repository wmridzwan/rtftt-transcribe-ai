# PP-T2 — Deterministic Provider Resolution & Configuration

## 1. Status

`DONE — CLOSED BY HPO (DECISION-PP-T2-CLOSURE-001)`

Decision record (2026-09-27): readiness `NOT_READY`
(`reviews/PP-T2-READINESS-REVIEW.md`; 0 BLOCKER, 2 HIGH, 3 MEDIUM, 1
LOW, 1 INFO). HPO resolved the blocking decisions
(`DECISION-PP-T2-KILL-SWITCH-001`, `DECISION-PP-T2-CONFIG-NAMING-001`)
and reconciled §§2/6/8/11/13/15/19/20. Fresh confirmation returned
`PP-T2 = READY-ELIGIBLE`
(`reviews/PP-T2-READINESS-CONFIRMATION.md`). HPO promoted
`BACKLOG → READY` (`DECISION-PP-T2-READY-PROMOTION-001`) and authorized
execution (`DECISION-PP-T2-EXECUTION-AUTHORIZATION-001`).

Execution record (2026-09-27): lifecycle `READY → IN_PROGRESS → REVIEW`.
Builder verification: new resolution suites 35/35 pass (62 assertions);
full suite 1143 (1138 passed, 5 pre-existing skips, 0 failures, clean
first run); Pint clean (1 auto-format); PHPStan 0 errors. Two
implementation-noted behaviors handled in tests without contract
change: `Log::spy()` is ineffective in this environment (used
`Log::listen` + `MessageLogged` instead); `.env` worker URLs target
127.0.0.1 so container-level HTTP tests pin localhost for fake
patterns. Transcription outbound `request_id` remains the frozen
`WorkerRequest`-minted value (test asserts preservation, not
propagation). PP-T1 binding tests migrated from singleton-identity to
per-resolution instance assertions per approved §8. Implementer claims
neither VERIFIED nor DONE. PP-T3–PP-T6 remain unauthorized.

Corrective cycle 1 (2026-09-27, independent review CHANGES_REQUESTED:
HIGH request_id-in-resolution-records, MEDIUM rejection-logging):
resolvers accept an optional log-context-only `?string $requestId`
(selection ignores it); all rejection paths log warnings without
secrets; both jobs add `provider_class` to the existing request-scoped
invocation-started lines (one additive key each, no behavior change);
§§8/11 amended accordingly. Re-verified: resolution suites 42/42 pass
(85 assertions); full suite 1150 (1145 passed, 5 pre-existing skips,
0 failures); Pint clean; PHPStan 0 errors. Still REVIEW; still no
VERIFIED/DONE claim.

Independent re-review (2026-09-27,
`reviews/PP-T2-CORRECTIVE-CYCLE1-RE-REVIEW.md`):
`PP-T2 (corrective cycle 1) = VERIFIED` on independently reproduced
evidence (AC1–AC8 PASS; scope audit clean; no BLOCKER/HIGH). Findings:
M-1 (MEDIUM, governance-process paper-trail gap — accepted
non-blocking, this re-review stands as the durable record) and L-1
(LOW, unrelated `LogContextTest` flake — no action). Lifecycle
`REVIEW → VERIFIED` evidenced by the re-review artifact.

Closure (2026-09-27, HPO `DECISION-PP-T2-CLOSURE-001`): HPO accepts
PP-T2; canonical transition `VERIFIED → DONE`. Full lifecycle history:
`READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`. PP-T3–PP-T6 remain
BACKLOG (PP-T4 DEFERRED); closure authorizes no later work.

Decision record (2026-09-27): readiness `NOT_READY`
(`reviews/PP-T2-READINESS-REVIEW.md`; 0 BLOCKER, 2 HIGH, 3 MEDIUM, 1
LOW, 1 INFO). HPO resolved the blocking decisions
(`DECISION-PP-T2-KILL-SWITCH-001`, `DECISION-PP-T2-CONFIG-NAMING-001`)
and reconciled §§2/6/8/11/13/15/19/20. Fresh confirmation returned
`PP-T2 = READY-ELIGIBLE`
(`reviews/PP-T2-READINESS-CONFIRMATION.md`; all ACs PASS-testable, no
unresolved decision, no new blocking finding). HPO promoted
`BACKLOG → READY` (`DECISION-PP-T2-READY-PROMOTION-001`, 2026-09-27).
Review artifacts themselves unchanged. No implementation authorized;
execution requires a separate HPO decision.

## 2. Objective

Define deterministic per-domain provider selection + configuration with observable identity, safe failure, self-hosted default, translation locked to self-hosted, and a deployment-free kill switch. No automatic fallback.

"Deployment-free" means no code deploy or release; the decided kill-switch
mechanism (env flag + documented Artisan refresh, see §6) is operations,
explicitly permitted.

## 3. Why

HPO requires explicit routing ownership (`ProcessingPolicy → ProviderResolver → provider`, per domain) so overflow capability can never become silent rerouting.

## 4. Dependencies

Requires: PP-T1 contract complete (implementation dependency: T1 VERIFIED/DONE before T2 implementation unless canonical policy allows a reviewed combined wave; prefer sequential).

## 5. Inputs / frozen contracts

T1 interfaces; `ARCHITECTURE-PLAN-OPTION1.md` §§2,5,14; queue/timeout invariant `provider<job<retry_after`; translation Wave-1 lock (self-hosted NLLB).

## 6. Scope

- Per-domain resolver (`TranscriptionProviderResolver`, `TranslationProviderResolver`) at orchestrator seam; deterministic read of the canonical selection keys below.
- Canonical key table (decided `DECISION-PP-T2-CONFIG-NAMING-001`; reconciled 2026-09-27):

  | Purpose | Config path | Env | Default | Allowed values |
  |---|---|---|---|---|
  | Transcription selection | `transcription.provider_selection` | `RTFTT_TRANSCRIPTION_PROVIDER_SELECTION` | `'self_hosted'` | `'self_hosted'`, `'external_<name>'` (binding must exist) |
  | Translation selection | `translation.provider_selection` | `RTFTT_TRANSLATION_PROVIDER_SELECTION` | `'self_hosted'` | `'self_hosted'` only in Wave 1 |
  | Kill switch | `processing.external_kill_switch` | `RTFTT_PROCESSING_EXTERNAL_KILL_SWITCH` | `false` | boolean-ish (`true`/`1`/`false`/`0`) |

  Existing `translation.provider` (env `RTFTT_TRANSLATION_PROVIDER`,
  default `'self-hosted'`) is UNCHANGED as the provider identity label
  for logging/persisted identity; it never drives class selection. Each
  key's code comment must cross-reference the other key's distinct
  purpose. Values use `self_hosted` (underscore) for selection;
  the label's `self-hosted` (hyphen) spelling is historical and untouched.
- Config keys + env convention per the table above; boot validation (unknown selection value / missing URL+token when external / invariant violation / unpinned model → boot exception).
- Provider identity stamping (`provider_key`/`model_pinned`/`request_id`) into logs/context (durable columns only if separately approved — default logs/context). The stamped `request_id` is the invocation's existing `requestId` (see §8); resolvers mint nothing.
- Kill switch (decided `DECISION-PP-T2-KILL-SWITCH-001`): env flag above. Enabled → both resolvers return self-hosted adapters regardless of selection keys, and log engagement (no secrets). Disabled/missing → selection keys honored. Invalid value → fail closed as engaged (force self-hosted) with a warning naming the value. Evaluated on every interface resolution (no cached selection across resolutions). Refresh: operator changes env, then runs the documented refresh (`php artisan config:clear`, or `config:cache` where used); long-lived workers pick it up on restart/recycle per normal Laravel semantics.
- T2 implements the kill-switch mechanism; T5 owns the operational procedure/runbook/alerting around the same mechanism (see PP-T5 §6 cross-reference).

## 7. Non-scope

Live vendor integration; external API code; client processing; automatic routing/benchmarking; pricing/billing; dynamic selection; new queues.

## 8. Architecture / behavioral contract

- Same input+config → same provider, 100% deterministic; resolvers are pure selection (no inference, no network).
- Providers never call each other; resolver lives beside ServiceProvider, one per domain.
- Binding shape (pinned): the resolver is invoked only from within `TranscriptionServiceProvider::register()` / `AppServiceProvider::register()`; no other call site may select a concrete provider. Selection is re-evaluated on every interface resolution — the interface binding must not cache a selected provider across resolutions (e.g. `bind`, not a cached `singleton` selection); the resolver may return shared adapter instances. Resolver selection takes no request-scoped arguments for routing and must not inspect `request_id` (or any payload/user/media content) for routing — `request_id` is correlation only. `resolve()` accepts one optional log-context parameter (`?string $requestId = null`) that is recorded in resolution log records when provided and is never read for selection; the container path passes nothing. Unknown/unsupported selection rejections are logged (warning, selection value, no secrets) before throwing.
- Orchestration and jobs keep depending only on the frozen `TranscriptionProvider` / `TranslationProvider` interfaces (unchanged from PP-T1); frozen interfaces are not modified.
- Translation resolver accepts only `self_hosted` in Wave 1 (any other value → validation failure).
- The invocation's existing `requestId` (minted once by `TranscriptionInvocation::create()` / `TranslationInvocation::create()` via `Str::uuid()`) is preserved and propagated through Invocation/outbound/logs. PP-T2 must not mint a second logical request identity. Selection/audit behavior uses the existing invocation identity. Do not conflate it with the job-level `httpRequestId` (`ProcessTranscription` / `ProcessTranslation`), which is a distinct transport field.

## 9. Failure semantics

Invalid provider value → validation/boot failure (fail closed). Missing credentials when external selected → fail closed, mapped taxonomy code, no fallback attempt. External disabled via kill switch → self-hosted path serves; in-flight external attempts follow existing manual-retry/stale rules.

## 10. Security/privacy constraints

No silent fallback in either direction; external path requires explicit config; secrets env-only, never logged/committed; selection changes attributable (who/what/when via config + logs).

## 11. Observability/audit requirements

Every resolution logs: `request_id` (the invocation's existing `requestId`, never a resolver-minted value), domain, selected `provider_key`, `model_pinned`, config source; unknown-value rejections logged without secrets. Request-scoped correlation is completed at the job layer: the existing per-request `provider invocation started` log lines (which already carry `request_id`) additionally record the resolved `provider_class`, so every request joins request identity with the selected provider. Dashboards/alerts defined in T5; T2 emits the fields.

## 12. Compatibility requirements

Default config routes exactly today's behavior. No lifecycle/queue/schema change. Phase 1–7 untouched.

## 13. Acceptance criteria

- [ ] Default config selects `self_hosted` for both domains (assert in test with stock env).
- [ ] Explicit `transcription.provider_selection=external_x` selects the named external binding when configured (fixture binding, no network).
- [ ] Invalid provider value fails validation/boot with a named error (assert message/code).
- [ ] Missing URL/token for selected external fails closed (no fallback attempt observed).
- [ ] Translation with non-`self_hosted` value is rejected.
- [ ] Kill switch: with `RTFTT_PROCESSING_EXTERNAL_KILL_SWITCH` unset/`false`, resolvers honor the selection keys; with it set to `true`/`1` followed by the documented refresh (`php artisan config:clear`), both domains resolve to the self-hosted adapters (assert resolution output for transcription and translation); with an invalid value (e.g. `maybe`), both domains resolve to self-hosted and a warning naming the value is logged (assert resolution output + warning, assert no secret logged).
- [ ] Provider identity (`provider_key`/`model_pinned`/`request_id`) present in logs/context for each request (assert fields, assert no secret present).
- [ ] No silent fallback: fault-injected self-hosted failure never triggers external call (assert zero external invocations).

## 14. Testing requirements

Resolver unit tests (deterministic matrix), config validation tests, kill-switch tests, identity-stamping tests, secret-absence assertions, full suite + Pint + PHPStan. No network egress in tests.

## 15. Risks

Flag-name drift → canonical key table in §6 (decided `DECISION-PP-T2-CONFIG-NAMING-001`). Kill-switch bypass via cached config → refresh/reload test per the §6 refresh procedure. Translation lock bypass → negative test. Existing identity-label key confusion → §6 side-by-side purposes + code-comment cross-references.

## 16. Regression concerns

T1 parity must stay green; queue/timeout invariants; Phase 3/5 orchestration tests. T2 must not alter normalized outputs.

## 17. Rollback expectations

Config flip to self-hosted; no schema rollback. Document flag + refresh procedure.

## 18. Governance / authorization boundary

CONTRACT AUTHORING ONLY. No implementation. READY only after T1 dependency satisfied + contract review + HPO promotion.

## 19. READY eligibility conditions

T1 VERIFIED/DONE (satisfied `DECISION-PP-T1-CLOSURE-001`); contract review clean; config-key names reconciled with repo conventions (satisfied `DECISION-PP-T2-CONFIG-NAMING-001`, canonical table in §6); kill-switch mechanism approved by HPO (satisfied `DECISION-PP-T2-KILL-SWITCH-001`, contract in §6). Remaining: fresh readiness confirmation + explicit HPO READY promotion.

## 20. Exact next legal action

Perform a fresh PP-T2 readiness confirmation against this reconciled contract. Do not implement PP-T2. No READY promotion and no execution authorization until the fresh confirmation returns READY-eligible and the HPO explicitly promotes.
