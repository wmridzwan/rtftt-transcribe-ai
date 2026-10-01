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

## P6-003 Closure — Text Editing + Undo/Redo

Date: 2026-09-24

Status: DECIDED — Human Product Owner (`DECISION-P6-003-CLOSURE-001`).

Decision:

The fresh independent review
(`reviews/P6-003-P6-007-independent-review.md`) returned **VERIFIED** for P6-003
with no remaining BLOCKER/HIGH/MEDIUM finding. The reviewer independently
confirmed and reproduced: append-only text editing; expected-base concurrency;
stale conflict handling; strict-ancestor undo; unique-child redo;
branch-after-undo semantics; machine-source immutability; ownership/isolation;
reload durability; real-browser behavior; no translation-staleness persistence;
and no P6-004/P6-005/P6-008 scope leakage. P6-003 is transitioned
`VERIFIED → DONE`, with the historical review artifact preserved unchanged.

The P6-003-corrected P6-001/P6-002 foundation remains the frozen downstream input
for the remaining Phase 6 work, and P6-003 consumes it without redefinition:
immutable machine source + append-only revisions + one active pointer + durable
graph/history; text edits preserve identity/position/timing/language; textual
edits classify as `EditKind::Textual` → `SourceTextChanged` with no
translation-staleness persistence (P6-005 retains sole ownership).

Phase consequence:

P6-003 closure does not close Phase 6. P6-007 remains open:
`IMPLEMENTED_PENDING_REVIEW` after a corrective cycle for one MEDIUM
presentation-truthfulness finding (the per-row "edited after the translation was
produced" note rendered when no translation existed; the note is now gated on
persisted translation existence). P6-007 requires a fresh independent corrective
re-review before HPO closure. P6-004/P6-005/P6-008/P6-009 remain not started and
each still requires a canonical contract and an explicit HPO READY promotion;
P6-009 remains FINAL_GATE_ONLY. No further Phase 7 work is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-003-CLOSURE-001`,
`DECISION-P6-003-P6-007-READY-BATCH-001`, `DECISION-P6-007-SCOPE-001`);
`tasks/P6-003-text-editing-undo-redo.md`;
`tasks/P6-007-source-translation-comparison.md`;
`reviews/P6-003-P6-007-independent-review.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; ADR-025.

## P6-007 Closure - Source / Translation Comparison

Date: 2026-09-24

Status: DECIDED - Human Product Owner (`DECISION-P6-007-CLOSURE-001`).

Decision:

The fresh independent corrective re-review of P6-007 confirmed the original
MEDIUM presentation-truthfulness finding is closed and returned **VERIFIED** with
no remaining BLOCKER/HIGH/MEDIUM/LOW/INFO finding. The reviewer independently
confirmed that: an edited revision with no translation shows only the factual
no-translation state; no false "Edited after the translation was produced" claim
remains; an edited revision with a persisted translation preserves machine-source
provenance; structurally incompatible revisions are never silently
index-remapped; no staleness is inferred or persisted; P6-007 remains
presentation-only (no database writes); P6-003 shared-workspace behavior remains
green; and the real-browser (DC-01) evidence was independently reproduced.

P6-007 is transitioned `VERIFIED -> DONE`. The historical independent review
artifact (`reviews/P6-003-P6-007-independent-review.md`, which returned the
original CHANGES_REQUESTED MEDIUM finding) and the corrective provenance (task
file corrective cycle; `PHASE6-P6-003-CLOSURE-P6-007-CORRECTIVE-BATCH-REPORT.md`)
are preserved unchanged; no historical finding was rewritten.

Frozen downstream semantics: Phase 5 translations align to the machine
`segment_index`; P6-007 does not own the translation lifecycle and does not
persist or infer staleness; the edited revision <-> machine translation
provenance relationship must remain explicit; structurally incompatible
revisions must not be silently aligned or index-remapped.

Phase consequence:

P6-007 closure does not close Phase 6. P6-004/P6-005/P6-008/P6-009 remain not
implemented. This batch also authors the canonical P6-004 (Timing Editing +
Validation) contract, which awaits an explicit HPO READY promotion; P6-005
additionally waits on P6-004 DONE; P6-008 requires its own contract and READY
promotion; P6-009 remains FINAL_GATE_ONLY. No further Phase 7 work is authorized.

Record completeness (retained): `reviews/P6-002-corrective-independent-re-review.md`
is still missing; P6-002 is **not** reopened and no artifact was fabricated or
reconstructed from summaries.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-007-CLOSURE-001`,
`DECISION-P6-007-SCOPE-001`, `DECISION-P6-003-P6-007-READY-BATCH-001`);
`tasks/P6-007-source-translation-comparison.md`;
`tasks/P6-004-timing-editing-validation.md`;
`reviews/P6-003-P6-007-independent-review.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md`; `PHASE6-7-ELIGIBILITY-MATRIX.md` §Q; DC-01;
ADR-025.

## P6-004 Ready - Timing Editing + Validation

Date: 2026-09-24

Status: DECIDED - Human Product Owner (`DECISION-P6-004-READY-001`).

Decision:

P6-004 is promoted to **READY** and authorized for implementation. Its canonical
contract (`tasks/P6-004-timing-editing-validation.md`) exists and its dependencies
are satisfied: P6-001 DONE (frozen D6-03 timing invariants), P6-002 DONE (revision
service), P6-003 DONE (shared workspace surface now free), D6-03 adopted, and
Phase 4 primitives / P6-006 available. P6-004 does not depend on Phase 5 or
P6-005.

The implementation must consume, not redefine, the frozen Phase 6 timing
contract: finite, non-negative, `start <= end`, millisecond precision with no
silent rounding, overlaps/nested overlaps/equal starts/equal ends legal,
zero-length legal but never active, no cross-segment timestamp monotonicity,
ordering by revision `position` not time, and immutable machine-source timing.

It must implement timing-only, append-only revision editing with expected-base
concurrency and no in-place mutation; preserve text/language/identity/position;
classify as `EditKind::Timing` -> `TimingChanged` without persisting translation
staleness (P6-005 owns it); provide a narrow `TimingEditComposer` that rejects
NaN/infinite/negative/`start > end`/out-of-contract precision and accepts overlap,
nested overlap, equal starts/ends, zero-length, out-of-time-order positions, and
extending past a neighbor; keep playback/active-segment resolution and P6-006
navigation on active-revision timing (machine timing only when no revision is
active, half-open semantics and lowest-position tie resolution preserved); and
deliver the workspace UX with dedicated `data-timing-*` hooks without repurposing
reserved Phase 4 hooks or interfering with P6-003/P6-007.

Real-browser DC-01 verification is mandatory, with P6-003/P6-006/P6-007 and
Phase 4 playback regressions re-run. On completion P6-004 becomes
`IMPLEMENTED_PENDING_REVIEW`; it must not be self-marked VERIFIED or DONE.

Boundary:

P6-004 must not implement split/merge, structural segment-identity change,
persisted translation staleness, or translation remapping (all P6-005). P6-005
remains dependency-blocked until P6-004 is DONE. P6-008/P6-009 and all further
Phase 7 work remain not started. Missing independent-review artifacts for P6-002
and P6-007 must not be fabricated; their record-completeness notes are retained.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-004-READY-001`);
`tasks/P6-004-timing-editing-validation.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §5/§6; `PHASE6-7-ELIGIBILITY-MATRIX.md` §Q;
DC-01; ADR-025.

## P6-004 Closure - Timing Editing + Validation

Date: 2026-09-24

Status: DECIDED - Human Product Owner (`DECISION-P6-004-CLOSURE-001`).

Decision:

The fresh independent review of P6-004 returned **VERIFIED** with no remaining
BLOCKER/HIGH/MEDIUM finding. The reviewer confirmed the frozen Phase 6 timing
semantics are implemented exactly; invalid first (machine-source) edits are
write-free; active-revision timing is the playback/active-resolution source of
truth; machine-source timing is immutable; overlap/nested/equal/zero-length/
out-of-time-order timing remain legal; stale-base compare-and-set behavior is
correct; no P6-003/P6-006/P6-007 regression was found; and no split/merge or
translation-staleness persistence was introduced.

P6-004 is transitioned `VERIFIED -> DONE`. The independent review and the
historical implementation/pre-review artifacts are preserved unchanged.

Non-blocking INFO debt is carried forward without reopening P6-004
(`DECISION-P6-004-INFO-CARRYFORWARD-001`): the pre-existing P6-007/P4-006 V4-13
locator-scoping issue (hidden comparison content); the shared Laravel validation
error bag that could theoretically surface unrelated form errors in the timing
toolbar (no observed failure); and the Phase 4 headless playback-start V4-08/V4-09
environmental flake.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-004-CLOSURE-001`,
`DECISION-P6-004-INFO-CARRYFORWARD-001`);
`reviews/pre-review/P6-004-pre-review.md`;
`PHASE6-P6-004-IMPLEMENTATION-BATCH-REPORT.md`;
`verification/p6-004/P6-004-BROWSER-VERIFICATION-EVIDENCE.md`.

## P6-005 Eligibility and Contract Authorization - Split / Merge + Translation Invalidation

Date: 2026-09-24

Status: DECIDED - Human Product Owner (`DECISION-P6-005-ELIGIBILITY-001`).

Decision:

With P6-004 = DONE, P6-005 is reclassified from `DEPENDENCY_BLOCKED` to
**`CONTRACT_REQUIRED / READY-ELIGIBLE AFTER CONTRACT`**, and its canonical
contract is authorized for authoring. P6-005 is **not** implemented in this batch.

P6-005 owns two high-risk areas: structural segment editing (split/merge) and
persisted translation invalidation. Its contract consumes, without redefining, the
frozen P6-001 through P6-004 semantics (revision model, identity/position, D6-03
timing invariants, expected-base concurrency, and the frozen
translation-invalidation taxonomy and precedence
`SegmentStructureChanged > TimingChanged > SourceTextChanged`).

P6-005 must not rewrite Phase 5 translation segments, silently remap translations
to new revision segment identities, or pretend structurally modified revisions
remain aligned to machine translation. Structural changes invalidate the
appropriate translation state. P6-005 does not silently alter P6-007 presentation
ownership; if persisted staleness becomes available, P6-007 may later consume it
through an explicit interface.

Before implementation, the following owner decisions must be resolved (recorded
OPEN in `DECISION_QUEUE.md`):

- `DECISION-P6-005-SPLIT-BOUNDARY-001` (split boundary / degenerate splits);
- `DECISION-P6-005-MERGE-JOIN-001` (merge text join and resulting timing);
- `DECISION-P6-005-LANGUAGE-PROVENANCE-001` (mixed-language provenance flag
  representation);
- `DECISION-P6-005-STALENESS-LIFECYCLE-001` (persisted staleness lifecycle);
- `DECISION-P6-005-SCHEMA-001` (exact schema additions).

Implementation additionally requires an explicit HPO READY promotion. P6-008
remains separately governed; P6-009 remains FINAL_GATE_ONLY.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-005-ELIGIBILITY-001` and the five OPEN
decisions above); `tasks/P6-005-split-merge-translation-invalidation.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §7/§8/§10;
`PHASE6-7-ELIGIBILITY-MATRIX.md` §S; ADR-025; ADR-022.

## P6-005 Owner Decisions - Split / Merge + Translation Invalidation

Date: 2026-09-24

Status: DECIDED - Human Product Owner (five decisions:
`DECISION-P6-005-SPLIT-BOUNDARY-001`, `DECISION-P6-005-MERGE-JOIN-001`,
`DECISION-P6-005-LANGUAGE-PROVENANCE-001`,
`DECISION-P6-005-STALENESS-LIFECYCLE-001`, `DECISION-P6-005-SCHEMA-001`).

Decision:

**Split boundary.** Split is allowed only at a strict interior boundary. For the
segment being split, split at the beginning is rejected and split at the end is
rejected; the operation must produce two meaningful child segments; degenerate
structural splits are not allowed. The split instant must satisfy the strict
interior rule (`start < t < end`) and the text offset must satisfy
`0 < k < length(text)`. Zero-duration/empty structural children must not be
created merely to permit a boundary split. This does not change the general P6
timing rule that zero-length segments may exist through other valid editing
operations.

**Merge join.** Merge is adjacent-only and uses one canonical plain-space
separator between contributor texts. Contributors are ordered by revision
position; the resulting text is
`segment1_text + " " + segment2_text [+ ...]`. Contributor text is not trimmed or
rewritten; no punctuation-aware rewriting; no sentence-structure inference; merge
is deterministic. Resulting timing is the start of the earliest-position
contributor and the end of the latest-position contributor.

**Language provenance.** Split: both child segments inherit the source segment's
language marker. Merge: if every contributor has the same language, the resulting
segment retains that language; if contributors contain different language markers,
the resulting segment language becomes `und` and the original contributor-language
provenance is retained explicitly. The first/earliest language is never silently
selected as the merged canonical language, and no automatic language redetection
occurs during split/merge. The implementation uses an explicit persistence
representation for mixed-language provenance (a typed/JSON ordered list of
contributor language markers) rather than relying on `und` alone.

**Staleness lifecycle.** Staleness is persisted per translation row / translation
target identity; existing Phase 5 translations remain historical outputs and are
never rewritten or remapped. First invalidation sets `stale_at`,
`staleness_reason`, and the causing revision. Repeated invalidation preserves the
original `stale_at`, selects the canonical reason by the frozen precedence
`SegmentStructureChanged > TimingChanged > SourceTextChanged`, upgrades the reason
only when the new reason outranks the stored reason, and retains/updates
causing-revision provenance consistently with the stored canonical reason; a
stronger reason is never downgraded. A stale translation remains viewable as
historical output and must not be represented as current. Retranslation does not
clear staleness on the historical row; a later successful retranslation
creates/uses the canonical new translation identity, and the old translation
remains stale historical evidence (its translated segment content is not deleted
or mutated to appear current). A failed retranslation must not make the previous
stale translation current again.

**Schema.** Minimum additive schema authorized: add to `translations` the nullable
`stale_at` timestamp, nullable `staleness_reason`, and nullable
`stale_caused_by_revision_id` (reference to the revision responsible for the
stored canonical reason). Existing Phase 5 translation rows and translation
segments are preserved; Phase 5 translation identity is not rewritten. Add an
explicit nullable representation on revision segments for mixed-language
provenance that is deterministic, retains contributor language markers in
contributor order, and is independent of machine `segment_index` (typed/JSON,
validated at the domain boundary). No generic metadata dumping field is added.

Boundary: these decisions resolve the five OPEN P6-005 decisions only. They do not
redefine P6-001 through P6-004 semantics, do not authorize P6-008/P6-009, and do
not authorize new Phase 7 work.

Reference:

`DECISION_QUEUE.md` (the five DECIDED decisions above);
`tasks/P6-005-split-merge-translation-invalidation.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §7/§8/§10; ADR-025; ADR-022.

## P6-005 READY Promotion - Split / Merge + Translation Invalidation

Date: 2026-09-24

Status: DECIDED - Human Product Owner (`DECISION-P6-005-READY-001`).

Decision:

With the five owner decisions recorded and the canonical contract reconciled,
P6-005 is transitioned `CONTRACT_AUTHORED / READY-ELIGIBLE AFTER CONTRACT` →
**READY** and authorized for implementation.

Authorized scope: structural split; structural merge; required revision-segment
language provenance; additive translation-staleness schema; atomic invalidation
(structural revision append and invalidation committed in one transaction using
the existing P6-002 CAS/version rules, not a second concurrency model); required
UI; feature/domain/concurrency tests; and real-browser DC-01 verification.

Not authorized: P6-008 (revision-history UI), P6-009 (Phase 6 final gate),
rewriting Phase 5 translations or translation segments, and any new Phase 7 work.

Review model: per-task independent review (Claude Code). On completion P6-005
becomes `IMPLEMENTED_PENDING_REVIEW`; the implementer must not self-mark VERIFIED
or DONE.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-005-READY-001` and the five owner decisions);
`tasks/P6-005-split-merge-translation-invalidation.md`;
`PHASE6-7-ELIGIBILITY-MATRIX.md` §S/§T; ADR-025; ADR-022.

## P6-008 READY Promotion - Revision History / Audit Surface

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-P6-008-READY-001`).

Decision:

With HPO-008-A/B/C/D recorded DECIDED and the canonical contract reconciled,
P6-008 is transitioned `DRAFT` → **READY** and authorized for implementation.
Implementation not started.

Decided scope: arbitrary eligible same-transcription historical revision
selection via the existing CAS-fenced `activate()` primitive (no artificial
recent-only/bounded-history restriction); lightweight D6-05 surface only
(history list in version order, active marker, persisted metadata,
machine-source state where supported); OPTIONAL-1 retro-wire excluded
(P6-003/P6-004 boundary stands); PROCEED (D6-02 adopted persisted history,
so the cancellation condition is not satisfied).

Not authorized: P6-009 execution (P6-008 DONE is an input to it, not the
gate itself); Phase 5 translation or translation-segment writes;
redefining P6-001..P6-007 semantics; D6-08/D6-09; any new Phase 7 work.

Review model: per-task independent review (Claude Code). On completion
P6-008 moves to REVIEW; the implementer must not self-mark VERIFIED or
DONE.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-008-READY-001`);
`tasks/P6-008-revision-history-audit-surface.md`;
`PHASE6-7-ELIGIBILITY-MATRIX.md` §T; ADR-025 (D6-02/D6-05); frozen domain
contract `PHASE6-EDITING-DOMAIN-CONTRACT.md` §§2-3.

## P6-008 Closure - Revision History / Audit Surface

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-P6-008-CLOSURE-001`).

Decision:

The fresh independent review (`reviews/P6-008-INDEPENDENT-REVIEW.md`)
returned **VERIFIED** with all AC1–AC10 PASS and no BLOCKER/MAJOR/MINOR
finding. The HPO concurs and transitions P6-008 `VERIFIED → DONE`.

OPTIONAL-1 (per-render users/translations lookups; negligible; future
optimization candidate) is non-blocking and preserved in the review
artifact. Builder report (`reviews/P6-008-BUILDER-REPORT.md`) and
`verification/p6-008/` evidence are preserved unchanged.

Phase consequence: P6-008 closure does not close Phase 6. P6-001..P6-008 are
now DONE; the P6-003..P6-008 DONE prerequisite for P6-009 is satisfied, but
P6-009 remains FINAL_GATE_ONLY with no canonical contract and requires
separate HPO authorization to open. No Phase 7 work is authorized beyond
DONE P7-005.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-008-CLOSURE-001`);
`tasks/P6-008-revision-history-audit-surface.md`;
`reviews/P6-008-INDEPENDENT-REVIEW.md`;
`reviews/P6-008-BUILDER-REPORT.md`.

## P6-009 READY Promotion - Phase 6 Final Integration Verification Gate

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-P6-009-READY-001`).

Decision:

The HPO reviewed the canonical P6-009 DRAFT contract
(`tasks/P6-009-phase6-integration-verification.md`) and accepts it as the
canonical Phase 6 FINAL_GATE_ONLY integration verification contract, subject
to resolving HPO-009-A/B/C:

- **HPO-009-A (contract acceptance)** — APPROVED. The contract is accepted as
  written; P6-009 is promoted `DRAFT` → **READY**. This authorizes the gate to
  be opened; it does not itself execute the gate.
- **HPO-009-B (D6-08/D6-09)** — CONFIRMED REMAIN DEFERRED AND NON-BLOCKING.
  D6-08 (speaker/annotations/bookmarks) and D6-09 (waveform/timeline) remain
  deferred under `DECISION-PHASE6-OWNER-DECISIONS-001`/ADR-025 and are not
  required for Phase 6 closure. P6-009 must verify (AC10) that neither was
  accidentally introduced; this decision does not reactivate either.
- **HPO-009-C (HPO-008-C exclusion)** — CONFIRMED. The P6-003/P6-004
  translation-staleness non-persistence boundary is preserved; P6-009 must not
  retro-wire that behavior and verifies the existing approved Phase 6 state
  only, with no remediation or semantic expansion.

This decision authorizes contract acceptance and lifecycle promotion to READY
only. It does not authorize gate execution, does not close Phase 6, and does
not authorize any Phase 7 work.

Phase consequence:

P6-001..P6-008 remain DONE. P6-009 is now READY (open, not started); a
separate implementation-owner assignment and gate execution (IN_PROGRESS →
REVIEW → VERIFIED → HPO closure) remain required before Phase 6 can be
recommended for closure. Phase 6 remains OPEN. No Phase 7 work is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-009-READY-001`);
`tasks/P6-009-phase6-integration-verification.md`;
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §11; ADR-025;
`.ai/guidelines/orchestration-policy.md`.

## HPO-F001-A - Remediate gate finding F-001 via bounded task P6-010

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-HPO-F001-A`).

Decision:

P6-009 executed with verdict FAIL on AC6 (finding F-001, MAJOR): the
supported transcript exports (TXT/SRT/VTT/DOCX) succeed but render
machine-source segments instead of the authoritative active revision,
contradicting the frozen P6-001 §9 export clause. The HPO accepts F-001 as a
genuine Phase 6 integration gap and selects REMEDIATE: F-001 is owned by a
dedicated bounded Phase 6 remediation task, P6-010 - Revision-Aware Export
Remediation (`tasks/P6-010-revision-aware-export-remediation.md`, DRAFT),
limited to deriving export content from the canonical active revision with
machine-source fallback only when no valid active revision exists.

P6-009 AC6 is not narrowed or superseded. P6-009 remains IN_PROGRESS with
execution verdict FAIL until P6-010 is DONE and the gate is rerun. No
fallback/semantics ambiguity is open (frozen P6-001 §9 decides it).

This decision authorizes remediation contract authoring only. It does not
promote P6-010 to READY, does not implement the remediation, does not pass
P6-009, does not close Phase 6, and does not authorize Phase 7 work.

Phase consequence:

P6-001..P6-008 remain DONE. P6-009 remains IN_PROGRESS (verdict FAIL).
P6-010 exists as DRAFT (not READY, not started). Phase 6 remains OPEN.
No phase closes automatically. F-001 blocks the Phase 6 closure
recommendation until P6-010 DONE + gate rerun.

Reference:

`DECISION_QUEUE.md` (`DECISION-HPO-F001-A`);
`tasks/P6-010-revision-aware-export-remediation.md`;
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md` (§4, §13);
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §9;
`.ai/guidelines/orchestration-policy.md`.

## P6-010 READY Promotion - Revision-Aware Export Remediation (F-001)

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-P6-010-READY-001`).

Decision:

The HPO reviewed the canonical P6-010 DRAFT contract
(`tasks/P6-010-revision-aware-export-remediation.md`) and approves it as the
canonical bounded remediation for F-001 (HPO-P6-010-A):

- **HPO-P6-010-A (contract approval)** — APPROVED. Export uses the current
  active revision when a valid active revision exists; machine-source export
  is fallback only when no valid active revision exists; machine-source
  data remains unchanged and recoverable; formats remain TXT, SRT, VTT,
  DOCX; no new formats; no translation-export redesign; no Phase 7 scope;
  no P6-003/P6-004 retro-wire; no weakening of P6-009 AC6. Transition
  applied: P6-010 `DRAFT` → **READY**.
- HPO-F001-A remains DECIDED (REMEDIATE). No unresolved HPO scope/fallback
  decisions remain; fallback semantics are accepted as
  frozen-contract-derived.

This decision authorizes contract approval and lifecycle promotion to READY
only. It does not authorize implementation, does not rerun or alter P6-009,
does not close Phase 6, and does not authorize any Phase 7 work.

Phase consequence:

P6-001..P6-008 remain DONE. P6-009 remains IN_PROGRESS (verdict FAIL).
P6-010 is now READY (open, not started); a separate Builder-execution
authorization remains required before implementation may start. Phase 6
remains OPEN. No Phase 7 work is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-010-READY-001`);
`tasks/P6-010-revision-aware-export-remediation.md`;
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md` (§4, §13);
`PHASE6-EDITING-DOMAIN-CONTRACT.md` §9;
`.ai/guidelines/orchestration-policy.md`.

## P6-010 Closure - Revision-Aware Export Remediation (F-001)

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-P6-010-CLOSURE-001`).

Decision:

The HPO reviewed the independent review result for P6-010
(`reviews/P6-010-INDEPENDENT-REVIEW.md`) and concurs with the VERIFIED
verdict: AC1–AC11 PASS with 0 BLOCKER/MAJOR/MINOR/OPTIONAL findings, and all
fresh verification (targeted 8/8, export suites 37/37, editing/revision
suite 126 passed / 1 documented skip, full suite 900 / 898 passed /
2 skipped / 0 failures, Pint clean, PHPStan 0 errors, machine integrity,
authorization/gating, Unicode, diff scope audit) independently reproduced.

Canonical transition applied: VERIFIED → (HPO closure decision) → DONE.
History preserved (DRAFT → READY → IN_PROGRESS → IMPLEMENTED_PENDING_REVIEW
→ VERIFIED → DONE), not rewritten.

F-001 distinction: the bounded remediation implementation is RESOLVED. The
historical P6-009 gate result remains FAIL pending a full rerun — no P6-009
evidence was rewritten and AC6 was not retroactively modified.

This closure authorizes no further implementation, does not rerun P6-009,
does not close Phase 6, and does not authorize Phase 7 work.

Phase consequence:

P6-001..P6-008 remain DONE. P6-010 = DONE. P6-009 remains IN_PROGRESS
(verdict FAIL) and is now eligible for a full rerun under explicit
authorization. Phase 6 remains OPEN. No Phase 7 work is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-010-CLOSURE-001`);
`tasks/P6-010-revision-aware-export-remediation.md`;
`reviews/P6-010-BUILDER-REPORT.md`;
`reviews/P6-010-INDEPENDENT-REVIEW.md`;
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md`;
`.ai/guidelines/orchestration-policy.md`.

## P6-009-RERUN-01 Execution Authorization (late-persisted reconciliation)

Date: 2026-09-25 (authorization given before rerun execution; persisted late
the same day by governance reconciliation).

Status: DECIDED - Human Product Owner
(`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`).

Decision:

The HPO authorized a full rerun of P6-009 (`P6-009-RERUN-01`) by direct HPO
instruction issued before rerun execution on 2026-09-25, after P6-010 reached
DONE. The instruction named the task, the canonical contract
(`tasks/P6-009-phase6-integration-verification.md`), the authority context,
and the complete-rerun scope (AC1–AC11 fresh; historical FAIL preserved; no
self-verify/close; no Phase 6 closure; no Phase 7). Under repository
governance a direct explicit HPO instruction constitutes authorization (cf.
`.ai/guidelines/orchestration-policy.md`, Phase Gates: authorization "in the
roadmap or an explicit user instruction"); persistence in the decision
records is the durable-records norm (cf.
`.ai/guidelines/ai-development-os.md`: important information "must not exist
only in chat output"), and that persistence step was omitted at the time.

This record reconciles persistence only. It does not retroactively invent
authority, does not backdate any decision, and does not alter any technical
evidence. The authorizing act predates execution; only its recording is late.

Scope (as authorized):

- Full final-gate rerun only: AC1–AC11 with fresh integrated evidence under
  rerun identity `P6-009-RERUN-01`, historical failed-gate evidence preserved
  untouched.
- No feature implementation, no silent remediation, no AC weakening.
- No P6-009 closure, no Phase 6 closure, no Phase 7 work.

Correction note: the rerun task record cited `DECISION-P6-009-READY-001` +
`DECISION-P6-010-CLOSURE-001` as its authority. Read verbatim, neither grants
rerun-execution authority (the former authorized the original execution; the
latter explicitly disclaims rerunning P6-009 and frames the rerun as
"eligible for explicit authorization"). Those two decisions are context and
eligibility only. The actual authorizing act is the direct HPO rerun
instruction recorded here.

Phase consequence:

P6-001..P6-008 remain DONE. P6-010 = DONE. P6-009 rerun executed under this
authorization with verdict PASS (fresh AC1–AC11;
`verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`);
independently reviewed (`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md`).
Phase 6 remains OPEN. No Phase 7 work is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`);
`tasks/P6-009-phase6-integration-verification.md`;
`verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`;
`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md`;
`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md`;
`.ai/guidelines/orchestration-policy.md`.

## P6-009 Closure - Phase 6 Final Integration Verification Gate (RERUN-01)

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-P6-009-CLOSURE-001`).

Decision:

The HPO reviewed the P6-009 final-gate rerun result, the independent
review, the governance reconciliation, and the late-persisted rerun
authorization record, and concurs with the canonical state:

- Rerun `P6-009-RERUN-01`: verdict PASS, AC1–AC11 with fresh integrated
  evidence
  (`verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`);
- Independent review (`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md`):
  VERIFIED, technical evidence independently reproduced with zero
  discrepancies, no BLOCKER/HIGH findings;
- Governance reconciliation
  (`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md`): A2
  (authorized in conversation, late-persisted) / B1 (REVIEW → VERIFIED
  legal);
- Rerun authorization (`DECISION-P6-009-RERUN-01-AUTHORIZATION-001`):
  persisted, scope-limited to the rerun, no Phase 6/7 authority included.

The HPO explicitly accepts the remaining procedural finding as
RECONCILED / ACCEPTED FOR CLOSURE on the reconciled interpretation
(authorization predated execution; persistence omitted then truthfully
reconciled; no fabricated or backdated authority; technical verification
valid). The finding remains in the permanent audit trail; it is not
deleted, downgraded, or rewritten, and it does not block DONE closure.

Canonical transition applied: VERIFIED → (HPO closure decision) → DONE.
History preserved (original FAIL → HPO-F001-A → P6-010 DONE → direct HPO
rerun authorization → rerun PASS → independent VERIFIED → governance
reconciliation → DONE), not flattened. The historical first-execution FAIL
remains valid history.

Phase consequence:

P6-001..P6-008 remain DONE. P6-010 = DONE. P6-009 = DONE (this decision).
Phase 6 terminal gate completed. Phase 6 remains OPEN pending a separate
HPO Phase 6 closure decision; this closure does not itself close Phase 6.
No Phase 7 work is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-P6-009-CLOSURE-001`);
`tasks/P6-009-phase6-integration-verification.md`;
`verification/p6-009/P6-009-FINAL-GATE-EVIDENCE.md`;
`verification/p6-009-rerun-01/P6-009-FINAL-GATE-RERUN-EVIDENCE.md`;
`reviews/P6-009-RERUN-01-INDEPENDENT-REVIEW.md`;
`reviews/P6-009-RERUN-01-GOVERNANCE-RECONCILIATION.md`;
`.ai/guidelines/orchestration-policy.md`.

## Phase 6 Closure - Advanced Transcript UX

Date: 2026-09-25

Status: DECIDED - Human Product Owner (`DECISION-PHASE6-CLOSURE-001`).

Decision:

The HPO reviewed the formal Phase 6 closure review and concurs that every
mandatory closure criterion passes:

- Required Phase 6 tasks terminal: P6-001..P6-010 DONE, each by HPO
  closure on an independent VERIFIED verdict
  (`DECISION-P6-001-CLOSURE-001`, `DECISION-P6-002-CLOSURE-001`,
  `DECISION-P6-003-CLOSURE-001`, `DECISION-P6-004-CLOSURE-001`,
  `DECISION-P6-005-CLOSURE-001`, `DECISION-P6-006-CLOSURE-001`,
  `DECISION-P6-007-CLOSURE-001`, `DECISION-P6-008-CLOSURE-001`,
  `DECISION-P6-009-CLOSURE-001`, `DECISION-P6-010-CLOSURE-001`).
- Terminal gate P6-009 DONE on the independently VERIFIED rerun
  (`P6-009-RERUN-01`, AC1–AC11 fresh PASS; F-001 resolved via P6-010 DONE).
- No unresolved BLOCKER/HIGH finding; F-001 resolved; procedural rerun
  finding reconciled and HPO-accepted for closure.
- D6-08/D6-09 properly DEFERRED and non-blocking (standing authority
  `DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025; HPO-009-B; gate AC10
  PASS in both executions).
- Audit history preserved end to end (original FAIL, F-001, P6-010,
  rerun, reviews, reconciliation, closures — none rewritten).
- Phase 7 not improperly started (only early-authorized P7-005 DONE).

**Phase 6 = CLOSED.**

Completed range P6-001..P6-010 as above. Deferred scope D6-08/D6-09
unchanged (not closed, not implemented, not reactivated). Carry-forward
items (non-blocking): Phase 5 LOW/INFO; P6-004 INFO; P6-005
MINOR-1/OPTIONAL-1; P6-008 OPTIONAL-1; P6-002 record-completeness gap
(retained); reconciled P6-009 procedural finding (audit trail); TD-001..TD-013
(Phase 7-owned; none a Phase 6 closure prerequisite).

HPO authority: this formal closure instruction. Resulting phase state:
CLOSED. This decision authorizes no Phase 7 implementation and resolves no
D7-* decision; Phase 7 entry requires separate HPO entry review and
authorization.

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE6-CLOSURE-001`);
`reviews/PHASE6-CLOSURE-REVIEW.md`;
`tasks/P6-009-phase6-integration-verification.md`;
`PHASE5-7-DEPENDENCY-GRAPH.md` (closure rule);
`docs/TECHNICAL_DEBT_REGISTER.md`.

## ADR-026 — Phase 7 Production Topology Owner Decisions (D7-01..D7-08)

Date: 2026-09-26

Status: ACCEPTED — Human Product Owner (`DECISION-PHASE7-OWNER-DECISIONS-001`).

Decision:

The HPO resolves the eight Phase 7 owner-policy questions as follows.
These are owner-policy resolutions only; they do not by themselves
authorize Phase 7 implementation.

- D7-01 — Production data store: **OPTION B — self-hosted PostgreSQL.**
  PostgreSQL is the production persistence target. P7-002 must define and
  verify the SQLite/development → PostgreSQL production migration path.
  Existing Phase 6 revision/history/editing/export invariants must survive
  the driver transition. PostgreSQL-specific backup tooling must be
  reconciled with D7-07. Dual-driver or otherwise appropriate compatibility
  verification must be defined by the eventual task contract.
  Status: `D7-01 = RESOLVED — OPTION B`.
- D7-02 — Queue / worker supervision model: **OPTION A — Redis queue +
  OS-level worker supervision (systemd/supervisord), without Laravel
  Horizon.** Redis is the production queue backbone; worker
  lifecycle/restart stays with the OS supervisor; Horizon is not part of
  the initial production architecture. This decision drives disposition of
  TD-003; TD-004 is resolved per the final Redis deployment/security
  topology. Status: `D7-02 = RESOLVED — OPTION A`.
- D7-03 — Production storage strategy: **OPTION A — local private
  storage** for the initial production deployment. Media and derived
  artifacts remain on private application storage. Capacity, filesystem
  durability, backup interaction, permissions, and deployment topology must
  be explicitly validated. Existing streaming/range-request/revision/export
  behavior remains canonical. TD-011 remains a Phase 7 verification
  concern. Object storage is **deferred, not rejected**: it may be reopened
  when deployment scale, multi-instance requirements, or
  durability/capacity evidence justifies it; object-storage migration is
  not authorized by this decision.
  Status: `D7-03 = RESOLVED — OPTION A`.
- D7-04 — Malware scanning: **OPTION A — self-hosted ClamAV** for
  production uploads. Uploaded user media must pass the approved
  malware-scanning contract before becoming normally usable. Failure,
  timeout, scanner-unavailable, infected-file, quarantine/rejection, and
  recovery behavior must be explicitly defined. Signature-update and
  service-health requirements belong in the production operations contract.
  User media must not be sent to a third-party cloud scanning provider
  under this decision. Status: `D7-04 = RESOLVED — OPTION A`.
- D7-05 — Production browser support matrix: **OPTION A — Chromium-based
  browsers only** for the initial release. Firefox and Safari/WebKit are
  best-effort / unsupported unless later promoted by a new HPO decision.
  P7-010 must align its browser verification contract to this matrix.
  TD-005 is dispositioned against this narrowed support boundary. Existing
  non-Chromium evidence must not be misrepresented as a current production
  support guarantee. Status: `D7-05 = RESOLVED — OPTION A`.
- D7-06 — Retention / deletion policy: **MODIFIED OPTION A — time-based
  automatic purge with a 30-day retention period.** Source media and
  purge-eligible derived artifacts are retained for 30 days after
  successful processing/completion, then automatically deleted per the
  approved lifecycle contract. The implementation contract must define:
  what starts the 30-day clock; which source and derived artifacts are
  purge eligible; what records remain for audit/history; treatment of
  failed/incomplete processing; retry behavior; user-triggered deletion
  interaction; P6 revision/history invariants; export behavior before and
  after purge; deletion idempotency and partial-failure recovery; and
  user-facing disclosure of the retention policy. The purge mechanism must
  not orphan canonical Phase 6 revision/history records. This resolves the
  owner-policy portion of TD-007; implementation remains subject to its
  future authorized task. Status:
  `D7-06 = RESOLVED — MODIFIED OPTION A — 30-DAY RETENTION`.
- D7-07 — Backup / restore objectives: **OPTION A — daily backups,
  documented backup and restore procedures, at least one successfully
  executed restore drill before production-readiness closure, no strict
  numeric production RPO/RTO SLA for the initial release.** The final
  backup mechanism must match the D7-01 datastore. A backup job existing
  without demonstrated restore evidence is insufficient for the production
  gate. Status: `D7-07 = RESOLVED — OPTION A`.
- D7-08 — Concurrency / performance target: **OPTION A — single-admin,
  low-concurrency operation.** Phase 7 is not required to prove
  multi-tenant or large-team horizontal scale. P7-009 must nevertheless
  define and execute a concrete measurable capacity envelope appropriate to
  this operating model (covering the primary production workflow and the
  tested worker/job/storage/database limits), not an informal "low
  concurrency" statement. Any later transition to small-team or
  multi-tenant concurrency requires a fresh capacity/scaling decision.
  Status: `D7-08 = RESOLVED — OPTION A`.

Deferred alternatives: D7-01 Options A/C; D7-02 Options B/C (Horizon
excluded from initial architecture; may be reopened on
observability/scale evidence); D7-03 Options B/C (object storage deferred,
not rejected); D7-04 Options B/C (third-party cloud scanning excluded —
user media must not be sent off-site under this decision); D7-05 Options
B/C (Firefox/Safari promotion requires a new HPO decision); D7-06 Options
B/C; D7-07 Options B/C; D7-08 Options B/C (scale-up requires a fresh
decision).

Task dependencies: P7-002 (gated on D7-01); P7-003 (full scope gated on
D7-02); P7-004 (gated on D7-03; TD-011 verification concern preserved);
P7-006 (gated on D7-04); P7-010 (gated on D7-05); P7-011 (gated on D7-06
and P7-004, must not start before P7-004 VERIFIED); P7-007 (mechanism
reconciled with D7-01); P7-009 (measurable envelope per D7-08); P7-012
terminal gate consumes all of the above.

TD implications: TD-003 → D7-02 (policy direction set; implementation open
in P7-003); TD-004 → D7-02 / final Redis security topology (posture
decision still to be certified in P7-001/P7-003); TD-005 → D7-05 (narrowed
boundary; elimination work open in P7-010); TD-007 → D7-06 (owner-policy
portion resolved; implementation open in P7-011). No TD item is marked
implemented or VERIFIED by this ADR; the register preserves
implementation/verification state.

Implementation constraints: P6 revision/history invariants intact through
any migration; revision-aware export behavior canonical; no non-Chromium
support claims; no third-party upload-data egress for scanning; purge must
not orphan revision records; backup without restore evidence fails the
gate; capacity envelope must be measurable.

Reopening conditions: D7-03 (object storage) on scale/multi-instance/
durability evidence; D7-05 (Firefox/Safari) by new HPO decision;
D7-08 (higher concurrency) by fresh capacity/scaling decision; TD-009/
TD-012 remain resolved and need a fresh HPO decision to reopen.

Phase consequence:

D7-01..D7-08 are RESOLVED. This ADR grants no implementation
authorization. Phase 7 remains NOT ELIGIBLE / NOT AUTHORIZED until the
remaining entry-gate requirements are satisfied (TD reconciliation, scope
contract adoption, Wave 1 contracts READY-promoted, no entry-blocking
BLOCKER/HIGH, separate explicit HPO execution authorization).

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE7-OWNER-DECISIONS-001`);
`reviews/PHASE7-ENTRY-REVIEW.md` (§C/§K option tables);
`PHASE5-7-DECISION-REGISTER.md` (D7-01..D7-08);
`docs/TECHNICAL_DEBT_REGISTER.md` (TD-003/004/005/007/011);
`docs/PRODUCTION_READINESS_GATE.md`; `PHASE7-PLANNING.md`;
`PHASE6-7-ELIGIBILITY-MATRIX.md`.

## Phase 7 Scope Contract Adoption

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-SCOPE-ADOPTION-001`).

Decision:

The HPO adopts the reconciled Phase 7 scope contract as
`PHASE7-SCOPE-CONTRACT.md` (Status: ADOPTED — NOT AUTHORIZED FOR
IMPLEMENTATION). The contract takes the Entry Review §G proposal as its
baseline and applies the binding D7-01..D7-08 resolutions
(`DECISION-PHASE7-OWNER-DECISIONS-001`; ADR-026) without expanding scope:
PostgreSQL production target, Redis + OS-supervisor queue model without
Horizon, local private storage (object storage deferred, not rejected),
self-hosted ClamAV, Chromium-only matrix, 30-day retention auto-purge,
daily backups with an executed restore drill, single-admin capacity with a
measurable P7-009 envelope.

Phase consequence:

Scope authority moves from `PHASE7-PLANNING.md` (planning draft, preserved
as history) to `PHASE7-SCOPE-CONTRACT.md`. Entry-gate criterion 5 is
satisfied. Criteria 7 (Wave 1 contracts + READY promotion) and 9 (explicit
execution authorization) remain outstanding and require separate HPO acts.
No task is created, promoted, or authorized; Phase 7 remains NOT ELIGIBLE
/ NOT AUTHORIZED FOR EXECUTION.

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE7-SCOPE-ADOPTION-001`);
`PHASE7-SCOPE-CONTRACT.md`; ADR-026;
`reviews/PHASE7-ENTRY-REVIEW.md` (§G/§H);
`reviews/PHASE7-ELIGIBILITY-REVIEW.md`.

## Phase 7 Wave 1 READY Promotion — P7-003, P7-008, P7-010

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE1-READY-PROMOTION-001`).

Decision:

The HPO promotes the three authored Wave 1 task contracts to READY:
`P7-003: BACKLOG → READY`; `P7-008: BACKLOG → READY`;
`P7-010: BACKLOG → READY`. Historical BACKLOG states are preserved in
each task file's Status history.

Wave 1 remains `P7-003 + P7-008 + P7-010` with parallel start permitted at
planning level; P7-008 acceptance evidence involving worker
supervision/restart consumes the final authoritative P7-003 supervision
specification (finish-order preference, not a start gate).

Phase consequence:

Entry-gate criterion 7 (Wave 1 contracts authored + READY-promoted) is
satisfied. Criterion 9 (explicit execution authorization) remains
outstanding. No task moves to IN_PROGRESS; Phase 7 remains NOT AUTHORIZED
FOR EXECUTION. `READY != EXECUTION AUTHORIZATION.`

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE7-WAVE1-READY-PROMOTION-001`);
`tasks/P7-003-queue-worker-supervision-recovery.md`;
`tasks/P7-008-deployment-migration-safety-rollback.md`;
`tasks/P7-010-browser-support-matrix-flake-elimination.md`;
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§W/§X).

## Phase 7 Wave 1 Execution Authorization — P7-003, P7-008, P7-010

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`).

Decision:

The HPO authorizes execution of Phase 7 Wave 1 (P7-003 + P7-008 +
P7-010), scoped strictly to their adopted contracts, on the basis of the
independent readiness confirmation
(`reviews/PHASE7-WAVE1-READINESS-CONFIRMATION.md`:
`WAVE 1 READY FOR HPO EXECUTION AUTHORIZATION`; no BLOCKER/HIGH/MEDIUM/
LOW blocking finding; no unauthorized implementation begun).

Parallel implementation is authorized with the recorded constraints
(P7-008 supervised-worker acceptance reconciles against the final P7-003
spec). Prohibited: all non-Wave-1 P7 tasks, D7 reopening, scope changes
without a new HPO decision, object storage, Horizon, browser-matrix
expansion. Debt boundary as authorized (TD-003 → P7-003; TD-005/006/013
→ P7-010 as assigned; P7-008 TD-002 share as scoped; TD-004 stays
conditional; nothing marked closed by authorization).

Phase consequence:

Entry-gate criterion 9 is satisfied for Wave 1 scope. Final authorized
state: `PHASE 7 WAVE 1 — AUTHORIZED FOR EXECUTION` (P7-003, P7-008,
P7-010 only). No later Phase 7 wave is authorized. Tasks transition to
IN_PROGRESS only when actual work begins, per the State-to-Action
Contract; independent review required before any later wave.

Reference:

`DECISION_QUEUE.md`
(`DECISION-PHASE7-WAVE1-EXECUTION-AUTHORIZATION-001`);
`reviews/PHASE7-WAVE1-READINESS-CONFIRMATION.md`;
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§X/§Y).

## P7-008 AC2 Environmental Gap — HPO Disposition (Option A)

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-008-AC2-DISPOSITION-001`).

Decision:

The HPO accepts the missing P7-008 AC2 real-host reboot-cycle/systemd
evidence as a NON-BLOCKING ENVIRONMENTAL CLOSURE EXCEPTION (Option A).
The independent reviewer (`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md`
§C) reproduced all other load-bearing evidence and demonstrated no code
defect; the gap is environmental (no Linux/systemd host available),
and repository precedent permits accept-with-limitation closure
(`DECISION-P3-ESCALATION-001`).

AC2 is NOT rewritten as PASS. Carry-forward obligation (non-blocking):
real-host reboot-cycle verification (P7-008 AC2) and real-host
SIGTERM-drain re-confirmation (P7-003 AC8, reviewer INFO-1) must be
performed during P7-001 environment certification on the Linux
production/staging target, before P7-012. No P7-008 rework is implied
unless that verification reveals a defect.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-008-AC2-DISPOSITION-001`);
`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md` (§C);
`reviews/P7-008-BUILDER-REPORT.md`.

## P7-003 Closure — Queue / Worker Supervision + Recovery

Date: 2026-09-26

Status: DECIDED — Human Product Owner (`DECISION-P7-003-CLOSURE-001`).

Decision:

The HPO reviewed the independent review
(`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md` §B: VERIFIED, no
BLOCKER/HIGH; LOW-1 housekeeping + INFO-1 environmental note, neither
blocking) and concurs: AC1–AC10 satisfied (AC8 by specified mechanism +
budget on this host class; real-host re-confirmation carried forward per
the AC2 disposition record). Canonical transition VERIFIED → DONE;
history preserved, not rewritten. LOW-1 stray file (P7-003 demo marker
residue at repo root, provenance established from demo PID/timestamp)
deleted during this closure and recorded here — no unrelated residue
touched. TD-003 remediation stands implemented + VERIFIED; register
status follows the debt lifecycle (evidence noted, production-gate proof
still owed at P7-012 — not closed by this decision).

Phase consequence: P7-003 = DONE. Authorizes no further work.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-003-CLOSURE-001`); task file;
`reviews/P7-003-BUILDER-REPORT.md`; `docs/QUEUE-WORKER-SUPERVISION.md`.

## P7-010 Closure — Browser Support Matrix + Flake Elimination

Date: 2026-09-26

Status: DECIDED — Human Product Owner (`DECISION-P7-010-CLOSURE-001`).

Decision:

The HPO reviewed the independent review
(`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md` §D: VERIFIED, no
BLOCKER/HIGH) and concurs: AC1–AC9 satisfied on real Chromium with
retries 0 and no silent skips; TD-005 eliminated inside the supported
matrix with gate sensitivity strengthened; TD-006 evidenced; TD-013
fixed with console-clean regression; Chromium-only boundary intact with
Firefox/WebKit history preserved and no fixed-by-exclusion claims.
Canonical transition VERIFIED → DONE; history preserved. TD-005/006/013
implementation evidence stands VERIFIED; register statuses follow the
debt lifecycle (evidence noted — not closed by this decision).

Phase consequence: P7-010 = DONE. Authorizes no further work.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-010-CLOSURE-001`); task file;
`reviews/P7-010-BUILDER-REPORT.md`; `docs/BROWSER-SUPPORT-MATRIX.md`.

## P7-008 Closure — Deployment, Migration Safety, Rollback

Date: 2026-09-26

Status: DECIDED — Human Product Owner (`DECISION-P7-008-CLOSURE-001`,
under environmental exception `DECISION-P7-008-AC2-DISPOSITION-001`).

Decision:

The HPO reviewed the independent review
(`reviews/PHASE7-WAVE1-INDEPENDENT-REVIEW.md` §C: VERIFIED, 8/9 ACs
independently satisfied, AC2 environmentally blocked without defect) and
the Option A disposition above, and closes P7-008 DONE. Canonical
transition VERIFIED → DONE; history preserved. AC2 remains explicitly
NOT PASS; its real-host verification carries forward to P7-001
certification (pre-P7-012). LOW-2 (pg_dump hook as forward reference)
preserved as documented scope.

Phase consequence: P7-008 = DONE. Wave 1 terminal state: P7-003 DONE,
P7-008 DONE (under AC2 exception), P7-010 DONE.
`PHASE 7 WAVE 1 = CLOSED.` Wave 2 remains unauthorized; Wave 2 contracts
remain preparation artifacts.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-008-CLOSURE-001`,
`DECISION-P7-008-AC2-DISPOSITION-001`); task file;
`reviews/P7-008-BUILDER-REPORT.md`; `docs/DEPLOYMENT-RUNBOOK.md`.

## Phase 7 Wave 2 READY Promotion — P7-001, P7-006, P7-007

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE2-READY-PROMOTION-001`).

Decision:

The HPO promotes the three reconciled Wave 2 task contracts to READY:
`P7-001: BACKLOG → READY`; `P7-006: BACKLOG → READY`;
`P7-007: BACKLOG → READY`. Historical BACKLOG/CONTRACT_AUTHORED states
are preserved in each task file's Status history.

Reconciliation basis: P7-001 amended for the final Wave 1 DONE
interfaces (extension through `ProductionConfigGuard::violations()`,
`deployment:verify` sub-check composition, supervision-spec §8
"TD-004 definition; P7-001 certifies" split) and for the binding AC2
carry-forward (P7-008 AC2 reboot-cycle + P7-003 AC8 SIGTERM-drain
re-confirmation during P7-001 certification on the Linux target,
pre-P7-012; §§6.7/9.8/AC8). P7-006 reconfirmed with no semantic
reconciliation required. P7-007 hook wording verified verbatim against
the final runbook; foundation-only boundary, Wave 3 drill deferral,
and P7-002 dependency preserved; G-08 not claimed; AC2 not treated as
PASS.

Phase consequence:

Wave 2 tasks are READY. Entry state:
`PHASE 7 WAVE 2 TASKS READY — EXECUTION NOT AUTHORIZED`. No task moves
to IN_PROGRESS under this decision; Wave 2 execution requires a
separate explicit HPO execution authorization after a readiness
confirmation. P7-008 AC2 remains NOT PASS with the carry-forward
obligation unchanged. No debt item is closed, remediated, or VERIFIED
by this promotion. No later wave is authorized.

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE7-WAVE2-READY-PROMOTION-001`);
`tasks/P7-001-production-configuration-env-validation.md`;
`tasks/P7-006-security-hardening-baseline.md`;
`tasks/P7-007-backup-restore-foundation.md`;
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AA).

## Phase 7 Wave 2 Execution Authorization — P7-001, P7-006, P7-007

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`).

Decision:

The HPO authorizes execution of Phase 7 Wave 2 (P7-001 + P7-006 +
P7-007), scoped strictly to their adopted contracts, on the basis of
the independent readiness confirmation
(`WAVE 2 READY FOR HPO EXECUTION AUTHORIZATION`; no BLOCKER/HIGH
finding; no unauthorized implementation begun).

Execution shape `PARALLEL-SAFE WITH FILE-OWNERSHIP SEQUENCING`
(P7-001 owns guard-extension/runbook deltas; P7-007 hook content only,
preferably after). Boundaries: single config verdict, AC2 never PASS
(new evidence preserves history; failures route via governance);
D7-04 ClamAV binding, Chromium-only, no third-party egress; P7-007
foundation only (no drill, no G-08 claim, no P7-002 work). Debt
boundary as authorized (TD-004 → P7-001; TD-002 contracted portions;
nothing marked closed by authorization). Prohibited: all non-Wave-2 P7
tasks, Wave 3, D7 reopening, object storage, Horizon, matrix
expansion.

Phase consequence:

Final authorized state: `PHASE 7 WAVE 2 — AUTHORIZED FOR EXECUTION`
(P7-001, P7-006, P7-007 only). Tasks transition to IN_PROGRESS only
when actual work begins, per the State-to-Action Contract;
independent review required before any later wave. No later Phase 7
wave is authorized.

Reference:

`DECISION_QUEUE.md`
(`DECISION-PHASE7-WAVE2-EXECUTION-AUTHORIZATION-001`);
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AB).

## P7-001 AC8 Environmental Disposition (Option A)

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-001-AC8-DISPOSITION-001`).

Decision:

HPO accepts the missing P7-001 AC8 real-host evidence as a
non-blocking environmental closure exception (Option A): AC8 =
`ENVIRONMENT-BLOCKED — ACCEPTED AS NON-BLOCKING FOR P7-001 CLOSURE`.
AC8 is NOT rewritten as PASS. P7-008 AC2 stays NOT PASS. Both
real-host checks (P7-008 AC2 reboot-cycle; P7-003 AC8 SIGTERM-drain
re-confirmation) remain pre-P7-012 obligations via the P7-001
evidence mechanism; a later failure opens fresh remediation against
the originating task/surface. Basis: AC1–AC7 independently VERIFIED,
no defect, no suitable Linux host; precedent
(`DECISION-P3-ESCALATION-001`, `DECISION-P7-008-AC2-DISPOSITION-001`);
the contract's non-automatic-precedent language satisfied by this
affirmative act.

Reference: `DECISION_QUEUE.md` (`DECISION-P7-001-AC8-DISPOSITION-001`).

## P7-001 Closure — Production Configuration + Env Validation

Date: 2026-09-26

Status: DECIDED — Human Product Owner (`DECISION-P7-001-CLOSURE-001`).

Decision: HPO closes P7-001 VERIFIED → DONE under
`DECISION-P7-001-AC8-DISPOSITION-001`. AC8 NOT PASS, carried forward.
Authorizes no further work and no Wave 3 execution.

Phase consequence: P7-001 = DONE.

Reference: `DECISION_QUEUE.md` (`DECISION-P7-001-CLOSURE-001`); task
file; `reviews/P7-001-BUILDER-REPORT.md` (corrected for F1).

## P7-006 Closure — Security Hardening Baseline

Date: 2026-09-26

Status: DECIDED — Human Product Owner (`DECISION-P7-006-CLOSURE-001`).

Decision: HPO closes P7-006 VERIFIED → DONE. F4: CSP `unsafe-eval`
narrative justification accepted as sufficient; preserved as a
non-blocking evidence gap (missing artifact not represented as
existing); implementation not reopened. F5 reconciled with evidence
(`guardRefusal` call sites at `ProductionConfigGuard.php:95`,
`ProductionPostureChecks.php:74`); the independent review is
preserved unchanged. AC4 real-daemon proof stays target-only.
Authorizes no further work and no Wave 3 execution.

Phase consequence: P7-006 = DONE.

Reference: `DECISION_QUEUE.md` (`DECISION-P7-006-CLOSURE-001`); task
file; `reviews/P7-006-BUILDER-REPORT.md`.

## P7-007 Closure — Backup / Restore Foundation

Date: 2026-09-26

Status: DECIDED — Human Product Owner (`DECISION-P7-007-CLOSURE-001`).

Decision: HPO closes P7-007 VERIFIED → DONE. F6 reconciled by
correcting the builder report in place (false test claim removed;
path real but untested); follow-up (add the unwritable-target test
in a later wave) recorded, non-blocking, no code in this closure.
Drill deferred to Wave 3; G-08 not claimed. Authorizes no further
work and no Wave 3 execution.

Phase consequence: P7-007 = DONE.

Reference: `DECISION_QUEUE.md` (`DECISION-P7-007-CLOSURE-001`); task
file; `reviews/P7-007-BUILDER-REPORT.md` (corrected for F6).

## TD-008 Reprioritization — Full-Suite Flakiness Frequency

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-TD-008-REPRIORITIZATION-001`).

Decision: `TD-008 — REPRIORITIZATION REQUIRED`. Status stays OPEN
(unresolved, unattributed to Wave 2); severity LOW → MEDIUM; moved to
the must-resolve-before-production-launch set with an explicit
prerequisite: a dedicated suite-hygiene remediation task must land
before the P7-012 terminal gate. No remediation implemented here;
scoping belongs to Wave 3/4 planning. Wave 2 closure not blocked
("no regression attributable to the task diff" holds).

Reference: `DECISION_QUEUE.md`
(`DECISION-TD-008-REPRIORITIZATION-001`);
`docs/TECHNICAL_DEBT_REGISTER.md` (TD-008 entry updated).

## Phase 7 Wave 2 Closure

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE2-CLOSURE-001`).

Decision: HPO closes Phase 7 Wave 2. P7-001 DONE (under AC8
exception), P7-006 DONE, P7-007 DONE; all independently VERIFIED; no
BLOCKER/HIGH; evidence gaps preserved honestly; TD-008
reprioritization recorded; no Wave 3 work started or authorized.

Phase consequence:

Wave 2 terminal state: P7-001 DONE, P7-006 DONE, P7-007 DONE.
`PHASE 7 WAVE 2 = CLOSED` (carry-forwards: P7-001 AC8 + P7-008 AC2
real-host evidence pre-P7-012; F4 gap preserved; unwritable-target
test follow-up open; TD-008 reprioritized). Wave 2 closure authorizes
no Wave 3 promotion, IN_PROGRESS, or execution.

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE7-WAVE2-CLOSURE-001`);
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AC).

## Phase 7 Wave 3A READY Promotion — P7-002, P7-004

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE3A-READY-PROMOTION-001`).

Decision: HPO promotes the reconciled Wave 3A contracts to READY.
P7-002 BACKLOG → READY (reconciled against final P7-001 + P7-007 DONE
interfaces; D7-01/B binding; drill downstream); P7-004 BACKLOG → READY
(reconciled against final P7-001 + P7-006 + P7-007 DONE interfaces;
D7-03/A binding; TD-011 intact). No stale preparation-stage assumption
survives in either contract; histories preserved in each task file.

Phase consequence:

Wave 3A tasks READY; execution NOT AUTHORIZED. P7-009/P7-011 remain
BACKLOG under their Wave 3 internal gates (P7-004 VERIFIED;
P7-002 DONE + P7-004 DONE); P7-007 drill deferred (P7-002 DONE +
separate authorization); P7-012 FINAL_GATE_ONLY. TD-008 OPEN/MEDIUM
pre-P7-012 prerequisite preserved, non-blocking for Wave 3A. No debt
item closed by this promotion.

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE7-WAVE3A-READY-PROMOTION-001`);
task files (`tasks/P7-002-*`, `tasks/P7-004-*` Status histories);
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AD).

## Phase 7 Wave 3A Execution Authorization — P7-002, P7-004

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`).

Decision: HPO authorizes Wave 3A execution for P7-002 + P7-004,
scoped strictly to their adopted contracts, shape PARALLEL-SAFE WITH
FILE-OWNERSHIP SEQUENCING (P7-002 owns DB env/inventory deltas;
P7-004 owns storage deltas; additive verify checks; P7-008 canonical
runbook; P7-007 hooks consumed, not redefined). P7-002 absorbs no
drill and claims no restore readiness; P7-004 introduces no object
storage. TD-008 OPEN/MEDIUM/pre-P7-012 preserved.

Phase consequence:

`PHASE 7 WAVE 3A — AUTHORIZED FOR EXECUTION.` P7-002/P7-004 stay
READY until actual work begins (then READY → IN_PROGRESS); REVIEW →
VERIFIED → DONE only via independent review + HPO closure. P7-009 /
P7-011 BACKLOG under internal gates; P7-007 drill deferred (P7-002
DONE + separate authorization); P7-012 FINAL_GATE_ONLY.

Reference:

`DECISION_QUEUE.md`
(`DECISION-PHASE7-WAVE3A-EXECUTION-AUTHORIZATION-001`);
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AE).

## P7-002 PG Environmental Disposition (Option A)

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-002-PG-ENV-DISPOSITION-001`).

Decision: HPO accepts the unavailable live-PostgreSQL execution
evidence (AC1/AC2/AC5/AC6 pg-halves) as
`ENVIRONMENT-BLOCKED — ACCEPTED AS NON-BLOCKING FOR P7-002 CLOSURE`,
following the Wave 1/2 environmental-limitation precedent. The
blocked halves are NOT rewritten as PASS; real pg rehearsal/proof is
carried forward pre-P7-012 (consumed by the P7-007 drill and P7-009's
final run); later target failure opens fresh remediation. G-02/G-08
unclaimed.

Reference: `DECISION_QUEUE.md`
(`DECISION-P7-002-PG-ENV-DISPOSITION-001`).

## P7-002 Closure

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-002-CLOSURE-001`, under
`DECISION-P7-002-PG-ENV-DISPOSITION-001`).

Decision: HPO closes independently VERIFIED P7-002 as DONE.
Canonical transition VERIFIED → DONE; history preserved. Authorizes
no drill, no Wave 3B.

Reference: `DECISION_QUEUE.md` (`DECISION-P7-002-CLOSURE-001`); task
file (`tasks/P7-002-*` Status history).

## P7-004 Closure

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-004-CLOSURE-001`).

Decision: HPO closes independently VERIFIED P7-004 as DONE (no
environment-blocked AC). D7-03 binding preserved; object storage
deferred-not-rejected; TD-011 evidence retained for G-05; zero-byte
remediation history preserved. Authorizes no Wave 3B.

Reference: `DECISION_QUEUE.md` (`DECISION-P7-004-CLOSURE-001`); task
file (`tasks/P7-004-*` Status history).

## Phase 7 Wave 3A Closure

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-PHASE7-WAVE3A-CLOSURE-001`).

Decision: HPO closes Phase 7 Wave 3A. P7-002 DONE (under pg
exception), P7-004 DONE; both independently VERIFIED; no
BLOCKER/HIGH; gaps preserved honestly; no Wave 3B started or
authorized.

Phase consequence:

Wave 3A terminal state: P7-002 DONE, P7-004 DONE.
`PHASE 7 WAVE 3A = CLOSED` (carry-forwards: live-pg evidence
pre-P7-012; P7-007 drill deferred with P7-002 DONE dependency now
satisfied but still requiring separate authorization; TD-008
OPEN/MEDIUM; prior Wave 1/2 real-host obligations). Wave 3A closure
authorizes no P7-009/P7-011 promotion, no drill, no P7-012.

Reference:

`DECISION_QUEUE.md` (`DECISION-PHASE7-WAVE3A-CLOSURE-001`);
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AF).

## P7-011 READY Promotion

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-011-READY-PROMOTION-001`).

Decision: HPO promotes the reconciled P7-011 contract (clock table
§6.10, physical/history boundary §8.4, failure semantics §10) to
READY. P7-004-VERIFIED gate satisfied; D7-06 resolved; TD-007 stays
OPEN (implementation owned here, closes at G-09).

Phase consequence: `P7-011 READY — EXECUTION NOT AUTHORIZED.`
Implementation needs readiness confirmation + separate HPO execution
authorization. P7-009 BACKLOG; drill deferred; P7-012 FINAL_GATE_ONLY.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-011-READY-PROMOTION-001`);
task file (`tasks/P7-011-*` Status history).

## P7-011 Execution Authorization

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-011-EXECUTION-AUTHORIZATION-001`).

Decision: HPO authorizes P7-011 execution under the reconciled
contract (readiness `READY_CONFIRMED`). Strictly P7-011 scope;
TD-007 stays OPEN during execution; P7-009/drill/P7-012/ unrelated
TD work prohibited. Clerical BACKLOG staleness in CURRENT_STATE.md
and plan.md reconciled under this authorization.

Phase consequence: `P7-011 READY → IN_PROGRESS` on work start, then
`→ REVIEW → VERIFIED → DONE` only via independent review + HPO
closure.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-011-EXECUTION-AUTHORIZATION-001`).

## P7-011 Closure

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-011-CLOSURE-001`).

Decision: HPO closes independently VERIFIED P7-011 as DONE (both
cycle-1 MEDIUMs resolved; no BLOCKER/HIGH/MEDIUM; new LOW carried as
TD-014). Full history preserved. TD-007 stays OPEN (evidence
available; closure at authorized G-09 consumption). Authorizes no
P7-009/drill/P7-012/TD-014/later work.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-011-CLOSURE-001`);
task file (`tasks/P7-011-*` Status history);
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AH).

## P7-009 READY Promotion

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-009-READY-PROMOTION-001`).

Decision: HPO promotes the reconciled P7-009 contract to READY
(prerequisites P7-002/P7-004 DONE satisfied; no hidden dependency;
target-environment rule explicit; measure-and-report needs no new
thresholds). TD-001/TD-002 stay OPEN.

Phase consequence: `P7-009 READY — EXECUTION NOT AUTHORIZED.`
Execution needs readiness confirmation + separate HPO execution
authorization. Drill deferred; P7-012 FINAL_GATE_ONLY.

Reference:

`DECISION_QUEUE.md` (`DECISION-P7-009-READY-PROMOTION-001`);
task file (`tasks/P7-009-*` Status history);
`PHASE6-7-ELIGIBILITY-MATRIX.md` (§AI).

## P7-009 Phase A Execution Authorization

Date: 2026-09-26

Status: DECIDED — Human Product Owner
(`DECISION-P7-009-PHASE-A-EXECUTION-AUTHORIZATION-001`).

Decision: HPO authorizes P7-009 Phase A only (harness preparation +
rehearsal on currently available infrastructure) under the reconciled
P7-009 contract (readiness `READY_CONFIRMED — phased authorization
only`; no BLOCKER/HIGH; production-shaped target NOT available).
Current environment classified rehearsal/substitute only; all Phase A
artifacts labeled `REHEARSAL / SUBSTITUTE EVIDENCE — NOT TARGET
CAPACITY EVIDENCE`. Phase B (final production-shaped capacity run)
explicitly withheld pending target host + readiness confirmation +
explicit HPO Phase B authorization.

Phase consequence: `P7-009 READY → IN_PROGRESS` on work start.
P7-009 remains IN_PROGRESS after Phase A. No VERIFIED/DONE claim, no
final capacity evidence, no production capacity claim, no
product/release verdict; no P7-007 drill; TD-007/TD-008 untouched;
TD-014 not implemented; P7-012 not begun.

Reference:

`DECISION_QUEUE.md`
(`DECISION-P7-009-PHASE-A-EXECUTION-AUTHORIZATION-001`);
task file (`tasks/P7-009-*` Status history).

## ADR-027 — ProcessingProvider Initial Architecture Direction (Option 1)

Date: 2026-09-27

Status: ACCEPTED — Human Product Owner
(`DECISION-PROCESSING-PROVIDER-OPTION1-001`).

Decision:

Option 1 — Server-first + pinned direct external-provider capability —
is the approved initial ProcessingProvider architecture direction.

Approved architecture:

- Server platform: Linux / Ubuntu.
- Server compute baseline: CPU-only; GPU not required.
- Separate `TranscriptionProvider` and `TranslationProvider` contracts
  (no generic provider interface).
- Existing self-hosted faster-whisper `large-v3` preserved as the
  canonical transcription implementation.
- Existing self-hosted NLLB-200-distilled-600M preserved as the
  canonical translation implementation.
- External providers are additive only and must normalize into existing
  RTFTT domain structures without leaking vendor shapes.
- Provider selection is deterministic and explicit; providers never call
  each other.
- No silent cloud fallback in either direction.
- No Auto mode in Wave 1; no ClientDeviceProvider in Wave 1.
- External translation deferred from Wave 1 (translation stays
  self-hosted NLLB).
- Chunk identity is execution-only and must not become
  transcription segment/domain identity.
- Per-chunk audit is logs-only initially; a durable chunk-audit table
  may be reconsidered only if later verification proves logs
  insufficient (with explicit HPO authorization, never silently).
- Vendor selection deferred until after the provider
  abstraction/config foundation exists.
- Phase 1–7 historical contracts remain frozen; no DONE task is
  reopened by this direction.

This ADR newly records the direction decided during discovery/planning;
it does not rewrite history to imply earlier durability. Discovery
verdict `DISCOVERY_COMPLETE — OWNER_ARCHITECTURE_DECISION_REQUIRED`
is the basis; this ADR is the durable governance record the PP-T1
contract review required.

Phase consequence:

This ADR authorizes detailed architecture planning and PP-T1–PP-T6
contract authoring only. It authorizes no production implementation,
provider code, migrations, vendor calls, spike execution, model-default
changes, or Phase 1–7 contract modification. PP-T1 READY promotion is
a separate decision (`DECISION-PP-T1-READY-PROMOTION-001`).

Reference:

`DECISION_QUEUE.md` (`DECISION-PROCESSING-PROVIDER-OPTION1-001`,
`DECISION-PP-T1-READY-PROMOTION-001`);
`discovery/processing-provider/DISCOVERY-FINAL-REPORT.md` (options);
`discovery/processing-provider/ARCHITECTURE-PLAN-OPTION1.md`;
task files (`tasks/PP-T1-*` through `tasks/PP-T6-*`).

## P7-009-CORR-01 Real Upload → Transcription Initiation Bridge

Date: 2026-10-01

Status: DECIDED — Human Product Owner
(`DECISION-P7-009-CORR-01-UPLOAD-TRANSCRIPTION-BRIDGE-001`).

Context: the 2026-09-30 target-host readiness confirmation
(`reviews/P7-009-TARGET-HOST-READINESS-CONFIRMATION-002.md`, verdict
`TARGET_HOST_NOT_READY`) raised HIGH finding H-1: no product path exists from
an uploaded `MediaFile` to a real `Transcription`/`ProcessingJob`.

Decision: explicit user-initiated "Start Transcription" from Media Detail
(automatic transcription after upload and operator-only initiation rejected).
`MediaFile != Transcription`. The server-side path authenticates, authorizes,
validates a processable media state, creates a real `Transcription` and calls
`TranscriptionOrchestrator::request()`; double submission must not create
uncontrolled duplicate active work; the demo controller must not be reachable
as a production transcription path; no schema change without returning to the
HPO. Corrective task `P7-009-CORR-01` is authorized for contract,
implementation, testing and independent-review preparation only; contract:
`tasks/P7-009-CORR-01-upload-transcription-initiation-bridge.md`.

Authority boundaries (unchanged): P7-009 Phase B NOT AUTHORIZED; P7-007
restore drill NOT AUTHORIZED; P7-012 FINAL_GATE_ONLY, NOT AUTHORIZED. After
independent verification and deployment of the corrective release, the
Target-Host Readiness Confirmation is re-run; only `TARGET_HOST_READY` permits
the HPO to authorize Phase B separately.

Durable record: this file.

## P7-009-CORR-01 Corrective Cycle 1 (F-1 / F-2)

Date: 2026-10-02

Status: DECIDED — Human Product Owner
(`DECISION-P7-009-CORR-01-CYCLE1-001`).

Context: the first independent review of `P7-009-CORR-01`
(`reviews/P7-009-CORR-01-INDEPENDENT-REVIEW.md`, verdict VERIFIED) recorded two
non-blocking findings for the HPO: F-1 (MEDIUM — a Queued/Draft transcription
whose dispatch failed after commit has no UI recovery path) and F-2 (LOW —
`TranscriptionRetry` can reactivate an earlier Failed transcription alongside a
Start-created one, giving two active transcriptions for one media file). F-3
(LOW — repeated Start on an active transcription enqueues another no-op
`ProcessTranscription` message) was accepted as non-blocking and not fixed.

Decision: authorize one corrective cycle limited to F-1 and F-2, followed by a
fresh independent re-review. Scope boundary as recorded in the task contract
("Corrective Cycle 1 — Implementation Notes"): no migration, no dependency, no
worker/provider/queue/orchestrator change, no commit, no deployment; the
database-level "one active transcription per media" index (review F-6)
remained NOT authorized.

Record provenance: the original HPO text of this decision was not held in the
repository before this entry; this entry records its scope as cited by the task
contract, the Cycle 1 implementation notes and the Cycle 1 independent
re-review. It was first made durable here by the closure reconciliation
(`DECISION-P7-009-CORR-01-CLOSURE-001`).

Outcome: implemented 2026-10-02 (Resume Transcription control on Media Detail
and Transcription Detail reusing `media.transcriptions.store`; controller
`Throwable` handling that redirects to the stranded transcription;
`TranscriptionRetry` media-row lock plus competing-active-transcription refusal
with atomic rollback). Independent re-review:
`reviews/P7-009-CORR-01-CYCLE1-INDEPENDENT-REVIEW.md` — VERIFIED; F-1 CLOSED,
F-2 CLOSED, F-3 accepted non-blocking.

Authority boundaries (unchanged): P7-009 Phase B NOT AUTHORIZED; P7-007
restore drill NOT AUTHORIZED; P7-012 FINAL_GATE_ONLY, NOT AUTHORIZED.

Durable record: this file.

## P7-009-CORR-01 Closure and Commit Authorization

Date: 2026-10-02

Status: DECIDED — Human Product Owner
(`DECISION-P7-009-CORR-01-CLOSURE-001`).

Baseline: independent re-review verdict
`P7-009-CORR-01 Corrective Cycle 1 = VERIFIED`
(`reviews/P7-009-CORR-01-CYCLE1-INDEPENDENT-REVIEW.md`). BLOCKER 0, HIGH 0,
MEDIUM 0. AC1–AC13 PASS. **AC14 NOT YET EVIDENCED.** F-1 CLOSED; F-2 CLOSED;
F-3 accepted non-blocking. Reviewer-reproduced: full suite PASS (1295 tests,
1290 passed, 5 environment-skipped, 0 failures), Pint PASS, PHPStan PASS. The
HPO accepts the independent review and re-review.

Decision:

- `P7-009-CORR-01` = **VERIFIED / ACCEPTED FOR CLOSURE**. This is not DONE
  and is not a claim that AC14 passed.
- The HPO authorizes the canonical corrective change set to be committed and
  pushed: the corrective implementation, its tests, the task contract, the
  target-host readiness confirmation that raised H-1, both independent review
  artifacts, and the governance records. Unrelated work is excluded.

Carry-forward findings (accepted as non-blocking; none blocks corrective
closure, commit, or deployment preparation):

- **RR-1 — LOW.** A stale Resume page can initiate a new transcription after
  the previous transcription has already become terminal. Carried forward to
  real-host / product UX verification.
- **RR-2 — LOW.** Retry (lock order transcription row → media row) and media
  deletion (media row → transcription row via FK cascade) can acquire locks in
  opposite order and may deadlock under concurrent execution on PostgreSQL.
  Carried forward to Phase 7 real-host / concurrency verification.
- **RR-3 and later — INFO.** Retained as documented informational findings in
  the Cycle 1 re-review (RR-3 Retry endpoint generic 500 on post-commit
  dispatch failure; RR-4 Resume visibility vs. processable check; RR-5 Resume
  offered for a healthy Queued attempt; RR-6 governance lag — resolved by this
  reconciliation; RR-7 "dispatched" log precedes the push; RR-8 carried-over
  prior-review INFO items).
- The concurrency guarantee of the media-row-lock design is established by
  PostgreSQL reasoning, not by an executed PostgreSQL run (none exists in the
  review environment); it is verified on the target host (sequence below).

Post-commit sequence (binding order):

1. deploy a new immutable release;
2. do not modify the existing release in place;
3. execute AC14 on the target host;
4. perform the PostgreSQL concurrency checks listed by the reviewer
   (`reviews/P7-009-CORR-01-CYCLE1-INDEPENDENT-REVIEW.md` §14);
5. rerun Target-Host Readiness Confirmation;
6. only `TARGET_HOST_READY` may permit a later HPO authorization of P7-009
   Phase B.

This decision does NOT authorize: P7-009 Phase B; the P7-007 restore drill;
P7-012; final production readiness. Current target-host readiness verdict
remains `TARGET_HOST_NOT_READY` until re-confirmed after the corrective
release. AC14 is not marked PASS and P7-009 Phase B is not marked authorized
by this record.

Durable record: this file.
