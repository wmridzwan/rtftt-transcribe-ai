# PP-T5 — Provider Operational Controls Runbook

Authority: `tasks/PP-T5-provider-operational-controls.md` (reconciled
`DECISION-PP-T5-CONTRACT-RECONCILIATION-001`). Scope: procedure + verification
only. PP-T5 adds zero runtime code, zero config keys, zero enforcement paths.
All commands below match this repository on Windows PowerShell unless noted.

## 1. Purpose and scope

Prove operational readiness of BOTH fixture-scoped reference external paths —
transcription (`ReferenceExternalTranscriptionProvider`, PP-T3) and translation
(`ReferenceExternalTranslationProvider` with the scoped `external_reference`
selection, PP-T4) — without enabling any live vendor traffic. Covers
kill-switch procedure, rollback rehearsal, privacy/zero-egress verification,
secret handling and rotation, existing safety ceilings, synthetic-fault alert
emission, correlation fields, informational spend guidance, and
degraded-observability behavior. General translation unlocking, vendor
selection, live credentials, billing enforcement, and the PP-T6 final gate are
explicitly out of scope (§19).

## 2. Preconditions

- Checkout at a revision containing the PP-T1–PP-T4 DONE contracts.
- PHP 8.4 CLI available (`php -v` reports 8.4.x).
- Composer dependencies installed (`vendor/bin/pest.bat` exists).
- `.env` provider secrets EMPTY (see §10 check).
- Selections at stock defaults (`self_hosted` / disengaged kill-switch) unless
  a procedure step says otherwise.
- No migration pending (`php artisan migrate --pretend` shows nothing
  provider-related; PP-T5 ships no migration).

## 3. Supported reference paths

| Domain | Selection value | Binding | Adapter |
|---|---|---|---|
| transcription | `external_reference` | `transcription.providers.external_reference` (test/fixture only) | `ReferenceExternalTranscriptionProvider` |
| translation | `external_reference` | `translation.providers.external_reference` (test/fixture only) | `ReferenceExternalTranslationProvider` |

Defaults are `self_hosted` in both domains. Any other translation value is a
validation failure; any other transcription value requires an existing
container binding or it fails closed. No binding exists in production code
(verified by `PpT5DegradedObservabilityAuditTest`: no production external
bindings by default).

## 4. Required config/state inspection

Read-only inspection commands (single quotes per repo tinker convention):

```powershell
php artisan tinker --execute 'config("transcription.provider_selection");'
php artisan tinker --execute 'config("translation.provider_selection");'
php artisan tinker --execute 'config("processing.external_kill_switch");'
php artisan tinker --execute 'config("transcription.timeout_seconds");'
php artisan tinker --execute 'config("translation.timeout_seconds");'
```

Expected stock state: `self_hosted`, `self_hosted`, `false` (or unset), `300`,
`300`. Canonical keys (PP-T2 naming, frozen by AC8 — no other keys exist):

- `transcription.provider_selection` (`RTFTT_TRANSCRIPTION_PROVIDER_SELECTION`)
- `translation.provider_selection` (`RTFTT_TRANSLATION_PROVIDER_SELECTION`)
- `processing.external_kill_switch` (`RTFTT_PROCESSING_EXTERNAL_KILL_SWITCH`)

## 5. Kill-switch engagement

1. Set the environment flag (no code deploy, no release):
   `RTFTT_PROCESSING_EXTERNAL_KILL_SWITCH=true` (or `1`).
2. Apply the documented refresh (existing PP-T2 procedure):
   `php artisan config:clear` (or `php artisan config:cache` where caching is
   used).
3. Recycle long-lived queue workers so they pick up the new config
   (`php artisan queue:restart` or process restart per deployment; normal
   Laravel semantics — PP-T5 introduces no new reload channel).
4. Invalid values (anything other than `true`/`1`/`false`/`0`/booleans/null)
   fail closed as ENGAGED with a warning naming the value — never a boot
   crash, never silently disengaged.

## 6. Kill-switch verification

With the switch engaged and both selections naming `external_reference`:

- `app(TranscriptionProvider::class)` resolves
  `SelfHostedTranscriptionProvider`; `app(TranslationProvider::class)`
  resolves `SelfHostedTranslationProvider`.
- Reference transports record zero dispatches (no new external attempts).
- Logs carry `Processing kill switch engaged; forcing self-hosted
  transcription.` and `... translation.` (info level) with
  `provider_key=self_hosted`, `config_source=processing.external_kill_switch`,
  `kill_switch_engaged=true`, and the invocation `request_id` when provided.
- Stored selection config is NOT rewritten (disengaging restores the exact
  prior selection — no sticky reroute, no fallback chain).

Automated proof: `PpT5KillSwitchRollbackTest` (AC3 engaged/invalid/no-fallback
cases) and `PpT5CeilingsAlertEmissionTest` (forbidden_external alert case).

## 7. Rollback/recovery

The kill-switch IS the rollback (no schema/data rollback exists — no migration
shipped). Rehearsal sequence (mirrors `PpT5KillSwitchRollbackTest` AC4):

1. Preconditions: stock defaults route self-hosted (§4 inspection passes).
2. Simulated change: select both `external_reference` values; confirm both
   reference paths serve through fixtures.
3. Safe-path activation: engage the kill-switch per §5; confirm both domains
   resolve self-hosted with zero new external dispatches.
4. Restoration: return both selections to `self_hosted` and disengage the
   switch; re-run §4 inspection.
5. Post-rollback verification: §8.

## 8. Post-rollback clean-state verification

Clean state is ALL of the following (asserted by the AC4 rehearsal test):

- `transcription.provider_selection = self_hosted`
- `translation.provider_selection = self_hosted`
- `processing.external_kill_switch = false` (or unset)
- No migration applied or required
- Fixture bindings present-but-unselected where test bindings were registered
- Zero new external reference dispatches after the refresh
- Full PP-T5 focused suite green (see §17)

## 9. Privacy / zero-egress verification

- Runtime: both reference flows execute with `Http::preventStrayRequests()`
  armed (`PpT5PrivacyEgressSecretsTest` zero-egress test) — any real HTTP
  attempt would throw.
- Source audit: the reference seam files
  (`app/Transcription/ReferenceExternal*.php`,
  `app/Translation/ReferenceExternal*.php`, both resolvers) contain no
  `Http::`, cURL, socket, vendor-SDK, or `env()`/`getenv` usage (same test
  file, source-audit test).
- Fixture endpoints are `http://localhost:9/...` (non-routable discard port)
  and fixture tokens carry a `fixture-...-not-a-secret` marker.
- Egress disclosure (what WOULD leave on a real transport): derived audio
  chunk payloads + chunk metadata + pinned model identity (transcription);
  segment-aligned source text + metadata + pinned model identity
  (translation). Never: original media, ownership data, full transcript text
  at request time, or credentials in logs.
- Off-host backup copies count as full egress (`PRIVACY-DATA-MOVEMENT.md`
  J01–J03); current posture is node-local backups, loopback worker.

## 10. Secret-handling verification

- Empty/missing fixture credentials fail closed at construction with
  `ConfigurationError` (`CONFIGURATION_ERROR`) in both domains, before any
  dispatch (zero transport calls) — `PpT5PrivacyEgressSecretsTest` AC5 test.
- Secrets never appear in captured log payloads, outbound DTO assertions
  aside (authorization headers live only on in-process transport DTOs, never
  in logs), or persisted results (same test file).
- Environment check before any rehearsal:
  `RTFTT_TRANSCRIPTION_WORKER_TOKEN` and `RTFTT_TRANSLATION_WORKER_TOKEN`
  must be empty in test/fixture contexts; no real credential is ever
  committed (fixture tokens are `fixture-...-not-a-secret` markers).
- Provider error payloads leak no credentials (failure messages name codes,
  never tokens/endpoints).

## 11. Credential-rotation procedure

For a future real deployment (no live vendor credentials exist today; this
procedure rotates the existing worker-bearer and any future provider secret
using current config ownership — no code change required):

1. Issue the new secret out-of-band; do NOT commit it.
2. Update the environment (`RTFTT_*_TOKEN`) on the target host only.
3. Run `php artisan config:clear` (or `config:cache`) and recycle workers
   (same refresh as §5).
4. Verify: absent-secret fail-closed still armed (AC5 suite green),
   selections unchanged (§4 inspection), no secret in fresh logs (§10 scan).
5. Revoke the old secret at the issuer.

## 12. Safety-ceiling verification

Existing ceilings only (PP-T5 adds none). Automated proof:
`PpT5CeilingsAlertEmissionTest` AC6 cases.

| Domain | Ceiling | Boundary | Exceed | Code |
|---|---|---|---|---|
| transcription | `maxRequestBytes` (default = 500 MiB product limit) | accepted | rejected, zero dispatch | `MEDIA_REJECTED` (non-retryable) |
| transcription | provider timeout ceiling `config(transcription.timeout_seconds)` = 300 | 300 accepted | 301 refused at construction | `CONFIGURATION_ERROR` |
| translation | `maxSegments` (default 1000) | exact count accepted | exceed rejected, zero dispatch | `INVALID_REQUEST` (non-retryable) |
| translation | `maxPayloadChars` (default 500000) | exact chars accepted | exceed rejected, zero dispatch | `INVALID_REQUEST` (non-retryable) |
| translation | provider timeout ceiling `config(translation.timeout_seconds)` = 300 | 300 accepted | 301 refused at construction | `CONFIGURATION_ERROR` |

Ceiling rejections perform no retry and no fallback (same call repeated
rejects identically; selection config unchanged). Concurrency/timeout
operational guidance: honor the queue/timeout invariant (provider 300 <
job 330 < `retry_after` 420); there is NO over-concurrency rejection code —
do not invent one.

## 13. Synthetic fault / alert-emission verification

Alerts are structured LOG records (emission) via existing Laravel Log
infrastructure — test-observed with `Log::listen` + `MessageLogged` (the
PP-T2-corrective precedent). Delivery = existing log infrastructure and
deployment forwarding (unchanged). Operator response = this runbook. No
third-party delivery integration exists or is authorized. Automated proof:
`PpT5CeilingsAlertEmissionTest` AC7 cases.

| Test-side alert key | Synthetic fault | Emitted record (message / level) | Category |
|---|---|---|---|
| `invalid_selection` | select `bogus_vendor` / `external_other` | `Unknown transcription provider selection; failing closed.` / `Unsupported translation provider selection; failing closed.` (warning) | fail-closed throw |
| `missing_credentials` | construct adapter with empty token | synchronous `ConfigurationError` before dispatch (operator signal via caller/job error layer); zero dispatches | `CONFIGURATION_ERROR` |
| `forbidden_external` | kill-switch engaged + external selected | `Processing kill switch engaged; forcing self-hosted …` (info), self-hosted resolution, zero dispatches | forced self-hosted |
| `ceiling_breach` | oversized request | `Reference external … request exceeds provider ceiling.` (warning) | `MEDIA_REJECTED` / `INVALID_REQUEST` |
| `saturation_timeout` | script `Timeout` / `RateLimited` kinds | `Reference external chunk failed.` / `Reference external translation failed.` (warning, `outcome=failed`) | `WORKER_TIMEOUT`→retryable / `WORKER_SATURATED`→retryable / `PROVIDER_TIMEOUT`→retryable / `PROVIDER_UNAVAILABLE`→retryable |

Every record carries `request_id` + `provider_key` + `domain`; no record
carries secrets. Faults never mutate selection config (re-resolution asserts
prove no silent routing change). There is NO quota alert (no quota source
exists) and no latency-threshold alert beyond the mapped timeout/saturation
rows — do not invent either.

## 14. Correlation fields to inspect

Reuse the frozen identities — PP-T5 mints none:

- `request_id`: invocation `requestId` minted once by
  `TranscriptionInvocation::create()` / `TranslationInvocation::create()`;
  present on outbound transport DTOs, every adapter record, and resolver
  records when `resolve($requestId)` is used (log-context only — never
  routing).
- `provider_key` + `model_pinned`: join resolver lines (`config_source`,
  `kill_switch_engaged`) with adapter lines and job-layer
  `provider invocation started` lines (`provider_class`).
- `http_request_id` is a distinct transport field owned by the jobs — never
  redesigned, never minted in reference paths (asserted).
- Chunk ids (`chk_*`) are execution-only; they never leak into normalized
  results or segment identity (asserted in `PpT5IdentityCorrelationTest`).

## 15. Informational spend guidance

Spend handling is INFORMATIONAL ONLY (AC9 audited: no billing, budget, quota,
pricing, or spend code exists anywhere in the provider/config surface):

- No budgets, hard caps, per-provider/per-user thresholds, or billing
  integration exist. Do not quote cost figures: no reliable cost source
  exists in the fixture reference model, so NO numerical spend claim can be
  made.
- Available operational signals only: ceiling-rejection counts, saturation/
  timeout anomaly records, kill-switch engagement records, per-request
  latency (`duration_ms`) and outcome fields.
- Safety posture (not spend enforcement): adapter ceilings + kill-switch +
  fail-closed selection bound the blast radius of misconfiguration.
- Non-blocking future trigger: revisit spend review only when a real vendor
  with metered pricing is selected under a separate HPO decision — never
  silently.

## 16. Degraded-observability behavior

- `LogContext` assembly is best-effort and never throws (P7-005): proven on
  incomplete models in `PpT5DegradedObservabilityAuditTest`.
- Provider selection reads CONFIG ONLY: attaching observability listeners
  changes emitted records, never the selected provider (same test file).
  Kill-switch and fail-closed evaluation never consult log state.
- Framework-level log-sink failure propagation (if the Laravel log channel
  itself throws) is a framework property outside PP scope: it is NOT masked
  by provider code, and no PP-T5 harness alters logging infrastructure to
  fake this. The contracted guarantee is the selection-invariance above plus
  fail-closed defaults, not sink-failure immunity.

## 17. Evidence checklist

- [ ] `vendor/bin/pest.bat tests/Feature/ProcessingProvider` — all PP-T5
  focused tests pass (kill-switch/rollback, privacy/egress/secrets,
  ceilings/alerts, identity/correlation, degraded-observability/audits).
- [ ] PP-T2 resolver suites green (resolution + kill-switch semantics
  unchanged).
- [ ] PP-T3 + PP-T4 focused suites green (adapters untouched).
- [ ] Relevant Phase 3 / Phase 5 / Phase 6 suites green.
- [ ] P7 observability/ops suites referenced by contract green.
- [ ] Full suite + Pint + PHPStan clean.
- [ ] Runtime-diff audit: `git status --porcelain` shows NO new/modified
  files under `app/`, `config/`, `database/`, or worker code from PP-T5
  (tests + `verification/pp-t5/` + governance only).
- [ ] Secret scan: no token/`Bearer` material in captured log evidence or
  committed fixtures beyond `fixture-...-not-a-secret` markers.
- [ ] PP-T6 untouched: `tasks/PP-T6-*` still `BACKLOG / NOT AUTHORIZED`,
  no `verification/pp-t6/` created, no gate executed.

## 18. Failure/escalation conditions

STOP and escalate to HPO/governance (do not silently expand scope) when ANY
holds:

- A ceiling, mapping, or identity behavior required by the checks above is
  missing (needs runtime change — not authorized by PP-T5).
- Log evidence proves insufficient for run reconstruction (ADR-027 envisaged
  this: durable chunk-audit reconsideration needs explicit HPO authorization,
  never a silent table).
- Any BLOCKER/HIGH finding in review, any full-suite/Pint/PHPStan failure,
  or any non-zero runtime diff.
- Any request to verify against live vendors, real credentials, customer
  media/text egress, or production traffic (all forbidden in PP-T5).
- After 3 CHANGES_REQUESTED cycles: task becomes BLOCKED per the
  three-cycle escalation rule; HPO decides.

## 19. Explicit PP-T6 handoff boundary

PP-T6 (`tasks/PP-T6-integration-compatibility-verification.md`,
`BACKLOG / NOT AUTHORIZED / FINAL_GATE_ONLY`) owns the final integration and
Phase 1–7 compatibility gate: matrix execution, external-path fixture proofs,
regression gate, and verdict report. PP-T5 prepares evidence PP-T6 will later
consume (this runbook + the PP-T5 suites) and NOTHING more. Do not execute the
gate, issue a final readiness verdict, certify integration, authorize
production/vendor rollout, close the provider track, or alter PP-T6 lifecycle
in the name of PP-T5. Begin PP-T6 Step 1 only when explicitly instructed.
