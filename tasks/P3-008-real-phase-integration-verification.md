# P3-008 — Real Phase Integration Verification

## Status

DONE

## Ownership

Implementation Owner: UNASSIGNED (evidence harness + execution)
Reviewer: Claude Code (independent verification; per AGENTS.md agent model)

## Authorization

AUTHORIZED. Promoted to READY by the Human Product Owner on 2026-09-19 as part
of Phase 3 Batch 3 authorization (`DECISION-P3-BATCH3-001`). P3-008 execution is
dependency-gated: it must not execute the final Phase 3 integration verification
until P3-007 is implementation-complete and frozen, and it must not be
independently VERIFIED until P3-007 is independently VERIFIED. Owner decisions
B3-06 (live Redis) and B3-07 (real FFmpeg + faster-whisper) are DECIDED as
mandatory (see `DECISION_QUEUE.md` and ADR-018). This authorization does not mark
this task VERIFIED or DONE and does not close Phase 3.

## Authorized Phase

Phase 3 — Real Transcription Engine (ADR-017)

## Batch

Batch 3

## Objective

Verify the real end-to-end Phase 3 pipeline with actual media, real Redis queue
execution, the real authenticated Python worker, real FFmpeg, and the real
faster-whisper model. Mock-only verification is insufficient.

## Context

- ADR-017 (Phase 3 boundary amendment)
- `PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical specification, including
  the Completion Gate and the Canonical Phase Gate Statement)
- P3-001 through P3-007 (all prior batches)
- ADR-013 / ADR-016 and the P2-004A2 genuine-concurrency verification standard
- `reviews/PHASE3-BATCH2-independent-review.md` (residual INFO-1: live Redis
  never verified; MEDIUM-1 downgraded to INFO: SQLite is the canonical
  verification environment)

## Verification Nature

P3-008 is a **verification** task. It is not new feature development. The
Builder (OpenCode) prepares and executes the integration harness and captures
reproducible evidence; Claude Code independently reproduces the evidence and
issues the VERIFIED / CHANGES_REQUESTED verdict; the Human Product Owner closes.
A separate final Phase 3 phase-gate review and HPO acceptance are required to
close Phase 3 as a whole; P3-008 VERIFIED is necessary but not sufficient for
Phase 3 closure.

## Scope

Verify the real path:

```
real supported media
 ↓
Phase 2 private MediaFile
 ↓
transcription request
 ↓
Redis
 ↓
Laravel queue worker
 ↓
authenticated Python worker
 ↓
shared private filesystem
 ↓
FFmpeg
 ↓
real faster-whisper model
 ↓
normalized multilingual result
 ↓
transcript persistence
 ↓
segment persistence
 ↓
completed
 ↓
retrievable through existing authorized application surface
```

and, after P3-007:

```
failure
 ↓
retry eligibility
 ↓
retry
 ↓
new attempt
 ↓
successful completion or valid re-failure
```

### 1. Integration Flow Gate

- existing uploaded MediaFile → transcription request → processing attempt
  created → queued → worker claims → media passed by opaque reference →
  provider executes → normalized result → transcript persistence → segment
  persistence → completed → retrievable.
- After P3-007: failure → retry eligibility → retry → new attempt → success or
  valid re-failure, using the actual P3-007 contract.

### 2. Language / Code-Switching Gate

- per-segment language remains segment-specific;
- transcript detected/dominant language is separate from segment language;
- requested language is not overwritten;
- `und` remains valid;
- mixed-script Unicode persists safely;
- mixed segments covering `ms`, `en`, `zh`, `ta`, `und` are verified.

### 3. No-Speech Gate

- `text = ""`, `speech_detected = false`, detected language `und`, no segments,
  completed = success;
- no-speech does not enter retry/failure recovery.

### 4. Timestamp / Segment Gate

- deterministic ordering;
- millisecond precision preserved;
- DB precision (`decimal(12,3)`);
- SRT/VTT formatter compatibility with fractional seconds (already in product
  scope via `TranscriptionExportController`; do not expand export scope);
- no duplicate `(transcription_id, segment_index)`;
- replay/idempotency behavior.

### 5. Queue Gate

Explicitly distinguish and report:

- Laravel queue abstraction (driver-agnostic);
- real job serialization/deserialization;
- configured queue name (`transcription`);
- database queue testing;
- Redis outage behavior;
- live Redis integration (real `redis` driver + real `queue:work`).

DECIDED (B3-06): live Redis integration evidence is **mandatory** before P3-008
can be VERIFIED. The exact evidence required is: a reachable Redis server,
`QUEUE_CONNECTION=redis` (or `RTFTT_TRANSCRIPTION_QUEUE_CONNECTION=redis`), the
real job serialized and consumed by `queue:work --queue=transcription`,
authoritative DB state reload, completion through the established pipeline, no
media path/binary in the serialized job, and valid duplicate/stale protections
where applicable. If Redis is unavailable, P3-008 remains unverified.
Database-queue evidence is not a substitute for live Redis evidence.

### 6. Real Worker / FFmpeg / faster-whisper Gate

Determine and report the level of integration evidence actually produced:

- mocked provider only (insufficient);
- self-hosted worker process;
- real FFmpeg extraction;
- real faster-whisper model (canonical `large-v3`);
- representative media fixture.

DECIDED (B3-07): a real self-hosted FFmpeg + faster-whisper end-to-end execution
is **mandatory** before P3-008 can be VERIFIED. Mocks remain valid for
unit/feature coverage but cannot satisfy this final integration requirement. Use
a small controlled representative fixture. For code-switching coverage, use a
suitable real fixture or supplement the real-worker smoke run with deterministic
normalized-result fixtures; the real-worker requirement does not require every
scenario to invoke the large model. Mocks must not be substituted for an
acceptance requirement that demands the real worker. The report must state
exactly what was automated in CI, what required an environment-dependent
integration run, and what could not be claimed.

### 7. Media / Storage Gate

- provider receives only the opaque/authorized media reference through the
  canonical boundary;
- queue payload contains no raw file path or media binary;
- no public storage leak;
- temporary extracted audio remains ephemeral by default and cleanup obeys the
  configured retention policy;
- original media remains unchanged.

### 8. Ownership / Isolation Gate

- user cannot process another user's media;
- worker payload cannot override owner identity;
- attempt must belong to the same transcription/media ownership chain;
- stale/mismatched attempts cannot persist results;
- retry does not weaken isolation.

### 9. Failure Gate

Verify representative failure classes using the actual P3-007 contract:
provider failure, malformed/invalid result, persistence failure, stale job,
duplicate delivery, retry flow, second failure after retry, completed-state
protection.

### 10. Concurrency Gate

Use the repository's established genuine-concurrency standard. Where an
invariant depends on concurrent access, require independent OS
processes/connections. At minimum verify the P3-007 retry-CAS invariant.
Sequential-only claims are not acceptable.

### 11. Phase 3 Regression Gate

- **Phase 1**: authentication, dashboard, navigation, ownership intact.
- **Phase 2**: upload, 500 MiB contract, private storage, upload retry/
  idempotency, P2-004A2 CAS behavior intact.
- **Phase 3 Batch 1**: provider-neutral contracts, language/code-switching,
  worker/error envelope, ephemeral audio intact.
- **Phase 3 Batch 2**: transcript/segment persistence, atomic completion, queue
  claim, stale-job protection, concurrency evidence intact.
- **Phase 3 Batch 3**: retry/recovery semantics verified.
- No new unexplained skips.

## Out of Scope

- Translation
- Full transcript UI redesign
- Minimum WER threshold
- Hosted ASR provider
- Horizon
- Retranscription of completed transcripts
- New feature development

## Dependencies

- P3-007 (Failure / Retry / Recovery Hardening) — implementation-complete and
  independently VERIFIED before P3-008 VERIFIED.
- Owner decisions B3-06 and B3-07 — DECIDED (mandatory live Redis and real
  worker execution).

## Acceptance Criteria

1. Real supported media processed end-to-end.
2. Real Redis queue execution verified (mandatory; see B3-06).
3. Real authenticated Python worker invoked.
4. Real FFmpeg preparation verified.
5. Real faster-whisper inference verified.
6. Normalized multilingual result produced.
7. Transcript persistence verified.
8. Segment persistence verified.
9. Atomic completion verified.
10. Duplicate delivery safe.
11. Retry bounded and idempotent.
12. Terminal failure deterministic.
13. Cross-user isolation intact.
14. Phase 1/2 regression verification passes.
15. No translation introduced.
16. Mock-only tests insufficient; real path evidence required.
17. The P3-007 retry-CAS invariant is proven under genuine concurrency.
18. All Phase 3 contracts verified together; no blocking findings.
19. Queue/runtime environment claims are accurately scoped (no false claim of
    live Redis or real worker execution).
20. Final independent phase review completed.
21. All tests pass; Pint clean; PHPStan 0 errors.

## Verification Notes

Executed 2026-09-19 against the frozen, independently VERIFIED P3-007 behavior.
Full evidence: `PHASE3-P3-008-INTEGRATION-EVIDENCE.md`.

### Mandatory gates

- **Live Redis (B3-06) = PASS.** A portable Redis 3.0.504 server was started on
  `127.0.0.1:6379`. A real `ProcessTranscription` job was dispatched onto the
  `transcription` queue through the configured `redis` connection, a real
  `queue:work redis` worker consumed it, and the pipeline completed. The
  serialized payload was inspected: 633 bytes, ID-only (`transcriptionId`,
  `processingAttemptId`), no media path/binary/user identity/provider result,
  `maxTries = 1`.
- **Real FFmpeg + faster-whisper `large-v3` (B3-07) = PASS.** Real private MP3
  speech fixture → real FFmpeg extraction (verified `pcm_s16le`/16000 Hz/mono)
  → real `Systran/faster-whisper-large-v3` inference → normalized result →
  persistence → `completed`. Transcript text matched the spoken content;
  `detected_language = en`; `model = large-v3`; 58s CPU inference.

### Additional integration evidence

- No-speech: real silence fixture through live Redis + real worker produced
  `text=""`, `speech_detected=false`, `detected_language=und`, 0 segments,
  `completed`.
- Manual retry: real domain retry through live Redis + real worker; old failed
  attempt preserved (`WORKER_TIMEOUT`), new attempt completed, same
  `transcription_id`, exactly 2 attempts, 0 active.
- Ephemeral audio: prepared-audio directory empty before and after every run.
- Concurrency: `TranscriptionClaimConcurrencyTest` and
  `TranscriptionRetryConcurrencyTest` re-run green (2 passed / 22 assertions).

### Quality gates

- Full PHP suite: 366 total / 365 passed / 1 skipped (pre-existing 2FA) /
  1152 assertions / 2 pre-existing warnings.
- Pint clean; PHPStan 0 errors (`--memory-limit=1G`).
- Python worker suite: 36 passed.

### Scope / changes

- No production code was changed during P3-008.
- One hidden, env-guarded verification harness command
  (`app/Console/Commands/Phase3IntegrationVerification.php`, disabled unless
  `RTFTT_P3008_RUN=1`) was added solely to drive/observe the run.
- Live Redis and real inference are environment-dependent and are not claimed to
  run in normal CI.

## Review

Review File: `reviews/P3-008-independent-review.md`
Review Status: VERIFIED (independent Phase 3 integration review, 2026-09-19).
No BLOCKER/HIGH/MEDIUM. Non-blocking: INFO-1 (live Redis and real
faster-whisper gates inspected, not independently re-executed live by the
reviewer), INFO-2 (Redis runtime version not canonically pinned), INFO-3
(admin actor-vs-owner, carried forward), LOW-1 (stale-threshold override
hardening, carried forward from P3-007). The review accepted both mandatory
gates (B3-06 live Redis; B3-07 real FFmpeg + faster-whisper `large-v3`) and
audited the full I-01–I-22 matrix with no mandatory scenario unresolved or
BLOCKED. The verification harness
(`app/Console/Commands/Phase3IntegrationVerification.php`) was reviewed and
accepted as verification tooling.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-19
(DECISION-P3-008-CLOSURE-001), based on the completed independent Phase 3
integration review (`reviews/P3-008-independent-review.md`: P3-008 = VERIFIED;
no BLOCKER/HIGH/MEDIUM; mandatory B3-06/B3-07 gates accepted; I-01–I-22
audited).

Canonical transition: VERIFIED → (HPO closure decision) → DONE.

P3-008's role was the final Phase 3 integration verification gate, not an
ordinary feature-implementation task. Canonical history (preserved, not
rewritten):

```text
BACKLOG
→ Batch 3 HPO authorization
→ READY
→ dependency gate on P3-007 (independently VERIFIED, then DONE)
→ final integration verification execution (live Redis + real large-v3)
→ REVIEW
→ independent review: VERIFIED
→ HPO closure
→ DONE
```

Evidence preserved:

- `PHASE3-P3-008-INTEGRATION-EVIDENCE.md` (Builder evidence)
- `reviews/P3-008-independent-review.md` (independent verdict)

Non-blocking findings remain historical and are not promoted into scope:

- INFO-1: mandatory gates were inspected/corroborated, not live re-executed, by
  the reviewer (Builder did execute them live).
- INFO-2: Redis runtime version is not canonically pinned; production
  deployment gate should document a supported version.
- INFO-3: pre-existing admin actor-vs-owner semantics (deferred future gate).
- LOW-1: `attempt_stale_seconds` override hardening (optional, carried forward
  from P3-007).

Closure is governance/state reconciliation only; no implementation, test,
migration, worker, or verification-harness behavior is changed by this closure.
This closes the task execution portion of Phase 3 Batch 3. It does not close
Phase 3; Phase 3 final closure remains a separate HPO decision.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED. Phase 3 closure
(HPO acceptance of the final phase gate) is a separate decision and is not
created by P3-008 VERIFIED.
