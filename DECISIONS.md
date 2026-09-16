\# RTFTT Transcribe AI — Decision Log



This file records durable product and engineering decisions that future

agents need to understand.



Do not use this file for routine implementation notes.



\---



\## ADR-001 — Separate MediaFile and Transcription



Status: ACCEPTED



Decision:



MediaFile and Transcription remain separate domain entities.



Reason:



The same media may eventually be transcribed multiple times using different

models, language settings, retries, or processing configurations.



Reference:



PROJECT\_CONTEXT.md

architecture.md



\---



\## ADR-002 — Transcription Worker Must Be Independent



Status: ACCEPTED



Decision:



Heavy speech recognition must not execute synchronously inside Laravel HTTP

requests.



The future transcription worker must remain independently deployable.



Reference:



PROJECT\_CONTEXT.md

architecture.md



\---



\## ADR-003 — Incremental Product Development



Status: ACCEPTED



Decision:



Future capabilities must not be implemented simply because they are described

in PROJECT\_CONTEXT.md.



Implementation authorization comes from plan.md and explicit user instruction.



Reference:



PROJECT\_CONTEXT.md

plan.md



\---



\## ADR-004 — Multi-Agent Repository Workflow



Date: 2026-09-10

Status: ACCEPTED



Decision:



The repository acts as the shared communication and state layer between:



\- Codex

\- Claude Code

\- OpenCode

\- Human Product Owner



Routine AI-to-AI handoffs should be persisted through:



\- task files

\- review files

\- CURRENT\_STATE.md

\- DECISIONS.md

\- tests

\- Git history



instead of being manually copied between AI chat sessions.



Default agent roles:



\- Codex — primary implementation and engineering orchestration

\- Claude Code — independent review, debugging, and forensic investigation

\- OpenCode — interactive local engineering and experimentation



Human approval remains required for:



\- product scope decisions

\- major architecture changes

\- destructive operations

\- security-sensitive actions

\- UX/product acceptance gates

\- production release



---



## ADR-005 — Cascade Media Deletion Requires Explicit Confirmation



Date: 2026-09-11

Status: ACCEPTED



Decision:



Media may be deleted even when it has associated transcriptions. Deletion must

require an explicit cascade confirmation that clearly informs the user that all

related transcriptions will also be deleted.



Reason:



This decision resolves the conflicting media deletion behavior identified in

the Phase 1 audit. The application must use one consistent deletion contract:

the user deliberately confirms the destructive cascade before media and its

associated transcriptions are removed.



Reference:



Phase 1 completion audit

app/Http/Controllers/MediaActionController.php

app/Livewire/Media/Show.php

---

## ADR-006 — Phase 1 Demo Transcription Title Required

Date: 2026-09-11

Status: ACCEPTED

Decision:

The Phase 1 demo transcription form requires an explicit title. The UI must describe Title as required and must not promise a filename fallback, because Phase 1 does not accept a physical upload. Existing server-side required validation remains authoritative.

Reason:

The previous form copy described Title as optional and promised a filename fallback while the server rejected blank titles. This aligns the user-facing contract with the implemented demo-only behavior without expanding scope into real uploads.

Reference:

DECISION-P1-001; tasks/TASK-P1-CREATE-001.md; resources/views/transcriptions/create.blade.php; app/Http/Controllers/DemoTranscriptionController.php

---

## ADR-007 — Phase 1 Human Acceptance

Date: 2026-09-11

Status: ACCEPTED

Decision:

The Human Product Owner accepts Phase 1 — Application Foundation + Full Clickable Prototype — as complete for the current authorized scope.

Reason:

The completed Phase 1 implementation, verification suite, static analysis, frontend build, governance records and independent reviews satisfy the current scope.

Impact:

Phase 1 is closed for acceptance. This decision does not authorize Phase 2 or any later phase. Explicit authorization is required before new-phase implementation begins.

Reference:

CURRENT_STATE.md; plan.md; reviews/PHASE1-FOLLOWUPS-review.md; reviews/TASK-P1-STATIC-001-review.md; reviews/TASK-P1-STATIC-002-review.md; reviews/TASK-P1-STATIC-003-review.md; reviews/TASK-P1-STATIC-004-review.md; reviews/TASK-004C-review.md; reviews/TASK-P1-CREATE-001-review.md

## ADR-008 — Phase 2 Upload Product Contract

Date: 2026-09-11

Status: ACCEPTED for P2-001 checkpoint

Decision:

Phase 2 accepts single-file audio and video uploads using the centralized matrix in `config/media.php`, with an exact 500 MiB (524,288,000-byte) per-file application limit and no duration limit. Duplicate uploads are allowed. On success, `display_name` defaults to the original filename without its final extension; `original_filename` and physical storage identity remain unchanged by later renames. Successful upload opens Media Detail. Download means the original private media file, not transcript export.

The exact extension/MIME matrix is:

- Audio: `mp3` → `audio/mpeg`, `audio/mp3`, `audio/x-mpeg-3`; `wav` → `audio/wav`, `audio/wave`, `audio/x-wav`; `m4a` → `audio/mp4`, `audio/x-m4a`; `aac` → `audio/aac`, `audio/x-aac`; `flac` → `audio/flac`, `audio/x-flac`; `ogg` → `audio/ogg`, `application/ogg`.
- Video: `mp4` → `video/mp4`; `mov` → `video/quicktime`; `webm` → `video/webm`.

Video is accepted as stored media only. FFprobe, FFmpeg, transcription, and processing remain out of scope. Existing folder and admin authorization behavior is preserved, and upload-as-user is not supported.

Infrastructure note:

The current PHP CLI configuration reports `upload_max_filesize=2M` and `post_max_size=8M`, which cannot receive the approved 500 MiB (524,288,000-byte) upload. Before P2-003, the effective PHP and web-server request limits must be raised to at least the 500 MiB application boundary, with additional request overhead; no machine-wide configuration change is made by this checkpoint. The web-server and Livewire receiving limits are not discoverable from the repository and must be verified in the target runtime.

## ADR-009 — Phase 2 Ingestion and Storage Contract

Date: 2026-09-11

Status: ACCEPTED for P2-002 checkpoint

Decision:

Ingestion follows `temporary staging → server validation → metadata derivation → opaque private storage promotion → MediaFile persistence → Media Detail`. Temporary staging is not a durable `MediaFile`. Successful persistence uses `MediaStatus::Uploaded`; `Processing` and `Ready` remain reserved for later processing phases. Failed ingestion must not leave a misleading durable media record. Filesystem and database consistency require explicit recovery because database transactions do not roll back filesystem operations. Abandoned staging artifacts older than 24 hours are eligible for cleanup.

`MediaFile` stores an optional, non-unique SHA-256 checksum for integrity and future duplicate detection; it does not deduplicate uploads. The server computes the checksum from accepted media bytes and represents it as exactly 64 lowercase hexadecimal characters. Request-derived or client-supplied checksum values are not accepted; application code assigns the generated value through the `MediaFile` server-assignment boundary. Durable storage uses the configured private disk and opaque UUID-based identities, with no user identity or original filename in the path. The upload boundary must remain transport-independent so a future resumable/chunked transport can be added without changing the domain contract.

---

P2-002B compensation and retry extension:

- One opaque `upload_attempt_id` represents one intentional upload and is scoped
  to the authenticated owner. The upload boundary allocates it and the client
  carries it across completion retries; a new intentional duplicate receives a
  new identifier. Checksums remain non-unique and are not idempotency keys.
- The future workflow persists only the narrow successful owner/attempt lookup
  needed to return an existing `MediaFile` on retry. It must not add a general
  upload-attempt state machine as part of this contract.
- The durable object identity is allocated once per attempt and reused across
  retries. It remains private and opaque, and carries stable attempt identity
  for recovery.
- Before persistence, validation/promotion failures compensate only resources
  created by that attempt. A promoted object is deleted best-effort when row
  persistence fails; an unconfirmed delete is an orphan candidate. No orphan
  may be adopted into a new `MediaFile` automatically.
- After a `MediaFile` commit, response retry returns that record and does not
  compensate, promote, or insert again. An ambiguous database result is looked
  up by owner and attempt identifier before replay.
- Staging cleanup must claim/lease before deleting an eligible artifact, and
  must defer while a retry is active. If cleanup wins, the source is re-staged
  with the same attempt identifier; a new identifier is never minted silently.
- Orphan reconciliation defers active/young candidates, deletes unclaimed old
  unreferenced objects, and retries/records failed deletes without creating a
  row. This preserves intentional duplicate uploads while limiting one attempt
  to one logical upload.

The executable future-work test inventory is recorded in
`tests/Feature/IngestionCompensationContractTest.php`. The workflow, queues,
outbox, cleanup command, and persisted state machine remain out of scope for
P2-002B.

## ADR-010 — Canonical Multi-Agent Operating Model

Date: 2026-09-11

Status: ACCEPTED

Supersedes: The agent-role definitions and orchestration portion of ADR-004 (Multi-Agent Repository Workflow). ADR-004's repository-handoff principles (task files, review files, CURRENT_STATE.md, DECISIONS.md, Git history as shared state) are preserved and remain authoritative.

Decision:

The repository follows a multi-agent operating model with distinct responsibilities:

- **Work** — Coordinate / Orchestrate. Top-level orchestration layer. Reads repository state, identifies authorized runnable tasks, evaluates dependencies and gates, assigns implementation work, routes decisions to the Human Product Owner, and closes VERIFIED tasks. Work does not implement application code, act as the implementation owner, perform independent review of its own outcomes, make Human Product Owner decisions, or authorize new phases.

- **OpenCode** — Primary Build. Default implementation agent. Implements the assigned task, creates/updates tests, runs relevant commands, and moves to REVIEW after own verification succeeds. Must not mark its own implementation VERIFIED, close its own task as DONE, expand product scope, make Human Product Owner decisions, begin unrelated tasks automatically, or bypass Claude independent review where required.

- **Claude Code** — Independent Review. Default independent reviewer. Reviews implementation correctness, verifies acceptance criteria, inspects architecture and security boundaries, assesses regressions and edge cases, and produces durable review artifacts. Must not silently become the implementation owner, modify implementation code during review unless explicitly reassigned, review and approve its own implementation, or make Product Owner decisions.

- **Codex** — Investigate / Complex Engineering. Engineering specialist and secondary implementation capability. Default uses include deep repository investigation, complex debugging, architectural/technical investigation, difficult refactoring analysis, and supporting Work with technical evidence. May become the implementation owner for a specific task when Work or the Human Product Owner explicitly assigns it. If assigned, normal implementation-agent rules apply, Claude remains the independent reviewer, and only one active implementation owner is allowed. Codex is no longer the default engineering orchestrator.

- **Ridzwan / Human Product Owner** — Decide. Owns decisions involving product scope, materially different UX behavior, major architecture, security-sensitive decisions, destructive operations, production deployment, milestone acceptance, phase completion, and phase authorization. Agents may provide evidence and recommendations. Agents must not silently make these decisions.

- **Repo** — Remember. The repository is the shared durable memory and source of truth. Routine agent-to-agent communication must happen through repository artifacts rather than requiring manual relay between agents. Chat history is not the canonical project state when repository state exists.

The single-active-implementation-owner rule remains mandatory: one task may have only one active implementation owner.

Reason:

The previous model (ADR-004) defined Codex as the primary implementation agent and engineering orchestrator. The new model introduces Work as a dedicated orchestration layer, elevates OpenCode to the default implementation agent, preserves Claude as the independent reviewer, and retains Codex as the engineering specialist and explicit alternate implementation owner. This separation ensures no single agent both orchestrates and implements, improving governance boundaries.

ADR-004's repository-handoff principles remain authoritative: task files, review files, CURRENT_STATE.md, DECISIONS.md, DECISION_QUEUE.md, and Git history are the shared durable state layer. ADR-004 is preserved as the original accepted decision; this ADR supersedes only its agent-role/orchestration definitions.

Reference:

.ai/guidelines/ai-development-os.md; .ai/guidelines/orchestration-policy.md; AGENTS.md; CLAUDE.md; ADR-004

---

## ADR-011 — RTFTT / Voxora Governance Reconciliation

Date: 2026-09-13

Status: ACCEPTED

Reconciliation Closure: VERIFIED / DONE on 2026-09-13. Review record:
`reviews/GOVERNANCE-RECONCILIATION-ADR011-review.md`.

Decision:

Voxora is the long-term product and brand. RTFTT remains the current
engineering/repository identity, and the repository is not renamed by this
decision. The product vision is strategic and non-executable; future Voxora
evolution requires the repository roadmap and phase gates to authorize it.

The repository-native authority model remains controlling:

- `AGENTS.md` is the operational agent entry point.
- `.ai/guidelines/orchestration-policy.md` is the one canonical State-to-Action
  Contract. Its state/action mapping must not be duplicated elsewhere.
- `plan.md` owns authorized roadmap and phase scope.
- `CURRENT_STATE.md` owns current operational state and gates.
- Accepted ADRs remain durable decisions until explicitly superseded.
- Task files own task-level scope and acceptance criteria.
- Source, schema, tests, configuration, CI, and Git history provide evidence;
  they do not create authorization by themselves.

The repository phase numbers are preserved:

1. Application Foundation + Full Clickable Prototype
2. Real File Upload & Media Library
3. FFmpeg / FFprobe Media Processing
4. Independent faster-whisper Worker
5. Laravel ↔ Transcription Worker Integration
6. Advanced Transcript UX
7. Production Hardening

Future Voxora capabilities such as workspace evolution, richer export,
translation, search, advanced AI, realtime, collaboration, SaaS, and
organizational knowledge remain unnumbered future roadmap stages until a
separate mapping and authorization is approved.

The current Phase 2 ownership and ingestion contracts are preserved. This ADR
does not authorize P2-003, Phase 3 implementation, FFmpeg/FFprobe, queues,
Redis, Horizon, faster-whisper, transcription generation, or future-SaaS
schema changes.

ADR-002 remains a high-level decision for an independently deployable
transcription worker. Its transport, job/result schemas, storage access,
timeouts, retry semantics, heartbeat/cancellation, idempotency boundary, and
failure classification remain future decisions required before the relevant
worker/integration execution gates.

The provider boundary remains thin and replaceable. Multi-provider registries,
routing, fallback, complex capability registries, and broad provider-error
taxonomies are not current requirements and require evidence from a second
provider before adoption.

Future architecture must explicitly decide actor-versus-owner and
multi-tenancy semantics before admin-on-behalf-of, collaboration, team
ownership, or commercial multi-tenant implementation. No ownership schema is
changed by this ADR.

Governance work uses S/M/L scaling: S for small bounded low-risk work, M for
normal feature/task work, and L for architecture-, security-, data-integrity-,
migration-, or high-regression-risk work. All tiers retain objective, scope,
acceptance criteria, required tests, relevant regressions, and independent
verification where required by the State-to-Action Contract.

Formal independent review requires independent reconstruction of evidence. A
fresh context is preferred, vendor/model identity alone is insufficient, and
review artifacts must distinguish CI evidence, implementer-reported results,
and reviewer-reproduced commands. Reviewers may execute read-only checks when
their environment permits it and must not claim reproduction when they did not
run the command.

Before Phase 3 media parsing, a media parsing/FFmpeg trust-boundary decision is
required. Before worker/integration execution, worker operational and
capacity/concurrency decisions are required. Before first production use,
deployment, migration safety, rollback, backup/restore, monitoring,
failed-job visibility, retention/deletion, derived-artifact deletion,
log/privacy, and legal/privacy validation gates must be recorded.

Governance artifacts must cross-reference their owning authority rather than
restate competing rules. Agents must not silently modify governance to fit
implementation, and historical ADRs, task files, and review artifacts must not
be rewritten to simulate consistency with newer governance.

Reason:

The reconciliation resolves the proposed suite's phase-numbering conflict,
authority duplication, overstatement of worker/provider readiness, and missing
review/production boundaries while preserving the live repository's accepted
Phase 2 contract and current architecture.

Reference:

AGENTS.md; .ai/guidelines/orchestration-policy.md; plan.md; CURRENT_STATE.md;
architecture.md; ADR-002; ADR-007; ADR-008; ADR-009; ADR-010

## ADR-012 — Product Owner Authorization and Phase 2 Remediation

Date: 2026-09-13

Status: ACCEPTED — remediation awaiting independent re-review

Decision:

The Human Product Owner confirms that the current Phase 2 implementation was
authorized to proceed. The prior repository records failed to capture that
authorization correctly; this is a governance/audit-trail failure, not a reason
to revert the implementation. The original independent review finding about
"unauthorized implementation" is preserved unchanged in
`reviews/P2-004A-P2-004A1-P2-005-independent-review.md`.

This authorization is limited to remediation of P2-004A, P2-004A1, P2-005, and
the affected P2-003 MediaIngestionService regression surface. It authorizes the
existing narrow staging-claim/cleanup implementation, its mandatory race and
orphan-safety tests, and the minimum FFprobe/FFmpeg runtime required for
P2-005 metadata probing. It does not authorize transcription, speech
recognition, translation, queues, workers, Redis, Horizon, P2-007, or Phase 3.

FFprobe/FFmpeg is therefore an approved Phase 2 media-ingestion dependency for
P2-005 only. Its boundary is limited to probing an already persisted private
media object and normalizing the existing nullable metadata fields. It does
not open the repository's Phase 3 media-processing scope.

State reconciliation:

- authorized: this ADR and the Product Owner message dated 2026-09-13;
- implemented: current code and tests, subject to independent review;
- awaiting review: P2-003, P2-004A, P2-004A1, and P2-005;
- VERIFIED: only after independent re-review succeeds;
- DONE: only after Work records closure following VERIFIED.

The later staging-claim changes modified `app/Actions/MediaIngestionService.php`
after the prior P2-003 VERIFIED boundary. The prior P2-003 review is historical
evidence for its earlier revision; the current service, its claim lifecycle,
compensation paths, and related contracts are in the next independent review.

P2-004 and P2-006 remain closure-only records because their original core
coverage recommendations remain logically valid; their closure does not make
the affected tasks VERIFIED or DONE and does not authorize P2-007.

Reason:

This decision reconciles the authorization mismatch while preserving the audit
trail, authorizes the explicitly requested bounded remediation, and prevents
the implementation from being discarded or expanded into later phases.

## ADR-013 — Defer Automated Abandoned-Staging Cleanup

Date: 2026-09-13

Status: ACCEPTED — current Phase 2 gate

Decision:

The Human Product Owner approves Option D from the P2-004A/P2-004A1 SQLite
concurrency decision package. Automated abandoned-staging cleanup is deferred
out of the current Phase 2 completion scope. The unresolved cleanup mechanism
must not be executed or scheduled, and the current `lockForUpdate()` usage is
not treated as a valid SQLite row-lock concurrency guarantee.

Existing synchronous compensation, failure recovery, retry, ownership, and
same-attempt idempotency behavior established by P2-002B/P2-003 remains
authoritative. Accumulation of abandoned staging artifacts is an explicitly
accepted interim operational cost. No automated process may delete abandoned
staging under the unresolved P2-004A/P2-004A1 mechanism.

P2-004A and P2-004A1 remain BLOCKED, not VERIFIED or DONE. The canonical
State-to-Action Contract has no DEFERRED state, so their BLOCKED state is
retained and annotated as deferred out of the current gate rather than
inventing a new lifecycle state. Their complete review and remediation history
is preserved.

Option B — an SQLite-defined atomic cleanup/claim protocol independent of
unsupported row-level `FOR UPDATE` locking — is the preferred future
resolution class. This ADR does not authorize Option A, B, or C, a production
database change, an application redesign, or changes to P2-002B/P2-003.

Future cleanup work requires separate authorization and must satisfy the
independent concurrency verification gate in
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`, including
genuine independent database connections/processes and real contention.

Phase consequence:

This decision does not complete Phase 2, authorize P2-007, or authorize Phase
3. Phase completion remains subject to the repository's phase gate and Human
Product Owner acceptance. P2-007 is not eligible because no task promotion or
authorization exists in `plan.md`/`CURRENT_STATE.md`; it remains not eligible
and not authorized.

Reason:

Deferral removes the unresolved automated cleanup mechanism from the current
execution scope while preserving the accepted upload and retry contracts and
the repository's three-cycle escalation boundary.

## ADR-014 — Human Acceptance of the Bounded Phase 2 Scope

Date: 2026-09-13

Status: ACCEPTED — Phase 2

Decision:

The Human Product Owner accepts the current bounded Phase 2 scope as
reconciled. P2-003 and P2-005 remain DONE based on independent verification.
P2-004A and P2-004A1 remain BLOCKED and explicitly deferred from the current
Phase 2 gate; neither may be marked VERIFIED or DONE as a consequence of this
acceptance.

The Option D decision in ADR-013 remains in force: automated abandoned-staging
cleanup is unauthorized, the unresolved SQLite cleanup mechanism must not be
scheduled or executed, existing P2-002B/P2-003 retry, compensation, ownership,
and same-attempt idempotency behavior remains authoritative, and interim
abandoned-staging accumulation is accepted as an operational trade-off.

Future cleanup requires separate architectural and implementation
authorization and must satisfy the genuine-concurrency verification gate in
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`. Option B is
the preferred future resolution class, but no Option A, B, or C implementation
is authorized by this ADR.

Phase consequence:

Phase 2 transitions from `AWAITING HUMAN ACCEPTANCE` to the existing phase-level
`ACCEPTED` status used for Phase 1. This phase acceptance does not create,
promote, or authorize P2-007 and does not make P2-007 eligible. Phase 3 remains
not eligible and not authorized. Completion of one phase never authorizes the
next phase.

References:

`AGENTS.md`; `.ai/guidelines/orchestration-policy.md`;
`CURRENT_STATE.md`; `plan.md`; `RTFTT-MASTER-ROADMAP.md`; ADR-013;
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`.

## ADR-015 — Simplify to a Two-Agent Operating Model (Supersedes ADR-010)

Date: 2026-09-15

Status: ACCEPTED

Supersedes: ADR-010 (Canonical Multi-Agent Operating Model), in full. The
agent-role definitions and orchestration portions of ADR-004 remain
superseded as ADR-010 already established; ADR-004's repository-handoff
principles (task files, review files, CURRENT_STATE.md, DECISIONS.md, Git
history as shared state) continue unaffected and remain authoritative.

Decision:

The repository moves from the five-role model in ADR-010 (Work, OpenCode,
Claude Code, Codex, Human Product Owner) to a two-agent operating model:

- **OpenCode** — Builder. The implementation agent. Implements the assigned
  task, creates/updates tests, runs verification (tests, lint, typecheck),
  and moves the task to REVIEW. Must not mark its own work VERIFIED, close
  its own tasks as DONE, expand product scope, make Human Product Owner
  decisions, or begin unrelated tasks automatically.

- **Claude Code** — Independent Reviewer. Reviews implementation correctness,
  verifies acceptance criteria, inspects architecture and security
  boundaries, assesses regressions and edge cases, and produces durable
  review artifacts. Returns VERIFIED or CHANGES_REQUESTED. Must not modify
  implementation code during review, become the implementation owner, review
  its own implementation, or make product decisions.

- **Ridzwan / Human Product Owner** — Decider. Owns decisions involving
  product scope, materially different UX behavior, major architecture,
  security-sensitive decisions, destructive operations, production
  deployment, milestone acceptance, phase completion, and phase
  authorization. Closes VERIFIED tasks as DONE. Decides BLOCKED tasks.

- **Repo** — Remember. The repository remains the shared durable memory and
  source of truth; routine agent-to-agent communication happens through
  repository artifacts rather than manual relay.

The dedicated **Work** orchestration layer and the **Codex**
investigate/secondary-implementation role introduced by ADR-010 are removed.
Task assignment and closure that ADR-010 assigned to Work now follow the
State-to-Action Contract in `.ai/guidelines/orchestration-policy.md`
directly: the Human Product Owner assigns READY tasks (or OpenCode
self-assigns where already authorized) and closes VERIFIED tasks as DONE.
The single-active-implementation-owner rule remains mandatory.

Reason:

ADR-010's five-role model (Work, OpenCode, Claude Code, Codex, Human Product
Owner) added an orchestration layer and a secondary implementation role that
were not in active use, adding coordination overhead without a corresponding
governance benefit. The Human Product Owner directed a simplification back to
the two builder/reviewer agents already doing the work, with task routing and
closure handled directly through the existing State-to-Action Contract rather
than through a dedicated Work role.

This ADR resolves the authority conflict where `.ai/guidelines/
orchestration-policy.md` and `AGENTS.md` had already been updated to the
two-agent model and stated they superseded ADR-010, while ADR-010 itself
remained ACCEPTED and undisputed in this log. `AGENTS.md`,
`.ai/guidelines/orchestration-policy.md`, `.ai/guidelines/ai-development-os.md`,
and `CLAUDE.md` were the leading updated artifacts; this ADR is the durable
decision record that formally supersedes ADR-010 to match them.

Historical task and review records created under the ADR-010 model (for
example, entries recording `Implementation Owner: Codex`) are preserved as
historical truth and are not retroactively changed by this ADR.

Reference:

`AGENTS.md`; `.ai/guidelines/orchestration-policy.md`;
`.ai/guidelines/ai-development-os.md`; `CLAUDE.md`; `CURRENT_STATE.md`;
ADR-010; ADR-004.

## ADR-016 — Approve Option B Claim CAS Protocol for P2-004A/P2-004A1

Date: 2026-09-15

Status: ACCEPTED

Amends: ADR-013 (Defer Automated Abandoned-Staging Cleanup). Option D
remains in force until the implementation authorized here is independently
VERIFIED; this ADR authorizes work toward Option B, it does not itself mark
P2-004A/P2-004A1 VERIFIED or DONE, and it does not lift the Option D
deferral until that verification succeeds.

Decision:

The Human Product Owner approves the concrete claim protocol recorded in
`reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md` as the selected
Option B mechanism from `reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`.

The protocol replaces `lockForUpdate()` (a no-op on SQLite) with
single-statement, guarded `UPDATE`/`INSERT ... OR IGNORE` transitions on a
new `held_by`/`cleanup_claimed_at` pair of columns on `staging_claims`,
moves staging file deletion outside the decision transaction, and requires
proof of the race using genuine independent processes (not a single-process
simulation).

This resolves DECISION-P2-CONCURRENCY-002 (Option 1) in DECISION_QUEUE.md.

A new, narrowly-scoped implementation task (P2-004A2) is authorized,
recorded in `tasks/P2-004A2-staging-claim-cas-protocol.md`. Its scope is
limited to exactly: the new migration, `MediaIngestionService`,
`CleanupStaging`, and the new race tests described in the proposal. No
other P2-004A2-adjacent scope, P2-004B+, P2-005+, or Phase 3 work is
authorized by this ADR.

Reason:

The prior three CHANGES_REQUESTED cycles on P2-004A/P2-004A1 failed because
the task asked for a proven concurrency guarantee without specifying the
exact mechanism, leaving the implementation owner to invent one. This ADR
removes that ambiguity by approving a specific, already-designed mechanism
before implementation begins, and by requiring the specific test approach
(genuine independent processes) that the prior cycles lacked.

Phase consequence:

P2-004A and P2-004A1 remain BLOCKED as historical records; this ADR does
not reopen or retroactively change them. P2-004A2 is READY. Its closure
(VERIFIED, then Human Product Owner closure to DONE) is required before
Option D may be considered lifted. This ADR does not authorize P2-007,
Phase 3, or any later phase.

Reference:

`reviews/P2-004A-P2-004A1-option-b-protocol-proposal.md`;
`reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`;
`tasks/P2-004A2-staging-claim-cas-protocol.md`; ADR-013; DECISION_QUEUE.md
(DECISION-P2-CONCURRENCY-002).

## ADR-017 — Phase 3 Boundary Amendment: Real Transcription Engine

Date: 2026-09-17

Status: ACCEPTED

Supersedes: The earlier Phase 3/4/5 decomposition as recorded in
`RTFTT-MASTER-ROADMAP.md`, `plan.md`, and `architecture.md` where they
conflict with this ADR. Historical references to the old boundary are
preserved with supersession annotations.

Decision:

Phase 3 is redefined as the complete Real Transcription Engine, encompassing
the full path from media ingestion through self-hosted transcription to
persisted transcript and segment results. The earlier decomposition that
treated Phase 3 as FFmpeg/FFprobe-only, Phase 4 as faster-whisper worker,
and Phase 5 as Laravel-worker integration is superseded.

### Phase 3 scope

Phase 3 delivers:

- provider-neutral transcription domain contract;
- internal authenticated Python worker (faster-whisper + FFmpeg);
- private/internal HTTP transport;
- Redis-backed asynchronous queue processing;
- transcript persistence;
- segment persistence with per-segment language;
- multilingual/code-switching support (BM, English, Chinese, Tamil);
- retry, recovery, and failure hardening;
- real end-to-end integration verification.

### Canonical provider

Self-hosted faster-whisper. No hosted OpenAI/Deepgram ASR is canonical.

### Segment language granularity

One dominant/best-supported language per segment (OD-01). Word/span-level
language tagging is outside Phase 3.

### Language identifiers

BCP 47-compatible. Required vocabulary: `ms`, `en`, `zh`, `ta`, `und`.

### Model selection

Preferred: `turbo`. Mandatory benchmark gate: turbo vs large-v3 before
P3-003 finalization (OD-02).

### Worker transport

Private/internal HTTP (OD-03). Laravel ↔ Python boundary.

### Queue backend

Redis without Horizon initially (OD-06).

### Media access

Shared private filesystem with server-generated opaque references (OD-07).
Laravel must not load the full 500 MiB media object through PHP memory.

### Prepared audio retention

Configurable. Default: ephemeral. Supported: durable_until_terminal (OD-08).

### Worker authentication

Private/internal network + shared-secret bearer token from env/config (OD-09).

### Accuracy policy

No mandatory WER threshold. BM/Tamil may differ from English/Chinese.

### Batch execution model

Up to three sequential implementation tasks per OpenCode batch, followed by
one independent Claude review. IMPLEMENTED ≠ VERIFIED. OpenCode may not
self-assign VERIFIED or DONE.

### Existing lifecycle reuse

Phase 3 reuses existing enums:

TranscriptionStatus: draft, queued, preparing, transcribing, completed,
failed, cancelled.

ProcessingStatus: queued, running, completed, failed, cancelled.

ProcessingStage: upload, probe, extract_audio, transcribe, finalize.

Phase 3 must not introduce parallel lifecycle vocabularies.

### Phase 2 interaction

Phase 3 consumes durable private MediaFile objects. It does not depend on
Phase 2 staging cleanup. Option D (ADR-013) remains in force. P2-004A and
P2-004A1 remain BLOCKED.

### Configuration boundary

Phase 3 configuration centralized in a transcription-oriented config
surface. Secrets from environment. No committed secrets.

### Observability

Cross-cutting acceptance criteria owned by the implementing task. Minimum
correlation: request_id, transcription_id, processing_job_id, media_file_id,
stage, attempt_number, model, device, duration_ms, failure_code.

Reason:

The earlier Phase 3/4/5 decomposition assumed a sequential build-out that
separated FFmpeg processing, worker implementation, and queue integration
into distinct phases. The HPO determined that a single integrated Phase 3
delivering the complete transcription engine is more appropriate for the
current project stage. This ADR formally amends the phase boundary and
records the approved owner decisions OD-01 through OD-12 (see canonical
specification for full definitions).

Phase consequence:

Phases 4 and 5 as previously defined are absorbed into Phase 3. Future
roadmap phases (6, 7) and their boundaries remain to be reconciled after
Phase 3 is approved. This ADR does not authorize Phase 3 implementation;
it records the planning baseline for governance reconciliation.

Reference:

`PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md`;
`Phase 3 — Real Transcription Engine Technical Specification`;
`plan-phase3-media-processing.md`; ADR-002; ADR-013; ADR-014; ADR-016;
`RTFTT-MASTER-ROADMAP.md`; `architecture.md`; `CURRENT_STATE.md`; `plan.md`.
