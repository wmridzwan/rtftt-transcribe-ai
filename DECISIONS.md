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

`PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md` (canonical durable specification);
ADR-002; ADR-013; ADR-014; ADR-016;
`RTFTT-MASTER-ROADMAP.md`; `architecture.md`; `CURRENT_STATE.md`; `plan.md`.

## ADR-018 — Phase 3 Batch 3: Retry / Recovery Contract and Authorization

Date: 2026-09-19

Status: ACCEPTED

Amends: The canonical transcription lifecycle (the `failed` terminal rule in
`TranscriptionLifecycle`), as a narrowly-scoped extension. Does not supersede
ADR-017 or the Phase 3 canonical specification; it operationalizes the
retry/recovery portion of that scope. Option D (ADR-013) remains in force.

Decision:

The Human Product Owner resolved the Batch 3 owner decisions B3-01 through B3-07
and authorized Phase 3 Batch 3 (`DECISION-P3-BATCH3-001`) for P3-007 (Failure /
Retry / Recovery Hardening) and P3-008 (Real Phase Integration Verification).
P3-007 and P3-008 are promoted to READY.

### B3-01 — Same-transcription retry

Approved same-transcription retry with a new processing attempt. The canonical
lifecycle is extended with `failed → queued`, reachable only through an explicit
authorized retry action. Each retry creates a new `ProcessingJob`; the previous
failed attempt remains immutable historical evidence and is never reused or
reset. The transcription identity is unchanged.

### B3-02 — Manual domain retry only

Phase 3 implements manual domain retry only. No automatic domain-level retry is
introduced, and no automatic domain retry scheduler exists. Laravel transport
retry is distinct from domain transcription retry and remains effectively
single-attempt per the established queue contract. Automatic retry policy may be
considered in a future phase.

### B3-03 — No automatic retry schedule

No automatic retry count or backoff schedule is introduced in Phase 3. Each
explicit valid manual retry request may create one new attempt.
Concurrency/idempotency protection ensures repeated or concurrent submissions of
the same retry action cannot create multiple simultaneously active attempts. No
arbitrary lifetime retry cap is imposed. Historical attempts remain
observable/auditable.

### B3-04 — Completed transcriptions protected

Completed transcriptions cannot be retried or retranscribed under P3-007. For
`status = completed`, the retry action must reject or safely no-op. Transcript
text, detected language, segments, completion metadata, and successful attempt
history must not be overwritten. Retranscription/reprocessing of completed media
is a separate future product feature outside Phase 3.

### B3-05 — Abandoned running-attempt recovery

Phase 3 must support recovery of abandoned `running` attempts, but recovery must
not automatically start a new inference attempt. Canonical semantics:

```text
running attempt
→ demonstrably stale/abandoned
→ terminal recoverable failure state
→ transcription becomes eligible for explicit manual retry
```

The stale threshold is derived conservatively from the actual current provider
execution timeout contract/configuration (~300 seconds), not an arbitrary
hard-coded value. A stale-authority guard must prevent an old worker that later
resumes from completing or overwriting newer authoritative state. The minimum
additive schema needed to prove staleness safely may be used; heartbeat
infrastructure is not introduced unless strictly necessary.

### B3-06 — Live Redis evidence mandatory for the Phase 3 gate

Live Redis integration evidence is mandatory before P3-008 can be VERIFIED.
P3-008 must exercise an actual Redis instance and retain evidence of: dispatch
through the configured Redis connection; the `transcription` queue; real
serialization/deserialization; worker consumption of the Redis job;
authoritative DB state reload; completion through the established pipeline; no
media path/binary in the serialized job; and valid duplicate/stale protections
where applicable. If Redis is unavailable, P3-008 remains unverified.
Database-queue evidence is not a substitute for live Redis evidence.

### B3-07 — Real FFmpeg + faster-whisper evidence mandatory for the Phase 3 gate

A real self-hosted FFmpeg + faster-whisper end-to-end execution is mandatory
before P3-008 can be VERIFIED. Mocks remain valid for unit/feature coverage but
cannot satisfy the final integration requirement. A small controlled
representative fixture is used. Evidence must demonstrate the real canonical
path: private MediaFile → authorized opaque media resolution → FFmpeg extraction
→ faster-whisper → normalized provider-neutral result → Laravel persistence →
completed transcription, including actual FFmpeg invocation, actual
faster-whisper worker execution, accurate model/config recording,
provider-boundary crossing, text/segment persistence, ephemeral extracted-audio
cleanup, and a path/binary-free queue payload. For code-switching coverage, a
suitable real fixture may be used or the real-worker smoke run may be
supplemented with deterministic normalized-result fixtures; the real-worker
requirement does not require every scenario to invoke the large model. No false
claim may be made if model/runtime execution is unavailable.

### Failure taxonomy authority

Laravel's provider-neutral `TranscriptionFailure` taxonomy is the authoritative
domain retryability source. The worker-provided `retryable` value is transport
metadata/advisory only and must not independently control domain retry policy.
P3-007 must make the cross-layer contract internally consistent (current
discrepancy: worker `FFMPEG_FAILED: retryable=true` vs Laravel
`FfmpegFailed->isRetryable() = false`; `HttpTranscriptionProvider` currently
discards the worker flag). If `FFMPEG_FAILED` remains non-retryable under the
canonical Laravel taxonomy, the worker/envelope behavior must be aligned or
explicitly normalized at the provider boundary.

### Concurrency

The CAS/guarded state transition is the primary correctness boundary; a partial
unique index may provide defense-in-depth. SQLite concurrency claims require
genuine independent-process/database-connection evidence per the ADR-013 /
ADR-016 / P2-004A2 precedent. Concurrent retry requests must be proven to
produce exactly one new active attempt; sequential-only tests are insufficient.
Any partial unique index must be additive, SQLite-compatible, deterministically
reversible, and must not replace the CAS protocol.

### Execution dependency

P3-007 must be implementation-complete, frozen, and independently VERIFIED
before P3-008 executes the final Phase 3 integration verification. P3-008 may
prepare fixtures/harness earlier but must not be VERIFIED while P3-007 remains
unverified. Batch 3 authorization does not close Phase 3; Phase 3 closure
requires its own later HPO decision under the Completion Gate.

Reason:

The Batch 3 plan (`PHASE3-BATCH3-PLANNING.md`) identified a blocking lifecycle
contradiction and seven product/architecture decisions. The HPO resolved them
in favor of a narrow, manual, bounded, evidence-gated retry/recovery contract
that preserves all prior VERIFIED/DONE Batch 1/Batch 2 contracts and the
provider-neutral architecture.

Phase consequence:

P3-007 and P3-008 are READY. Batch 3 is authorized. Phase 3 remains IN PROGRESS
and is not closed. Batch 1/Batch 2 closures are unchanged. No application, test,
migration, queue, worker, provider, schema, or configuration change is authorized
by this ADR beyond the P3-007/P3-008 task contracts.

Reference:

`PHASE3-BATCH3-PLANNING.md`; `DECISION_QUEUE.md`
(DECISION-P3-BATCH3-001, B3-01 through B3-07);
`tasks/P3-007-failure-retry-recovery-hardening.md`;
`tasks/P3-008-real-phase-integration-verification.md`;
`PHASE3-REAL-TRANSCRIPTION-ENGINE-SPEC.md`; ADR-013; ADR-016; ADR-017;
`.ai/guidelines/orchestration-policy.md`.

## ADR-019 — Phase 4/5 Boundary Reconciliation: Transcript Experience Baseline

Date: 2026-09-19

Status: ACCEPTED — Human Product Owner decision 2026-09-19 (D4-01), ratified
together with the Phase 4 task-contract-authoring authorization
(DECISION-PHASE4-AUTHORIZATION-001).

Proposal history: This ADR was first recorded as PROPOSED in the Phase 4 planning
package (`PHASE4-PLANNING.md`, 2026-09-19). The Human Product Owner resolved
D4-01 through D4-07 on 2026-09-19 and accepted the boundary below. The proposal
text is preserved; the decided outcomes are recorded in "HPO Decision Outcomes".

Amends: The Phase 4/5 boundary left unreconciled by ADR-017 ("Phases 4 and 5 as
previously defined are absorbed into Phase 3... their future boundaries will be
reconciled separately"). Does not supersede ADR-017 or ADR-018 and does not
change any Phase 3 contract.

### HPO Decision Outcomes (2026-09-19)

| ID | Decision | Outcome |
|----|----------|---------|
| D4-01 | Phase boundary | ACCEPTED as proposed: Phase 4 = Transcript Experience baseline; Phase 5 = Translation; Phase 6 = Advanced Transcript UX (reserved); Phase 7 = Production Hardening (reserved) |
| D4-02 | Media playback delivery | Authorized application range-stream endpoint behind the existing media/transcription ownership policy; byte ranges, `206`, correct `Content-Type`/`Accept-Ranges`, audio+video, no path leakage, no full-object PHP memory load. Signed temporary storage URLs are not the Phase 4 baseline |
| D4-03 | Export set | Keep TXT, SRT, VTT, DOCX; harden and verify the existing implementation; do not rebuild or remove DOCX |
| D4-04 | Search | Client-side search over loaded persisted segments; no server-side full-text infrastructure or DB index in Phase 4; Unicode-safe (Latin/Chinese/Tamil) |
| D4-05 | Transcript editing | Excluded from Phase 4; deferred to Phase 6. Phase 4 transcript content is read-only |
| D4-06 | Player scope | Audio + video, play/pause, volume, seek, click-timestamp seek, ms-accurate target, synchronized active-segment highlighting; no waveform/timeline/clipping/annotations. Playback speed optional only if a small baseline control |
| D4-07 | Review model | Per-task implementation and independent review; no Phase-3-style batch-review exception; P4-006 is the final independent integration gate |

Decision:

Phase 4 is defined as the Transcript Experience baseline: the read, navigate,
search, copy, and export experience over completed persisted Phase 3
transcripts, plus authorized private media playback. Phase 5 is defined as
Translation (original-vs-translated, Bahasa Melayu Malaysia output, persistence,
and UX). Phase 6 remains reserved for Advanced Transcript UX and Phase 7 remains
reserved for Production Hardening.

### Phase 4 scope

- authorized private media playback (range/streaming, ownership-enforced);
- timestamp seeking and synchronized segment highlighting;
- in-transcript search (client-side);
- copy (full transcript and per-segment);
- export hardening over completed persisted transcripts (TXT/SRT/VTT/DOCX);
- Phase 4 integration verification.

### Explicit Phase 4 non-scope

- translation (Phase 5);
- transcript editing, diarization/speaker labels, chapters, annotations
  (Phase 6);
- AI/summary/chat and live/realtime capabilities;
- Horizon, automatic domain retry, provider-abstraction redesign;
- retranscription/reprocessing of completed transcriptions;
- production deployment, monitoring, retention/deletion, backup/restore,
  PostgreSQL/Redis certification (Phase 7);
- actor-vs-owner, admin-on-behalf-of, and multi-tenancy semantics.

### Preserved contracts

Phase 3 queue, retry, failure-taxonomy, language, no-speech, atomic-persistence,
and media contracts remain authoritative and unchanged. Completed transcriptions
remain protected.

Reason:

ADR-017 absorbed the earlier Phase 3/4/5 decomposition into Phase 3 and left
Phase 4/5 open. The product priority sequence after reliable transcription is
transcript experience, then translation. Assigning Phase 4 to the Transcript
Experience baseline and Phase 5 to Translation reconciles the roadmap without
disturbing the reserved Phase 6/7 boundaries.

Phase consequence:

Phase 4 boundary = DECIDED and this ADR = ACCEPTED. Phase 4 is AUTHORIZED FOR
TASK-CONTRACT AUTHORING only (DECISION-PHASE4-AUTHORIZATION-001); implementation
is NOT YET AUTHORIZED. Task contracts may be authored and refined, but no task
may be promoted to READY until a separate HPO task/batch implementation
authorization is recorded. This ADR authorizes no application code, test,
migration, route, controller, Livewire component, streaming endpoint, JavaScript
behavior, UI, schema, or configuration change. Supporting package:
`PHASE4-PLANNING.md`; decided decisions: D4-01 through D4-07 in
`DECISION_QUEUE.md`.

Reference:

`PHASE4-PLANNING.md`; `DECISION_QUEUE.md` (D4-01 through D4-07;
DECISION-PHASE4-AUTHORIZATION-001); `RTFTT-MASTER-ROADMAP.md`; `plan.md`;
`CURRENT_STATE.md`; ADR-013; ADR-017; ADR-018;
`.ai/guidelines/orchestration-policy.md`.

## ADR-020 — Playwright Verification Tooling for Phase 4

Date: 2026-09-20

Status: ACCEPTED

Amends: `DECISION-P4-BROWSER-VERIFICATION-001` (preserved, not erased).
Records `DECISION-P4-BROWSER-VERIFICATION-002`.

Decision:

The initial Phase 4 browser-verification strategy (documented
environment-dependent manual browser verification, no automation) was not
reliably executable by the CLI implementation agent, which has no GUI browser.
This satisfies the escalation condition recorded in
`DECISION-P4-BROWSER-VERIFICATION-001`.

Playwright is authorized as dev/test-only verification tooling for Phase 4:

- P4-003 browser verification;
- P4-006 final Phase 4 integration verification;
- directly related Phase 4 browser regression evidence where required.

Constraints: not a production or product-runtime dependency; not a frontend
architecture; does not replace Pest/PHP tests; Chromium-only unless a contract
requires otherwise; minimal configuration; no unrelated JavaScript tooling; no
general Phase 5/6/7 authorization. Any use beyond Phase 4 verification requires
separate explicit authorization.

Reason:

P4-003 requires real `HTMLMediaElement` behavior (seek precision, active-segment
synchronization, auto-scroll, keyboard) that backend tests cannot prove. Manual
GUI verification was not executable by the agent; an automated, reproducible
harness was required.

Phase consequence:

P4-003 browser evidence was completed under this ADR (ten Playwright checks
passed; see `P4-003-BROWSER-VERIFICATION-EVIDENCE.md`). P4-003 remains REVIEW.
P4-006 remains BACKLOG and still requires separate authorization. No
application, schema, or worker change is authorized beyond verification tooling.

Reference:

`DECISION_QUEUE.md` (`DECISION-P4-BROWSER-VERIFICATION-001`,
`DECISION-P4-BROWSER-VERIFICATION-002`); `P4-003-BROWSER-VERIFICATION-EVIDENCE.md`;
`tasks/P4-003-segment-navigation-synchronized-highlighting.md`;
`verification/` (Playwright harness, seed, router).

## ADR-021 — Cross-Phase Browser Verification Tooling (Playwright)

Date: 2026-09-21

Status: ACCEPTED — Human Product Owner, under the Phase 5–7 Controlled Parallel
Execution Authorization (2026-09-21), resolving DC-01.

Amends/Generalizes: ADR-020 (`Playwright Verification Tooling for Phase 4`).
ADR-020 is preserved unchanged as the Phase 4 record; this ADR generalizes the
tooling authorization to Phases 5, 6, and 7 without retroactively changing the
Phase 4 history.

Decision:

Playwright is the canonical dev/test-only browser verification tooling for
Phases 5, 6, and 7, under the following constraints:

- it is not a production or product-runtime dependency;
- it remains Chromium-based unless a separate contract requires another browser;
- it does not replace Pest/PHP tests;
- each phase still requires its own explicit implementation authorization before
  browser-dependent work is treated as governed;
- browser evidence is required where an approved contract marks behavior as
  browser-material (translation workspace, transcript editing, production
  readiness flows);
- mock or server-only tests may not be represented as real browser integration
  evidence.

Allowed tooling: Playwright test runner and the existing `verification/`
harness conventions (config, seed, router, auth setup, fixtures), extended
additively as needed.

Evidence expectations: retained per-task artifacts naming the task, the checks
performed, pass/fail, and any residual flake, following the Phase 4 evidence
pattern.

Per-phase authorization: this ADR does not itself authorize Phase 5, 6, or 7
implementation. Each phase requires its own HPO authorization. Phase 5 is
authorized separately (`DECISION-PHASE5-AUTHORIZATION-001`).

Reason:

Browser behavior is material across Phase 5 (translation workspace), Phase 6
(editing), and Phase 7 (production browser matrix), and the Phase 4 CLI agent
had no GUI browser. Keeping ADR-020 Phase-4-only while requiring per-phase
re-authorization would be repetitive and risk inconsistent harness usage. A
single explicit cross-phase ADR with per-phase authorization is clearer and
preserves the Phase 4 record.

Phase consequence:

Browser verification tooling is governed for P5/P6/P7. No application, schema,
worker, or runtime change is authorized by this ADR. DC-01 is RESOLVED.

Reference:

`DECISION_QUEUE.md` (DC-01, `DECISION-PHASE5-AUTHORIZATION-001`);
`PHASE5-7-DECISION-REGISTER.md` (DC-01); `PHASE5-7-EXECUTION-CLASSIFICATION.md`;
ADR-020; `verification/`.

## ADR-022 — Phase 5 Translation Contract Decisions and Implementation Authorization

Date: 2026-09-21

Status: ACCEPTED — Human Product Owner, under the Phase 5–7 Controlled Parallel
Execution Authorization (2026-09-21).

Supersedes: the OPEN/CANDIDATE status of D5-01 through D5-09 recorded in
`PHASE5-7-DECISION-REGISTER.md`. The planning register is preserved; these
decisions are now frozen.

Decision:

The Human Product Owner freezes the Phase 5 translation contract and authorizes
Phase 5 for implementation:

| ID | Decision |
|----|----------|
| D5-01 | Segment-aligned hybrid translation unit; persisted translation is segment-aligned. |
| D5-02 | Initial target languages: `ms`, `en`, `zh`, `ta`; source may also be `und`; code-switched transcripts are valid. |
| D5-03 | Multiple persisted translations are allowed per source transcript/revision according to target-language/lifecycle identity. |
| D5-04 | Provider-neutral application boundary with a self-hosted default translation provider. |
| D5-05 | Translation is derived data and must not mutate the machine transcript source. |
| D5-06 | Dedicated translation persistence must be used; do not store translation content in `transcriptions` or `transcription_segments`. |
| D5-07 | Translation UX belongs within the transcript workspace. |
| D5-08 | Translated exports: TXT, SRT, VTT, DOCX. |
| D5-09 | Final Phase 5 integration verification must include the real authorized self-hosted provider/model path; mocks cannot substitute for the final real gate. |

Phase 5 = AUTHORIZED FOR IMPLEMENTATION.

Phase consequence:

Phase 5 task contracts (`tasks/P5-001..P5-008`) may be authored and promoted to
READY by the Human Product Owner. P6/P7 early-start work is limited to the
allowlists in `PHASE5-7-EXECUTION-CLASSIFICATION.md`. Phase 6 and Phase 7 remain
NOT GENERALLY AUTHORIZED. Phase closure still requires independent verification
and explicit HPO closure. No frozen Phase 3/4 contract is changed.

Reference:

`PHASE5-PLANNING.md`; `PHASE5-7-EXECUTION-CLASSIFICATION.md`;
`PHASE5-7-DECISION-REGISTER.md`; `DECISION_QUEUE.md`
(`DECISION-PHASE5-AUTHORIZATION-001`, D5-01..D5-09, DC-01); ADR-017; ADR-018;
ADR-019; ADR-021.

## ADR-023 — Controlled Parallel Execution Model for Phases 5–7

Date: 2026-09-21

Status: ACCEPTED — Human Product Owner.

Decision:

Phase-level serialization is replaced by dependency-driven parallelism with
strict contract and closure boundaries. Phase 5 is AUTHORIZED FOR
IMPLEMENTATION. Phase 6 and Phase 7 are NOT GENERALLY AUTHORIZED but have
early-start and early-hardening allowlists respectively
(`PHASE5-7-EXECUTION-CLASSIFICATION.md`).

An eligible task may begin once its predecessor reaches
`IMPLEMENTED_PENDING_REVIEW` (committed, stable contract) under the standing
unattended execution authority, without waiting for independent verification;
if review later invalidates a consumed contract, downstream work is reconciled.
This resolves reviewer GOV-1 and ratifies the P5-002-after-P5-001 ordering.

The model defines the execution state machine
(`BACKLOG → READY → IMPLEMENTING → IMPLEMENTED_PENDING_REVIEW → REVIEWING →
VERIFIED → DONE`), the five execution tracks, contract-freeze rules, provisional
decision policy (`DECISIONS-PROVISIONAL.md`), and the blocker policy
(`BLOCKERS.md`). Independent verification and explicit HPO closure remain
mandatory; no phase closes automatically.

Reason:

The previous strict phase serialization underused safe parallelism. The
dependency-driven model maximizes throughput during unattended execution without
weakening architectural governance, phase boundaries, independent verification,
source immutability, or HPO ownership.

Phase consequence:

Phase 5 implementation proceeds. Phase 6/7 early work is limited to the
allowlists. Phase 6 cannot close before Phase 5 is CLOSED; Phase 7 cannot close
before Phase 6 is CLOSED; P7-012 must not run before Phase 6 is CLOSED. No frozen
Phase 3/4 contract or Phase 5 decision is changed.

Reference:

`PHASE5-7-CONTROLLED-PARALLEL-EXECUTION.md`; `PHASE5-7-EXECUTION-CLASSIFICATION.md`;
`PHASE5-7-DEPENDENCY-GRAPH.md`; `BLOCKERS.md`; `DECISIONS-PROVISIONAL.md`;
`reviews/P5-002-independent-review.md` (GOV-1); ADR-021; ADR-022.

## ADR-024 — Canonical Phase 5 Translation Worker Runtime

Date: 2026-09-22

Status: ACCEPTED — Human Product Owner.

Supersedes: the Phase 5 worker dependency pins recorded in `worker/requirements.txt`
at commit `bd30c6d` (`transformers==4.57.6`, `torch==2.9.1`,
`sentencepiece==0.2.1`), and the generic `>=` ranges that preceded them.

Decision:

The Human Product Owner adopts the real runtime set already demonstrated to load
and execute the canonical `facebook/nllb-200-distilled-600M` model as the
canonical Phase 5 translation worker runtime:

| Package | Canonical version |
|---|---|
| `transformers` | `5.17.0` |
| `torch` | `2.14.0` |
| `sentencepiece` | `0.2.2` |

The canonical translation model identity is unchanged:
`facebook/nllb-200-distilled-600M`.

Rationale:

> The previous exact pins were never successfully real-model gated. The new exact
> set has been demonstrated to load and execute the canonical
> `facebook/nllb-200-distilled-600M` model under the current Python/runtime
> environment. Phase 5 will standardize on the proven runtime rather than regress
> to an unverified dependency set.

Phase consequence:

- `worker/requirements.txt` is updated to the authorized exact pins; all Phase 5
  documentation, runtime tables, evidence, and guard tests must reflect this one
  unambiguous declared translation runtime.
- The P5-008 corrective (`DECISION-P5-008-CORRECTIVE-001`) re-provisions a clean,
  valid model cache, commits a reproducible real-gate harness, executes a real
  browser-to-real-model end-to-end flow, and re-runs the canonical gate.
- P5-008 remains `CHANGES_REQUESTED` until the corrective completes; it then
  returns to `IMPLEMENTED_PENDING_REVIEW` for a fresh independent review. This
  ADR does not mark P5-008 VERIFIED, does not close Phase 5, and does not
  authorize Phase 6 or Phase 7.
- No frozen Phase 3/4 contract or other Phase 5 decision is changed. The
  `und → eng_Latn` worker fallback assumption and the config-derived persisted
  model identity are recorded as retained INFO debt (P5-008 corrective §6/§7).

Reference:

`PHASE5-P5-008-INTEGRATION-EVIDENCE.md`; `worker/requirements.txt`;
`worker/TRANSLATION-OPERATIONS.md`; `tasks/P5-008-phase-integration-verification.md`;
`DECISION_QUEUE.md` (`DECISION-P5-008-CORRECTIVE-001`); ADR-022 (D5-04, D5-09).

## Phase 5 Closure — Translation

Date: 2026-09-23

Status: DECIDED — Human Product Owner (`DECISION-P5-008-CLOSURE-001`,
`DECISION-PHASE5-CLOSURE-001`, `DECISION-PHASE5-DEBT-CARRYFORWARD-001`).

Decision:

Phase 5 (Translation) is **CLOSED**. The fresh independent P5-008 verdict
(`reviews/P5-008-independent-review.md`; VERIFIED, no BLOCKER/HIGH/MEDIUM) was
accepted and P5-008 was closed DONE. All Phase 5 tasks are DONE. The real
self-hosted canonical model path (`facebook/nllb-200-distilled-600M`), the
canonical runtime (`transformers==5.17.0`, `torch==2.14.0`,
`sentencepiece==0.2.2`; ADR-024), a real Redis-backed queue run, a committed
integration harness, and a real browser-to-real-model end-to-end flow were
demonstrated; source immutability, ownership isolation, multilingual/code-switch
behavior, and translated TXT/SRT/VTT/DOCX exports were verified; regression and
static analysis pass.

Residual LOW/INFO findings are carried forward as non-blocking deferred debt
(`DECISION-PHASE5-DEBT-CARRYFORWARD-001`); Phase 5 is not reopened to clean them.
No historical finding or corrective cycle is rewritten.

Phase consequence:

Phase 5 closure does not authorize general Phase 6 or Phase 7 implementation.
Phase 6 is now the primary product-development path and may be authorized by a
separate HPO decision; Phase 7 early-hardening may run in parallel only where the
existing contracts prove independence. Phase 7 cannot close before Phase 6 is
CLOSED; P7-012 must not run before Phase 6 is CLOSED. See
`PHASE5-CLOSURE-REPORT.md` and `PHASE6-7-ELIGIBILITY-MATRIX.md`.

Reference:

`DECISION_QUEUE.md` (`DECISION-P5-008-CLOSURE-001`,
`DECISION-PHASE5-CLOSURE-001`, `DECISION-PHASE5-DEBT-CARRYFORWARD-001`);
`reviews/P5-008-independent-review.md`; `PHASE5-P5-008-INTEGRATION-EVIDENCE.md`;
`PHASE5-CLOSURE-REPORT.md`; ADR-022; ADR-023; ADR-024.

## ADR-025 — Phase 6 Editing Model and Cross-Phase Browser Verification

Date: 2026-09-23

Status: ACCEPTED — Human Product Owner.

Decision:

Phase 6 (Advanced Transcript UX) is authorized for contract authoring and
implementation under `DECISION-PHASE6-AUTHORIZATION-001`. The owner decisions
D6-01..D6-09 and DC-01 are adopted as follows:

- D6-01: the completed machine transcription is an **immutable source layer**;
  user edits live in an explicit **editable revision layer**; the machine
  transcript is never overwritten in place.
- D6-02: **persisted** revision/version semantics; undo/redo derives from durable
  revision history (survives reload), not browser-only state.
- D6-03: explicit timestamp editing allowed under canonical timing invariants
  (valid/non-negative; start precedes end; ordering/overlap explicitly defined;
  no silent mutation of the immutable machine source).
- D6-04: split/merge allowed inside the editable revision model; structural edits
  that change segment identity or textual source semantics must **not** silently
  keep a translation current; translation becomes explicitly stale per the later
  translation-invalidation contract; no silent cross-structure remapping.
- D6-05: a user-visible revision/history surface (lightweight initial scope) that
  shows revisions exist and identifies the active revision.
- D6-06: comparison is an explicit source-versus-translation/revision surface
  inside the transcript workspace; no separate product module.
- D6-07: enhanced navigation/search/filtering inside the transcript workspace; no
  standalone search product.
- D6-08: speaker labels / annotations / bookmarks **deferred** (not Phase 6
  required scope).
- D6-09: waveform / timeline **deferred** (not Phase 6 required scope).
- DC-01: the Phase 5 cross-phase browser-verification governance is the Phase 6/7
  default. Browser verification is required when browser behavior is material to
  acceptance; browser evidence never replaces concurrency, persistence,
  authorization, queue/provider integration, or real-service evidence; a browser
  double is never proof of a real backend path.

Task authorization discipline:

Phase 6 authoring begins with P6-001 (Advanced Transcript Editing Domain /
Contract Foundation). There is no blanket READY state; each task is promoted to
READY only after its canonical contract exists and dependencies are reconciled.
P6-006 is separately authorized as an early-start exception
(`DECISION-P6-006-AUTHORIZATION-001`). P7-005 (Observability Foundation) alone is
authorized early (`DECISION-P7-005-AUTHORIZATION-001`); all other Phase 7
implementation stays not authorized.

Reason:

The Phase 6 planning package recommended an immutable machine transcript plus an
editable derived layer to preserve provenance and keep translation alignment
explicit. The HPO adopted that recommendation and the associated revision,
timing, split/merge, comparison, and search decisions, and confirmed deferred
scope boundaries.

Phase consequence:

Phase 6 proceeds in bounded batches with independent review and explicit HPO
closure. Phase 7 remains NOT GENERALLY AUTHORIZED; P7-012 remains
FINAL_GATE_ONLY. No frozen Phase 3/4/5 contract is changed by this ADR.

Reference:

`PHASE6-PLANNING.md`; `PHASE5-7-EXECUTION-CLASSIFICATION.md`;
`PHASE6-7-ELIGIBILITY-MATRIX.md`; `PHASE5-7-DEPENDENCY-GRAPH.md`;
`PHASE5-7-RISK-REGISTER.md`; `DECISION_QUEUE.md`
(`DECISION-PHASE6-AUTHORIZATION-001`, `DECISION-PHASE6-OWNER-DECISIONS-001`,
`DECISION-P6-006-AUTHORIZATION-001`, `DECISION-P7-005-AUTHORIZATION-001`);
ADR-019; ADR-021; ADR-023.

## P6-001 Closure — Phase 6 Editing Domain / Contract Foundation

Date: 2026-09-23

Status: DECIDED — Human Product Owner (`DECISION-P6-001-CLOSURE-001`).

Decision:

The fresh independent corrective re-review
(`reviews/P6-001-corrective-independent-re-review.md`) returned **VERIFIED** with
no remaining BLOCKER/HIGH/MEDIUM. All prior findings were independently confirmed
resolved: the undo→branch version collision (BLOCKER), the undefined redo target
under branching (HIGH), the mixed-category staleness precedence gap (MEDIUM), and
the test-support PHPStan findings (LOW). P6-001 is transitioned `VERIFIED → DONE`,
with all historical review artifacts and corrective provenance preserved
unchanged.

The final P6-001 domain semantics are recorded as **frozen inputs** for
downstream Phase 6 work (see `PHASE6-EDITING-DOMAIN-CONTRACT.md` §0): immutable
machine source + append-only editable revisions + one active pointer; durable
revision graph; transcription-scoped monotonic `version` independent of ancestry
with transactional `(transcription_id, version)` uniqueness and
`parent_revision_id` ancestry; stale-base conflict with no silent merge and
separate active-pointer-CAS / version-uniqueness invariants surfacing as domain
conflict rather than raw DB errors; durable active-pointer undo/redo with
branch-after-undo, durable old descendants, prior-redo-path invalidation, and
`redoTargetFor()` valid only for a unique deterministic child; the timing
invariants; active-revision segment identity/position navigation; and translation
invalidation precedence
`SegmentStructureChanged > TimingChanged > SourceTextChanged`.

Phase consequence:

P6-001 closure does not close Phase 6. It unblocks P6-002, which the HPO promoted
to READY (`DECISION-P6-002-READY-001`). Each further Phase 6 task still requires
its own contract and an explicit HPO READY promotion. Phase 7 remains NOT
GENERALLY AUTHORIZED.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-001-CLOSURE-001`,
`DECISION-P6-002-READY-001`); `reviews/P6-001-independent-review.md`;
`reviews/pre-review/P6-001-corrective-pre-review.md`;
`reviews/P6-001-corrective-independent-re-review.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; `tasks/P6-001-phase6-editing-domain-contract.md`;
ADR-025.

## P6-002 READY — Revision Persistence / Version History

Date: 2026-09-23

Status: DECIDED — Human Product Owner (`DECISION-P6-002-READY-001`).

Decision:

The authored-and-reconciled P6-002 contract is promoted to **READY** and
authorized to implement within its canonical contract. Implementation must
consume the frozen P6-001 semantics without redefining them and must safely
persist `transcript_revisions`, `transcript_revision_segments`,
`transcriptions.active_revision_id`, transcription-scoped monotonic versions,
parent ancestry, the active pointer, immutable historical revisions, revision
segment identity/position, and durable history. `(transcription_id, version)`
uniqueness must be enforced transactionally; DB uniqueness/locking conflicts must
be translated into the canonical domain conflict. Split/merge UI and
translation-staleness persistence remain out of scope.

Phase consequence:

P6-002 proceeds to implementation and independent review; it is not VERIFIED or
DONE. P6-003/P6-004/P6-005/P6-007/P6-008/P6-009 and all non-authorized Phase 7
tasks remain not started.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-002-READY-001`); `tasks/P6-002-revision-persistence-version-history.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; ADR-025.

## P6-002 Closure — Revision Persistence / Version History

Date: 2026-09-23

Status: DECIDED — Human Product Owner (`DECISION-P6-002-CLOSURE-001`).

Decision:

The fresh independent corrective re-review returned **VERIFIED** with no remaining
BLOCKER/HIGH/MEDIUM/LOW/INFO findings. The original MEDIUM finding (undo did not
enforce strict ancestry) is resolved: `RevisionService::undo()` now activates only
a strict ancestor of the current active revision (walking `parent_revision_id`),
rejecting self, sibling, cousin, descendant, abandoned-branch, unrelated,
cross-transcription, and unknown targets, while active-pointer CAS still rejects
stale writes and rejected calls leave persistence unchanged. Redo semantics,
ownership/isolation, version allocation, transactional rollback, machine-source
immutability, schema constraints, and race handling remain intact, and Eloquent and
the in-memory reference repository remain behaviorally aligned. P6-002 is
transitioned `VERIFIED → DONE`, with all historical review and corrective
artifacts preserved unchanged.

The P6-001/P6-002 foundation is recorded as a **frozen downstream input** for the
remaining Phase 6 work: immutable machine source + append-only editable revisions +
one active pointer + durable graph/history; branching after undo; the revision
persistence schema with transcription-scoped monotonic version and unique
`(transcription_id, version)`; durable parent ancestry; active-pointer CAS;
transactional conflict translation; strict-ancestor-only undo; deterministic
unique-child redo; durable old branches; the timing invariants; active-revision
identity/position navigation with immutable machine timing; and translation
invalidation precedence `SegmentStructureChanged > TimingChanged >
SourceTextChanged`. Downstream tasks consume these without redefining them.

Phase consequence:

P6-002 closure does not close Phase 6. P6-003/P6-004/P6-005/P6-007/P6-008 remain
not started and each still requires a canonical contract and an explicit HPO READY
promotion; P6-009 remains FINAL_GATE_ONLY. No further Phase 7 work is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-002-CLOSURE-001`,
`DECISION-P6-002-READY-001`); `tasks/P6-002-revision-persistence-version-history.md`;
`reviews/P6-002-independent-review.md`;
`reviews/pre-review/P6-002-corrective-pre-review.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; `PHASE6-7-ELIGIBILITY-MATRIX.md` §M; ADR-025.

Provenance note: the reviewer-owned artifact
`reviews/P6-002-corrective-independent-re-review.md` was not present in the working
tree at reconciliation time; it is the expected durable evidence for the accepted
VERIFIED verdict and must be retained/added. This is a record-completeness note
only and does not reopen P6-002.

## P6-007 Scope — Presentation-only Source/Translation Comparison

Date: 2026-09-23

Status: DECIDED — Human Product Owner (`DECISION-P6-007-SCOPE-001`).

Decision:

P6-007 Source/Translation Comparison is **presentation-only** for its required
Phase 6 scope. P6-007 may display the immutable machine source, the active editable
revision, persisted Phase 5 translation content, and the source / revision /
translation comparison relationships. P6-007 must **not** own or persist
translation invalidation; translation-staleness display is optional. If staleness
state is not yet available because P6-005 has not implemented it, P6-007 must
degrade gracefully and must not invent or infer stale/current state. A later
P6-005 staleness marker may be consumed through an explicit contract without
P6-007 owning it.

This preserves P6-007 independence from P6-005. P6-007 must not mutate the machine
source or revision history, persist invalidation, silently remap translation
content, or own the translation lifecycle; it must not present a translation as
aligned with edited revision text when the persisted translation belongs to a
different source/revision identity.

Phase consequence:

P6-007 remains gated only by its canonical contract and an explicit HPO READY
promotion (P5 and P6-002 are DONE). P6-005 retains sole ownership of
translation-invalidation persistence. No implementation is authorized by this
decision.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-007-SCOPE-001`); `tasks/P6-007-source-translation-comparison.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; `PHASE6-7-ELIGIBILITY-MATRIX.md` §N; ADR-025;
`DECISION-PHASE6-OWNER-DECISIONS-001` (D6-04, D6-06).

### DECISION-P6-003-P6-007-READY-BATCH-001 � HPO promotes P6-003 and P6-007 to READY

Decision ID: DECISION-P6-003-P6-007-READY-BATCH-001

Status: DECIDED - HPO 2026-09-23

Type: Governance / Phase 6 task promotion

Originating Tasks: P6-003 (Text Editing + Undo/Redo); P6-007 (Source / Translation Comparison)

Question:

Are P6-003 and P6-007 promoted to READY and authorized for implementation?

Resolution:

HPO-DECIDED 2026-09-23 - **P6-003 and P6-007 are each promoted to READY and
authorized for implementation**, since each canonical contract exists and its
binding dependencies are satisfied:

- P6-003: `DECISION-P6-003-READY-001`; contract
  `tasks/P6-003-text-editing-undo-redo.md`; P6-001 DONE, P6-002 DONE, Phase 4
  primitives, P6-006 DONE, DC-01.
- P6-007: `DECISION-P6-007-READY-001`; contract
  `tasks/P6-007-source-translation-comparison.md`; Phase 5 CLOSED, P6-001/P6-002
  DONE, P6-006 DONE, `DECISION-P6-007-SCOPE-001` (presentation-only), DC-01.

Execution order: implement P6-003 first (tests, browser evidence, pre-review),
then implement P6-007 against the stabilized workspace. If parallel execution is
attempted instead, explicit file/component ownership of
`resources/views/transcriptions/show.blade.php` must be established first; the
two tasks must not independently modify the same workspace surface concurrently.

P6-003 implements only its canonical scope, preserving immutable machine source,
append-only revisions, expected-base concurrency, no silent merge, strict-ancestor
undo, unique-child redo, branch-after-undo semantics, P6-006 navigation/filter
behavior, and reserved Phase 4 hooks. Textual edits map to `EditKind::Textual` ->
`SourceTextChanged`; P6-003 does not persist translation staleness (P6-005 owns
that). After implementation P6-003 = `IMPLEMENTED_PENDING_REVIEW`; it must not be
self-verified.

P6-007 remains presentation-only. It may display machine source, active revision,
persisted Phase 5 translation, and comparison relationships, but must not mutate
machine source or revision history, persist staleness, remap translations
silently, or own the translation lifecycle. Persisted Phase 5 translations align
to machine `segment_index`, not revision segment identity, so edited-revision vs
translation comparisons must state that the translation belongs to machine source
unless an explicit persisted revision linkage exists, and structurally changed
revisions must never be mapped by index. Where no persisted staleness marker
exists, only factual state is shown. After implementation P6-007 =
`IMPLEMENTED_PENDING_REVIEW`; it must not be self-verified.

Record completeness (retained): `reviews/P6-002-corrective-independent-re-review.md`
is still missing; P6-002 is **not** reopened and no artifact may be fabricated or
reconstructed from summaries. The accepted reviewer should add the actual artifact
when available.

Blocks: None.

Does Not Block: P6-004/P6-005/P6-008/P6-009 (each still requires its own contract
and an explicit HPO READY promotion; P6-005 additionally waits on P6-004 DONE;
P6-009 is FINAL_GATE_ONLY); Phase 7 remains not generally authorized.
