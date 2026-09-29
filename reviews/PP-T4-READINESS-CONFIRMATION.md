# PP-T4 — Readiness Confirmation (fresh, post-reconciliation)

Date: 2026-09-28. Basis: reconciled `tasks/PP-T4-external-translation-provider.md`
(after `DECISION-PP-T4-DEFERMENT-RELEASE-001` +
`DECISION-PP-T4-CONTRACT-RECONCILIATION-001`). This confirmation does not
rely on the earlier review alone; every item below was re-checked against the
current contract text, live source, and governance records.

## 1. Finding disposition re-verification

| Finding | Severity | Disposition |
|---|---|---|
| H-1 vendor precondition | HIGH | RESOLVED — §2 single fixture-shaped reference adapter; per-vendor gated to addendum + HPO auth (§18); vendor selection stays deferred |
| H-2 construction/config contract | HIGH | RESOLVED — §8 scalar ctor + named `ReferenceExternalTranslationTransport` seam, no new global keys; tests inject fixtures |
| H-3 translation-lock conflict | HIGH | RESOLVED — §8 scoped `external_reference` fixture selection only; default self-hosted; kill-switch/fail-closed preserved; general unlocking forbidden (§§7/18) |
| M-1 error-map table | MEDIUM | RESOLVED — §9 canonical table, `ProviderFailed` unknown-default per frozen `failureFromCode`, AC5 asserts codes + retryability |
| M-2 alignment enforcement | MEDIUM | RESOLVED — §8 frozen validator + writer path; single-shot 1:1; chunking explicitly N/A; AC3/AC4 |
| M-3 retry/idempotency | MEDIUM | RESOLVED — §9 no internal retry/fallback; new-parent-attempt only; writer token fencing; reused requestId; AC10 |
| M-4 timeout value | MEDIUM | RESOLVED — §6/§8 honor 300s provider ceiling, no new keys |
| M-5 ceiling source/error | MEDIUM | RESOLVED — §6/§8 ctor scalars (`maxSegments`/`maxPayloadChars`); breach → `InvalidRequest`, AC7 |
| M-6 log checklist | MEDIUM | RESOLVED — §11 checklist embedded, AC6 asserts presence + absence |
| M-7 kill-switch AC | MEDIUM | RESOLVED — §8 + AC9 zero-invocation assert, PP-T2 mechanism reused |
| M-8 language rules | MEDIUM | RESOLVED — §8 source/target rules incl `und`; AC3 fixtures |
| M-9 T4/T5 boundary | MEDIUM | RESOLVED — §7/§15 T4 enforces + emits, T5 owns procedure/alerts |
| M-10 staleness rule | MEDIUM | RESOLVED — §8 additive-staleness rule; AC12; F-001 gate in §16 |
| L-1 fixture corpus | LOW | RESOLVED — §14 corpus enumerated |
| L-2 no-migration | LOW | RESOLVED — §7 explicit STOP + escalate |
| L-3 determinism rule | LOW | RESOLVED — §6/§13 versioned rerun rule, AC11 |
| I-1 binding name | INFO | NOTED — test-only detail, no action |

No unresolved BLOCKER, HIGH, or readiness-blocking MEDIUM remains.

## 2. Deferment release legality

- Original basis: ADR-027 Wave-1 exclusion
  (`DECISION-PROCESSING-PROVIDER-OPTION1-001`); PP-T4 §§18–19.
- Release condition reconciled: T1/T2-stable EXCEEDED (T1/T2/T3 DONE);
  Step-1 HPO prompt is the later wave authorization for governance;
  vendor-selection clause narrowed (H-1) with vendor selection staying
  deferred — no product decision invented.
- Release recorded: `DECISION-PP-T4-DEFERMENT-RELEASE-001`. PP-T5/PP-T6
  explicitly remain BACKLOG / NOT AUTHORIZED therein.

## 3. Confirmation checklist

- Deferment legally released (decision above); no other wave authorization
  claimed.
- Dependencies satisfied: PP-T1 DONE + PP-T2 DONE + PP-T3 DONE (closure
  decisions verified in `DECISION_QUEUE.md`; independent VERIFIED reviews on
  file). No combined-wave exception needed.
- Contract testable: AC1–AC12 are behavioral and independently reproducible
  (fixture/fake transport, zero network, empty env secrets); assertions name
  exact codes, fields, and counts — no class-name-only criteria except the
  wiring assert in AC1, where the T2-resolver seam is itself the contract.
- Scope bounded: §6 allowed surface (5-file planned surface + scoped resolver
  amendment stated) vs §7 non-scope; hard PP-T5/PP-T6 boundary (§E of the
  review); no later work authorized.
- Protected surfaces identified: frozen T1 translation interfaces/DTOs/
  validator/taxonomy/lifecycle/writer, T2 resolver seam + fail-closed +
  kill-switch + request identity, queue/lifecycle/schema freeze, transcription
  path + PP-T3 suites, Phase 1–7 contracts.
- Decisions durable: pre-existing (ADR-027, OPTION1-001, T2 kill-switch/
  naming, ADR-022 lifecycle/writer/staleness, ADR-018 retry rules, T3 pattern)
  reused, not reopened; new release + reconciliation recorded as
  `DECISION-PP-T4-DEFERMENT-RELEASE-001` +
  `DECISION-PP-T4-CONTRACT-RECONCILIATION-001`.
- No implementation begun: verified — no `ReferenceExternalTranslation*`
  production class exists; `ExternalTranslationProvider` named only in
  `tasks/PP-T4-*`.
- Exclusions explicit: §7 + §18 (no vendor calls/egress, no per-vendor
  classes, no general unlocking, no migration, no queue/lifecycle/
  transcription changes, no PP-T5/PP-T6).

## 4. Verdict

`PP-T4 = READY-ELIGIBLE`. Eligible for HPO promotion `BACKLOG → READY` and for
the Step-1 execution authorization (`DECISION-PP-T4-EXECUTION-AUTHORIZATION-001`
form). Promotion and authorization are separate HPO records, not granted by
this confirmation.
