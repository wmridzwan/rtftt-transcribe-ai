# PP-T5 — Privacy, Audit, Kill-Switch & Operational Controls

## 1. Status

`DONE — CLOSED BY HPO (DECISION-PP-T5-CLOSURE-001)`

Step-2 execution record (2026-09-28): lifecycle
`READY → IN_PROGRESS → REVIEW` under
`DECISION-PP-T5-EXECUTION-AUTHORIZATION-001`, strictly within this contract.
Implementation: 0 runtime files (contract: code owns nothing new); 5 focused
test files (38 tests, 243 assertions) under
`tests/Feature/ProcessingProvider/`; executable runbook
`verification/pp-t5/PP-T5-OPERATIONAL-RUNBOOK.md` (19 sections).
Builder verification: focused 38/38 pass; PP-T1/PP-T2 translation + resolver +
PP-T3/PP-T4 suites green (106/106 across the six T2/T3/T4 focused files);
full suite 1252 (1247 passed, 5 pre-existing skips, 0 failures on clean runs;
two runs hit the known pre-existing order-dependent `LogContextTest` flake,
passes in isolation 6/6 and on rerun — recorded, not concealed); Pint clean;
PHPStan 0 errors. AC1–AC10 all PASS on executed evidence. Scope audit clean
(no provider/resolver/config-key/queue/lifecycle/migration/vendor/scheduler/
alerts-platform/spend change; frozen interfaces untouched; PP-T6 untouched).
Builder claims neither VERIFIED nor DONE.

Independent review (2026-09-28, `reviews/PP-T5-INDEPENDENT-REVIEW.md`):
`PP-T5 = VERIFIED` on independently rerun evidence (AC1–AC10 PASS; scope
audit clean; no BLOCKER/HIGH/MEDIUM). Corrective cycle 1: one LOW
(PP-T5-REV-01: unused test-side alert-key map) fixed with an AC7
contract-traceability test; re-verified 39/39. Lifecycle `REVIEW → VERIFIED`
evidenced by the review artifact.

Closure (2026-09-28, HPO `DECISION-PP-T5-CLOSURE-001`): HPO accepts PP-T5;
canonical transition `VERIFIED → DONE`. Full lifecycle history:
`READY → IN_PROGRESS → REVIEW → VERIFIED → DONE`. PP-T6 remains
BACKLOG / NOT AUTHORIZED / FINAL_GATE_ONLY; closure authorizes no later
work.

Step-1 history (2026-09-28; governance/readiness only, no implementation):

- Readiness review `reviews/PP-T5-READINESS-REVIEW.md`: `NOT_READY`
  (0 BLOCKER, 4 HIGH, 7 MEDIUM, 2 LOW, 1 INFO — all contract-precision gaps
  plus one stale-scope gap: the 89-line draft predates PP-T4 DONE and governs
  transcription only).
- Reconciliation `DECISION-PP-T5-CONTRACT-RECONCILIATION-001`: dual-domain
  scope (H-1); log-emission alerts with no quota/third-party platform (H-2);
  zero new global config keys, zero new enforcement code (H-3); existing
  ceilings verified, no new rejection paths (H-4); pinned kill-switch AC (M-1),
  egress-attribution fields (M-2), executable runbook sections + clean state
  (M-3), informational-only spend (M-4), frozen identity reuse (M-5),
  dual-domain secrets + rotation (M-6), degraded-mode split (M-7); LOWs
  resolved inline; §§1/2/4/6/7/8/9/10/11/13/14/15/17/19/20 amended. Review
  artifact itself unchanged. No new ADR (architecture unchanged).
- Fresh confirmation `reviews/PP-T5-READINESS-CONFIRMATION.md`:
  `READY-ELIGIBLE` (no unresolved BLOCKER/HIGH/MEDIUM).
- Promotion `DECISION-PP-T5-READY-PROMOTION-001`: `BACKLOG → READY`.
- Execution authorization `DECISION-PP-T5-EXECUTION-AUTHORIZATION-001`:
  PP-T5 may move `READY → IN_PROGRESS` when work begins, strictly within this
  contract. PP-T6 remains unauthorized.

## 2. Objective

Define and build the cross-cutting operational controls — privacy/egress
attribution, audit identity, kill-switch procedure + rollback rehearsal,
secrets handling, operator safety-ceiling verification, and synthetic-fault
alert verification — for BOTH reference external paths (PP-T3 transcription
reference adapter and PP-T4 translation reference adapter), as procedure,
verification tests, and runbook artifact only, before either external path
can ever be enabled beyond fixtures.

Reconciled 2026-09-28 (H-1): the prior draft scoped transcription only. With
PP-T4 DONE, PP-T5 governs both domains; translation is included on the same
fixture-scoped terms (no general unlocking, no live traffic).

## 3. Why

Overflow capability without governance would permit hidden egress and unmanageable cost/incident surface. T5 is the safety gate between T3/T4 capability and T6 verification.

## 4. Dependencies

Implementation requires PP-T1 DONE + PP-T2 DONE + PP-T3 DONE + PP-T4 DONE
(all satisfied: `DECISION-PP-T1-CLOSURE-001`,
`DECISION-PP-T2-CLOSURE-001`, `DECISION-PP-T3-CLOSURE-001`,
`DECISION-PP-T4-CLOSURE-001`). Planning inputs:
`ARCHITECTURE-PLAN-OPTION1.md` §§5/8/9/14/15; `EXTERNAL-LANDSCAPE.md`;
`PRIVACY-DATA-MOVEMENT.md` (J01–J03); PP-T3 reconciled contract (chunk log
checklist, §9 error table, T3-enforces/T5-owns split); PP-T4 reconciled
contract (request log checklist, §9 error table, scoped `external_reference`,
T4-enforces/T5-owns split). No Phase 5/6/7 dependency beyond reusing the
existing log infrastructure read-only. No environment, vendor, or
infrastructure decision required (fixture-based, zero egress, empty env
secrets). Vendor selection stays deferred per ADR-027.

## 5. Inputs / frozen contracts

T2 identity fields + flags; T3 log-reconstruction checklist; T4 log-reconstruction
checklist; `PRIVACY-DATA-MOVEMENT.md` (J01–J03); loopback-only current posture;
`prepared_audio_retention='ephemeral'`; backup node-local 7 generations;
PP-T2 kill-switch mechanism + refresh procedure
(`DECISION-PP-T2-KILL-SWITCH-001`); PP-T2 canonical selection keys
(`DECISION-PP-T2-CONFIG-NAMING-001`); queue/timeout invariant
(provider 300 < job 330 < retry_after 420); P7-005 `LogContext`
best-effort/never-throw observability.

## 6. Scope

- Privacy/egress-attribution audit for both domains: every external-attempt
  record attributable to the explicit selection that caused it via existing
  fields (`provider_key`, `config_source`, `kill_switch_engaged`,
  invocation `requestId`, `provider_class` on the PP-T2 job-layer lines, plus
  the T3 per-chunk / T4 per-request checklist records); no secrets, media
  bytes, or full text in any record (M-2/M-5).
- Kill-switch operational procedure + rollback rehearsal around the PP-T2
  mechanism (reconciled M-1/M-3): exact flag, refresh, and verification steps
  in the runbook; rehearsal test proving self-hosted-only restoration without
  migration. T2 implements the mechanism — `DECISION-PP-T2-KILL-SWITCH-001`;
  T5 owns the operational procedure/runbook/alerting around the same
  mechanism.
- Secrets handling procedure (reconciled M-6): env-only, fail closed before
  any dispatch in both domains, never logged/committed/persisted; rotation
  procedure requiring no code change.
- Safety-ceiling VERIFICATION (reconciled H-4): assert the existing
  adapter-internal ceilings (T3 `maxRequestBytes` → `MediaRejected`; T4
  `maxSegments`/`maxPayloadChars` → `InvalidRequest`) and the 300s
  provider-ceiling honoring; concurrency/timeout operational guidance in the
  runbook against the 300/330/420 invariant. No new rejection code, no
  pricing-policy invention.
- Synthetic-fault alert verification (reconciled H-2): structured-log alert
  emission for invalid selection, missing credentials, forbidden external
  state, ceiling breach, and saturation/timeout anomaly (grounded in the
  T3/T4 §9 rows); emission (log record) vs delivery (existing log
  infrastructure) vs operator response (runbook) split explicit.
- Minimum run-level audit reaffirmed as logs-only per ADR-027 (reconciled
  L-1): no durable table, no migration; any claimed need → STOP + HPO
  escalation.
- Spend monitoring INFORMATIONAL ONLY (reconciled M-4): no budgets, no
  billing, no hard limits, no per-provider/per-user thresholds; ceilings +
  kill-switch are safety controls, not spend enforcement; runbook records a
  non-blocking future spend-review trigger.

## 7. Non-scope

Cost optimization; hard spend limits; billing integration; dynamic selection;
automatic routing/failover/retry schedulers; end-user billing; client trust
protocol; per-vendor classes; vendor selection and any live
credentials/traffic/customer media-or-text egress; durable chunk table or any
migration (escalation path only); third-party alert-delivery platform
(Slack/PagerDuty/Opsgenie or equivalent — I-1); Auto/Local UI; queue/lifecycle
redesign; any change to frozen T1/T2/T3/T4 behavior or config keys;
integration-gate execution/verdict (PP-T6 FINAL_GATE_ONLY — reconciled L-2).

## 8. Architecture / behavioral contract

- Controls are procedure/verification surfaces, not provider logic: resolvers
  read flags; operators flip kill-switch; adapters emit identity/logs; no
  provider self-disables or reroutes. PP-T5 adds ZERO new enforcement code
  and ZERO new global config keys (reconciled H-3); all thresholds/keys are
  the existing ones (§5).
- Kill-switch procedure (reconciled M-1): set
  `RTFTT_PROCESSING_EXTERNAL_KILL_SWITCH=true` (or `1`), run the PP-T2
  refresh (`php artisan config:clear`, or `config:cache` where used;
  long-lived workers restart/recycle per normal Laravel semantics), then run
  the runbook verification queries. New dispatches in both domains resolve
  self-hosted (transcription selection and translation `external_reference`
  alike); invalid flag values fail closed as engaged with a warning naming
  the value; in-flight attempts drain under existing manual-retry/stale
  rules.
- Secrets: absent → fail closed before any egress attempt in both domains
  (`ConfigurationError`, zero dispatch); rotation (env rotate → refresh →
  verify) needs no code change (reconciled M-6).
- Alerts are log records, not a platform: warning-level structured records
  with named alert fields, emitted through the existing Laravel Log
  infrastructure; tests observe them via `Log::listen` + `MessageLogged`
  (the PP-T2-corrective precedent). No new event bus, table, or sink.
- Identity reuse (reconciled M-5): the invocation's existing `requestId`
  (minted once by `TranscriptionInvocation::create()` /
  `TranslationInvocation::create()`); resolver `?string $requestId`
  log-context-only, never routing; job-level `httpRequestId` untouched and
  never redesigned; no second logical identity minted anywhere.

## 9. Failure semantics

Invalid/forbidden external state → fail closed with mapped code
(`ProviderResolutionException` at selection; `ConfigurationError` /
taxonomy-mapped codes at dispatch); kill-switch engaged mid-run → new work
self-hosted in both domains, in-flight follows manual-retry/stale rules;
ceiling breach → reject with the EXISTING named error (`MediaRejected` /
`InvalidRequest`), no partial dispatch, no new error code.
Degraded mode (reconciled M-7): metrics/log/alert-sink unavailability NEVER
changes provider selection — PP-T2 fail-closed and kill-switch semantics are
preserved, citing the P7-005 `LogContext` best-effort/never-throw contract;
invalid config → fail closed; provider unhealthy → existing taxonomy codes +
manual-retry/stale rules; credentials missing → fail closed before dispatch.

## 10. Security/privacy constraints

No hidden provider use (every external call attributable to explicit selection
+ identity log in both domains); logs redact secrets/media bytes/full text
(full translated text absent alongside transcript text); off-host backup copy
treated as egress; hosted switch needs ADR + consent lineage (future).
Secret-absence surfaces asserted in tests: all log records, committed
fixtures (env secrets empty), no DB persistence of secrets, no env dumping,
provider error payloads leak no credentials. Ownership/authorize fences
unchanged; translation stays text-only.

## 11. Observability/audit requirements

Per-request (both domains): invocation `requestId` (reused),
`domain` (transcription/translation), `provider_key`, `model_pinned`,
`config_source`, `kill_switch_engaged`, `provider_class` (job-layer lines),
domain ids (transcription/media/processing-job ids; translation/
transcription ids + target language), outcome, latency ms, failure category.
Per-chunk (transcription only, T3 checklist, logs-only): chunk id, offset
window, attempt + `attemptSeq`, outcome, timing. Per-request translation
audit uses the T4 checklist (no chunk fan-out exists). Secrets, media bytes,
and full text absent from all records (asserted).
Alert emission set (reconciled H-2): invalid selection, missing credentials,
forbidden external state, ceiling breach, saturation/timeout anomaly —
warning-level records carrying `alert_key`, domain, `provider_key`,
`request_id` (where available), outcome/failure category, and no secrets;
delivery is the existing log infrastructure; operator response is the
runbook. No quota alert (no quota source exists). If review proves logs
insufficient → STOP, escalate, no silent schema (ADR-027).

## 12. Compatibility requirements

Self-hosted path stays valid; retention/storage/backup topology unchanged;
Phase 7 diagnostics truthful (no ad-hoc log substitute for required durable evidence — if required evidence missing → escalate, don't fake).
Downstream domains never branch on vendor; Phase 2/3/5/6/7 invariants hold;
machine source immutable; revision/staleness/export unchanged (incl. the
F-001 active-revision guard); PP-T1 parity and PP-T2 resolution suites stay
green.

## 13. Acceptance criteria

- [ ] AC1 — Identity/audit fields present and queryable per request in both
  domains (assert the reconciled §11 field set on resolution + job-layer +
  T3 per-chunk / T4 per-request records; assert zero secrets/media bytes/
  full text).
- [ ] AC2 — Egress attribution: each external-attempt record links to the
  explicit selection that caused it via `provider_key` + `config_source` +
  `request_id` (+ `provider_class` on job lines) in both domains (assert the
  field join; assert absence rules).
- [ ] AC3 — Kill-switch procedure: flip → PP-T2 refresh → both domains
  resolve self-hosted (transcription selection and translation
  `external_reference` alike) with zero new external attempts; invalid value
  fails closed with warning; in-flight drains under manual-retry/stale rules
  (assert resolution output + warning + zero adapter invocations).
- [ ] AC4 — Rollback rehearsal: restore self-hosted-only operation without
  migration (run the runbook; assert clean state: both selections
  `self_hosted`, no migration, fixture bindings present-but-unselected, zero
  new external attempts after refresh).
- [ ] AC5 — Secrets: absent-credential runs in BOTH domains fail closed with
  `ConfigurationError` and zero egress attempts (assert code + zero
  dispatch per domain); rotation procedure verified without code change.
- [ ] AC6 — Ceilings: oversized transcription request rejected with
  `MediaRejected`, oversized translation request rejected with
  `InvalidRequest`, 300s provider-ceiling honoring asserted (assert codes,
  zero partial dispatch; no new rejection paths).
- [ ] AC7 — Alerts fire on synthetic invalid-selection, missing-credential,
  forbidden-state, ceiling-breach, and saturation/timeout-anomaly faults
  (assert each `alert_key` record via `Log::listen`; assert no secret
  logged).
- [ ] AC8 — Frozen-surface audit: no new global config keys, no resolver/
  adapter/queue/lifecycle/schema change (assert; PP-T1/PP-T2/PP-T3/PP-T4
  suites green).
- [ ] AC9 — Spend informational-only: no budgets, billing, hard limits, or
  thresholds exist in code or config (assert absence; runbook carries the
  future spend-review trigger only).
- [ ] AC10 — Runbook artifact exists at
  `verification/pp-t5/PP-T5-OPERATIONAL-RUNBOOK.md` with all reconciled §11
  sections and rehearsal evidence cited (assert sections + evidence paths).

## 14. Testing requirements

Control-plane tests (flags, kill-switch both domains incl. invalid value,
existing-ceiling asserts), identity/log assertions per domain incl.
secret/media/full-text absence scans, synthetic-fault alert tests
(`Log::listen`), rehearsal runbook test, frozen-surface audit (config-key
and seam grep), minimal fixture corpus reusing T3/T4 fixtures (no live
credentials, env secrets empty, zero network calls), full suite + Pint +
PHPStan.

## 15. Risks

Cached-config bypass → refresh test per the PP-T2 procedure. Log-forwarder
egress → env audit + redaction test. Alert fatigue → scoped 5-alert set
(H-2), no quota noise. Cost overrun → existing ceilings + kill-switch
procedure + non-blocking future spend-review trigger (informational only,
M-4). Dual-domain drift → every AC asserted per domain. Gate-as-development
→ charter guard (PP-T6 owns the gate, not PP-T5).

## 16. Regression concerns

T1–T4 suites green (parity, resolution incl. fail-closed/kill-switch, both
reference adapters); Phase 3/5/6 suites (normalization, lifecycle, NLLB
identity/staleness, revision/invalidation/history/export F-001 guard);
Phase 7 ops tests; no change to normalized outputs or lifecycles.

## 17. Rollback expectations

Kill-switch IS the rollback: flip → PP-T2 refresh → self-hosted; no
schema/data rollback (no migration shipped). The runbook
(`verification/pp-t5/PP-T5-OPERATIONAL-RUNBOOK.md`, created in Step 2)
documents prerequisites, enablement, verification queries, kill-switch
procedure, rollback rehearsal, credential rotation, health verification,
incident response, recovery validation, and closure criteria (reconciled
M-3); operator steps stay separate from application behavior.

## 18. Governance / authorization boundary

Contract authoring complete; execution authorized
(`DECISION-PP-T5-EXECUTION-AUTHORIZATION-001`, 2026-09-28) strictly within
this reconciled contract. Still forbidden without fresh authorization:
vendor calls, customer media-or-text egress, spike execution, per-vendor
classes, general translation unlocking, third-party alert-delivery
integration, billing/spend enforcement, schema/migration, queue/lifecycle
redesign, transcription/translation behavior changes, PP-T6 gate execution.
PP-T6 remains FINAL_GATE_ONLY and unauthorized.

## 19. READY eligibility conditions

Satisfied 2026-09-28: PP-T1 DONE + PP-T2 DONE + PP-T3 DONE + PP-T4 DONE (all
closure decisions recorded; no combined-wave exception needed); contract
review clean (fresh confirmation `READY-ELIGIBLE`, no BLOCKER/HIGH/MEDIUM);
dual-domain scope reconciled (H-1); log-emission alert contract with no
platform and no quota invention (H-2); zero new global keys and zero new
enforcement code (H-3); existing-ceilings-only verification (H-4);
kill-switch/attribution/runbook/spend/identity/secrets/degraded rules pinned
(M-1..M-7); no-migration explicit (L-1); PP-T6 boundary explicit (L-2).
Promotion recorded (`DECISION-PP-T5-READY-PROMOTION-001`).

## 20. Exact next legal action

Begin PP-T5 — Step 2: Build, Verify & Close (procedure/tests/runbook strictly
within this contract; builder moves `READY → IN_PROGRESS → REVIEW`; no
VERIFIED/DONE claim by the builder; PP-T6 remains unauthorized).
