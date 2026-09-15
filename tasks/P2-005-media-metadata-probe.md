# P2-005 — Media Metadata Probe

## Status

DONE

P2-005 is implemented and independently VERIFIED in the remediation-cycle-2
re-review. Work closed the task as DONE while preserving the prior and current
independent review evidence.

## Authorization State

The earlier denial below is preserved as historical authorization context. The
Product Owner explicitly authorizes the minimum FFprobe/FFmpeg runtime needed
for metadata probing as Phase 2 scope in ADR-012. Transcription, speech
recognition, translation, workers, queues, and all other Phase 3 behavior remain
outside this authorization.

The original denial of FFprobe/FFmpeg and unrelated runtime changes is retained
as historical context and is superseded only for this bounded P2-005 remediation
by ADR-012. No queue, worker, transcription, translation, or processing behavior
is authorized.

## Ownership

Decision owner: Human Product Owner for Phase 3 authorization, media-parsing
trust boundaries, and any external binary/dependency decision.

Implementation owner: Codex, under the Product Owner authorization recorded in
ADR-012.

Independent reviewer: Claude Code after implementation, if authorized.

## Current State Assessment

### Existing `MediaFile` metadata fields

The initial `media_files` migration already contains these technical fields:

- `duration_seconds` — nullable unsigned integer;
- `audio_codec` — nullable string;
- `video_codec` — nullable string;
- `sample_rate` — nullable unsigned integer; and
- `channels` — nullable unsigned small integer.

The model casts the numeric fields and permits the fields through its existing
mass-assignment boundary. No new schema is required merely to populate these
existing nullable columns.

The model also stores request/file metadata and storage identity, including
`original_filename`, `display_name`, `mime_type`, `extension`,
`file_size_bytes`, `media_type`, `storage_path`, `storage_filename`, and the
server-owned checksum.

### What P2-003 populates

P2-003 populates request/file metadata from the uploaded file and server-side
storage inspection:

- original filename and filename-derived display name;
- extension and server-detected MIME type;
- audio/video media type from the approved extension/MIME matrix;
- received byte size;
- opaque private storage path and filename;
- server-generated lowercase SHA-256 checksum;
- authenticated owner and optional authorized folder; and
- `MediaStatus::Uploaded`.

### What P2-003 intentionally leaves null

P2-003 explicitly writes `null` for:

- `duration_seconds`;
- `audio_codec`;
- `video_codec`;
- `sample_rate`; and
- `channels`.

This is deliberate. P2-003 performs no media probing and does not claim that a
filename, extension, MIME type, or demo value is technical media metadata.

### Existing probing/tool state

Before ADR-012, no FFprobe or FFmpeg binary or probe adapter was authorized.
The current bounded remediation uses the configured FFprobe executable through
the existing transitive `symfony/process` capability; this does not authorize
broader media processing.

The repository contains prototype `ProcessingJob` and transcription models,
seeded technical values, and queue configuration, but these are not an
accepted P2-003 probing implementation. They do not establish a current
runtime probe boundary or authorize worker behavior.

### Current execution behavior and tests

P2-003 remains synchronous only for receiving, validation, checksum, promotion,
and `MediaFile` persistence. P2-005 probes only an already persisted object and
does not dispatch a processing job.

The verified P2-003 tests assert that technical fields such as duration remain
null for real ingestion and that successful upload creates no `Transcription`
or `ProcessingJob` side effects. Existing tests do not and should not claim to
verify FFprobe behavior.

## Canonical Phase Reconciliation

The repository’s canonical sequence is:

1. Phase 1 — Application Foundation and Full Clickable Prototype;
2. Phase 2 — Real File Upload and Media Library;
3. Phase 3 — FFmpeg / FFprobe Media Processing;
4. Phase 4 — Independent faster-whisper Worker; and
5. Phase 5 — Laravel ↔ Transcription Worker Integration.

`plan.md`, `architecture.md`, `DECISIONS.md` ADR-011, and `CURRENT_STATE.md`
all preserve Phase 3 as the FFmpeg/FFprobe boundary. They also state that the
media-parsing/FFmpeg trust boundary must be decided before Phase 3 execution.

Therefore the label `P2-005 — Media Metadata Probe` is authorized by ADR-012 as
a bounded Phase 2 remediation task only; it does not renumber the canonical
roadmap or authorize the remaining Phase 3 media-processing scope. The prior
planning-only conclusion is preserved in the historical record below.
it is not a canonical Phase 2 task identifier. Any future implementation must
be mapped to the approved Phase 3 plan or another explicitly approved
repository phase without renumbering the canonical roadmap.

## Proposed Objective

When the Phase 3 gate is opened, define and implement a bounded media metadata
probe that reads approved technical metadata from an already persisted private
`MediaFile` without changing upload ownership, storage identity, checksum,
same-attempt idempotency, or the Phase 2 upload contract.

This objective is now implemented under ADR-012 and remains limited to the
existing nullable fields and a bounded FFprobe invocation.

## Scope of This Planning Record

- reconcile the existing nullable technical fields with the P2-003 contract;
- compare synchronous, deferred, contract-only, and no-op approaches;
- identify the Phase 3 trust-boundary and dependency decisions required before
  implementation;
- define a future bounded probe contract and acceptance baseline;
- identify future task decomposition and tests; and
- preserve P2-003, ADR-009, and the canonical phase numbering.

## Explicit Non-Scope

This record does not include:

- FFprobe or FFmpeg installation or invocation;
- a PHP media-probing package or external binary dependency;
- changes to the upload controller, ingestion service, upload UI, or storage
  layout;
- migrations or new metadata/status tables;
- transcription, translation, Whisper, faster-whisper, or provider routing;
- queue, worker, Redis, Horizon, callback, or processing-job implementation;
- transcript generation, export, or workspace behavior;
- media deletion, rename, folder, ownership, or multi-tenancy changes;
- staging cleanup or durable orphan reconciliation;
- scheduler or production automation; and
- P2-006+, Phase 3 implementation, or any later phase.

## Architecture Options

### Option A — Synchronous probe during upload

Pros:

- technical metadata would be available immediately after upload;
- no separate post-upload trigger would be required; and
- existing nullable columns could be populated without a new metadata table.

Cons and risks:

- couples a potentially expensive external probe to the 500 MiB HTTP receiving
  request;
- adds probe startup, read, timeout, and resource failures to upload success;
- makes browser completion and retry behavior more complex;
- risks deleting, rolling back, or misclassifying a valid uploaded media record
  if probe failure is treated as upload failure; and
- directly crosses the repository’s Phase 2/Phase 3 boundary.

Compatibility with P2-003 is poor unless the probe is explicitly separated
after persistence and upload success never depends on it. No such change is
authorized.

### Option B — Deferred probe job

Pros:

- keeps the large-file HTTP receiving path independent of probe duration;
- permits explicit retries, timeout handling, and operational reporting; and
- can preserve `MediaStatus::Uploaded` while technical metadata is incomplete.

Cons and risks:

- requires an approved trigger, queue/worker boundary, job contract, failure
  classification, retry policy, and status/observability semantics;
- risks pulling Phase 4/5 worker and queue concerns into a metadata task;
- the current repository has no accepted probe job or operational contract; and
- queue configuration and prototype processing models do not constitute
  authorization.

This historical option was not authorized before ADR-012 and is not the current
implementation boundary.

### Option C — Probe contract/interface only, implementation deferred

Pros:

- could clarify the future boundary without changing P2-003;
- could make dependency and result normalization explicit before code; and
- avoids installing or invoking external media tooling now.

Cons and risks:

- may create speculative abstractions before a real Phase 3 requirement exists;
- does not populate metadata or solve operational failure behavior; and
- still requires a media-parsing trust-boundary decision before it is
  implementation-ready.

This was suitable only as historical Phase 3 planning; ADR-012 makes the narrow
P2-005 implementation runnable in Phase 2.

### Option D — No-op metadata policy for now

Pros:

- exactly matches the verified P2-003 behavior;
- requires no dependency, migration, queue, runtime, or upload change;
- keeps valid uploaded media safe when technical metadata is unavailable; and
- respects the canonical Phase 3 boundary and current authorization state.

Cons and risks:

- technical fields remain null until a future approved probe exists;
- downstream UX cannot rely on duration or codec fields for real uploads yet;
- a later Phase 3 task must define the probe contract and failure behavior.

This is the safest current policy and the recommended approach.

## Recommended Contract

### Current contract

The former Option D recommendation is preserved as historical context. ADR-012
selects the narrow probe implementation for P2-005:

- P2-003 remains the complete upload/ingestion boundary.
- A successful upload remains `MediaStatus::Uploaded`.
- Existing technical metadata fields remain nullable and are left null for
  real P2-003 uploads.
- Upload success does not depend on probing.
- No probe trigger exists in the current application.
- The minimum FFprobe/FFmpeg runtime dependency is approved for metadata probing.
- Existing private opaque storage, ownership, checksum, and retry behavior are
  unchanged.

### Remaining Phase 3 contract requirements

Before broader Phase 3 implementation is authorized, a later task must decide:

- the approved FFprobe/FFmpeg trust boundary and dependency/version policy;
- whether the probe runs in an isolated local process or another approved
  runtime boundary;
- the exact fields and units accepted for duration, codecs, sample rate, and
  channels;
- whether unsupported or corrupt media produces null fields, a probe-failure
  status, or another explicitly approved result;
- process timeout, memory, CPU, output-size, and cancellation limits;
- storage access through the configured private disk without public exposure;
- whether probing is synchronous only after persistence or deferred behind a
  separately authorized job boundary;
- idempotent re-probe behavior and ownership checks;
- logging, privacy, and error visibility; and
- whether any new schema/status fields are actually required.

No decision is silently made for these future questions by this artifact.

## Future Task Decomposition

| Work item | Purpose | Likely affected surfaces | Acceptance focus | Explicit non-scope |
|---|---|---|---|---|
| Phase 3 trust-boundary decision | Approve media parsing tool/runtime and security boundary | `DECISIONS.md`, `architecture.md`, Phase 3 task record | Dependency, process isolation, resource limits, supported media, failure policy are explicit | No binary install or application code |
| Metadata probe contract | Define normalized fields and lifecycle behavior | Phase 3 task, architecture/decision records, existing `MediaFile` fields | Units, nullability, idempotency, ownership, failure semantics are explicit | No transcription or queue implementation |
| Probe implementation | Read private persisted media and update approved fields | Future probe service/adapter, controlled configuration, tests | Accurate or safely null metadata; upload remains safe and unchanged | No upload redesign or Phase 4/5 behavior |
| Probe verification | Independently reproduce success/failure/regression behavior | Feature/integration tests and review artifact | Corrupt/unavailable/timeout/re-probe cases and no-side-effect guarantees | No cleanup or later-phase work |

No work item in this table is READY or authorized.

## Future Acceptance Criteria

Any later authorized implementation must demonstrate:

1. P2-003 upload success, ownership, storage, checksum, retry, and compensation
   behavior remains unchanged unless separately approved.
2. Technical metadata is populated only from a trusted probe result, not from
   extension, filename, MIME guess, or seeded demo values.
3. Existing nullable fields receive accurate values with documented units, or
   remain safely null when a value is unavailable or unsupported.
4. Probe failure, timeout, unavailable dependency, corrupt media, and malformed
   probe output do not delete, corrupt, or expose the uploaded private media.
5. The configured private disk and opaque storage path remain authoritative;
   public storage is never used.
6. Re-probing the same `MediaFile` is owner-safe and idempotent according to the
   approved future contract.
7. A 500 MiB upload is not made dependent on an unbounded synchronous probe.
8. No transcription, translation, queue, worker, Redis, Horizon, or processing
   side effect occurs unless separately authorized by its own phase gate.
9. Existing prototype/demo metadata remains distinguishable from real probe
   results where that distinction matters to the product contract.
10. Independent review reproduces the relevant tests and confirms the Phase 3
    trust boundary.

## Testing Requirements for a Future Implementation

The future implementation task must include tests for:

- successful extraction of duration and applicable audio/video technical fields;
- unsupported media or absent stream metadata remaining safely null;
- corrupt or invalid media producing a safe, classified failure;
- unavailable probe dependency behavior;
- timeout/resource-limit behavior;
- re-probe idempotency and no duplicate side effects;
- private-disk and ownership enforcement;
- preservation of the uploaded object and `MediaFile` on probe failure;
- no `Transcription` or `ProcessingJob` creation;
- no upload regression at normal and boundary sizes; and
- compatibility with existing rows whose technical fields are null.

If a database-backed status or error field is later proposed, migration,
rollback, existing-row, and backfill safety tests are required before that
change is authorized. No such migration is required by the current schema.

## Risks and Regression Concerns

- FFprobe installation and version variance could produce inconsistent fields
  or output formats across environments.
- Running a probe synchronously against a 500 MiB upload could create request
  timeout and user-experience failures.
- Treating probe failure as upload failure could corrupt the verified P2-003
  success/compensation contract.
- A deferred implementation could grow into queue, worker, Redis, Horizon, or
  Phase 4/5 orchestration without a separate decision.
- Inaccurate or guessed metadata is worse than a documented null value.
- Invoking an external binary without a trust boundary could create command,
  resource, path, or untrusted-input risks.
- The dirty worktree contains unrelated governance and implementation changes;
  broad staging would compromise isolation.
- Machine-specific Herd or local binary configuration must not become portable
  application configuration.

## Implementation Isolation Plan

No implementation is authorized. If a future Phase 3 task is approved:

1. Record the media-parsing/FFmpeg trust-boundary decision before dependency or
   runtime changes.
2. Use an agreed baseline containing the accepted P2-003 artifacts in a
   separate branch or worktree where possible.
3. Leave the current dirty worktree untouched and classify every changed path.
4. Isolate probe code, dependency/configuration changes, tests, and task/state
   records to the approved Phase 3 task.
5. Do not stage or commit unrelated P2-004/P2-004A/P2-004A1 artifacts, runtime
   configuration, or later-phase files.
6. Run focused tests, the full regression suite, applicable static analysis and
   build checks, `git diff --check`, and independent review before closure.

## Review Requirements

This planning artifact requires no implementation review. Any future probe
implementation must be independently reviewed for:

- conformance to the approved Phase 3 trust boundary;
- accurate field mapping and units;
- safe failure, timeout, and unavailable-dependency behavior;
- preservation of private storage, ownership, checksum, and upload idempotency;
- no deletion or corruption on probe failure;
- regression against P2-003; and
- absence of transcription, worker, queue, and later-phase scope.

## Phase Boundary

P2-005 is not a current Phase 2 execution task. FFprobe/FFmpeg metadata probing
belongs to the repository-defined Phase 3 media-processing boundary. Phase 4
remains the independent faster-whisper worker and Phase 5 remains Laravel ↔
worker integration. None of these phases is authorized by this artifact.

## Recommended Authorization Decision

`CLOSE_P2-005_AS_DEFERRED`

The existing schema is already capable of storing the named nullable technical
fields, but the repository has no approved probe dependency, runtime boundary,
failure contract, or Phase 3 authorization. P2-003 is correct to leave these
fields null. A Phase 2 probe would conflict with the canonical roadmap and risk
coupling the verified 500 MiB upload path to an unbounded external process.

If metadata probing becomes necessary, reopen it as a separately mapped Phase 3
decision and implementation task after the media-parsing/FFmpeg trust boundary
is approved. Until then, retain the no-op metadata policy and leave the fields
null for real P2-003 uploads.

## Next Action

Historical pre-authorization recommendations are superseded by ADR-012. The
current task is DONE after independent verification. Do not begin P2-007 or
Phase 3.
