# RTFTT Transcribe AI Architecture

## Current Phase 1 Architecture

```
Browser
   ↓
Laravel Application
   ↓
Livewire / Blade / Flux UI
   ↓
Application Models (Eloquent)
   ↓
SQLite (Development)
```

### Key Components

- **Authentication**: Laravel Fortify (login, password reset, password confirmation)
- **Frontend**: Livewire 4 + Blade + Flux UI + Tailwind CSS 4
- **Database**: SQLite for development
- **Testing**: Pest
- **Build Tool**: Vite

### Domain Models

- **User** — Application users with roles (admin/user)
- **MediaFile** — Uploaded/source media assets
- **Transcription** — Transcription attempts/results associated with media
- **TranscriptionSegment** — Timestamped transcript chunks
- **ProcessingJob** — Processing lifecycle/stage information

### Important Design Decisions

1. MediaFile, Transcription, and ProcessingJob are intentionally separate models
2. One MediaFile may have multiple Transcription attempts in the future
3. Different models, languages, and processing jobs may apply to the same media
4. Phase 1 creates prototype domain data without real processing

## Future Architecture

```
Browser
   ↓
Laravel Application
   ↓
Queue (Redis / Horizon)
   ↓
Independent Transcription Worker
   ↓
faster-whisper (CPU + INT8 → GPU + CUDA + FP16)
   ↓
Database / Result Callback
```

### Future Deployment Target

```
Laravel App VPS
      ↓
Redis / Queue
      ↓
CPU or GPU Worker(s)
```

### Key Principles

- The transcription worker must eventually be independently deployable
- Never perform heavy transcription synchronously inside an HTTP request
- Laravel should not care which compute backend processes the transcription
- Future worker metrics: audio_duration_seconds, processing_seconds, model, worker, device, RTF

### Reconciled phase and worker boundaries

The repository phase numbering is canonical: Phase 3 is FFmpeg / FFprobe
media processing, Phase 4 is the independent faster-whisper worker, and Phase
5 is Laravel ↔ transcription-worker integration. This architecture section
does not authorize any of those phases.

ADR-002 establishes the high-level independent-worker direction. It does not
mean that the worker operational contract has already been decided. Before the
relevant Phase 4/5 execution gate, a separate approved decision is required
for transport, job schema, result schema, storage access, timeout, retry
semantics, heartbeat/cancellation where required, idempotency boundary, and
failure classification. Those contracts are deliberately not designed here.

The transcription-engine boundary is a thin replaceable architectural
principle. A multi-provider registry, provider routing, fallback engine,
complex capability registry, and broad normalized provider-error taxonomy are
future capabilities only; they require evidence from an actual second-provider
requirement before becoming current scope.

### Future ownership and production gates

The current Phase 2 ownership model remains owner-by-`user_id` with existing
admin behavior. It does not claim to solve future actor-versus-owner,
admin-on-behalf-of, collaboration, team ownership, or commercial
multi-tenancy. Those semantics require an explicit architecture/product gate
before implementation; no future-SaaS schema is introduced by this document.

Before Phase 3 media parsing, the media parsing and FFmpeg trust boundary must
be decided. Before worker/integration execution, the worker operational
contract and capacity/concurrency considerations must be decided. Before first
production use, the repository must define deployment, migration safety,
rollback, backup and restore verification, monitoring and failed-job
visibility, retention and deletion, derived-artifact deletion, log/privacy
behavior, and required legal/privacy validation. These are future gates, not
Phase 2 work.

## Phase 2 Ingestion Contract

Phase 2 introduces the contract for real media ingestion without introducing media processing or transcription.

```text
Browser
   ↓
Laravel upload boundary
   ↓
Temporary private staging storage
   ↓
Server validation and metadata derivation
   ↓
Opaque durable private storage
   ↓
MediaFile persistence
   ↓
Media Detail
```

The initial transport is single-file upload. Resumable or chunked transport is deferred and must not change the domain-level ingestion contract.

The accepted Phase 2 media matrix, single-file limit, no-duration-limit rule, duplicate policy, and post-upload destination are centralized in `config/media.php`. The application limit is exactly 500 MiB (524,288,000 bytes) per file, no duration limit is enforced, duplicates are allowed, and upload completion uses `MediaStatus::Uploaded`. `Processing` and `Ready` remain reserved for later media-processing semantics.

The durable storage identity is opaque and must not contain user identity or the original filename. The intended durable layout is `media/{media-uuid}/{opaque-filename-with-extension}` on the configured private disk; temporary files use the configured staging directory. The database stores metadata and a nullable, non-unique SHA-256 checksum for integrity and future duplicate detection. The server computes this checksum from the accepted media bytes and represents it as exactly 64 lowercase hexadecimal characters. Request-derived or client-supplied checksum values are not accepted; application code assigns the generated value through the `MediaFile` server-assignment boundary. Duplicate uploads remain allowed.

An abandoned staging artifact becomes eligible for cleanup after 24 hours. Filesystem promotion and database persistence must be coordinated explicitly because database transactions do not roll back filesystem operations. A completion retry must be tied to one upload attempt; intentional duplicate uploads remain allowed.

### P2-002B — Ingestion compensation and retry contract

The future upload workflow must treat an upload attempt as a logical operation
with one opaque `upload_attempt_id`. The upload boundary allocates one
identifier for each intentional upload; the client carries it and reuses it for
every completion request or response retry for that upload. Starting another
intentional upload, even with identical bytes or checksum, creates a new
identifier. A checksum is never an idempotency key.

The identifier is scoped to the authenticated media owner. A future successful
ingestion must persist the narrow association needed to look up the resulting
`MediaFile` by owner and attempt identifier (a unique owner/attempt key on the
successful record, or an equivalent narrow idempotency index). This is an
idempotency boundary, not a general upload-attempt state machine. Legacy
prototype rows may remain without the field; real ingestion records may not.

The durable object identity is allocated once per logical attempt and reused on
retry. It must remain opaque, private, and independent of the original filename
and user identity. The durable object or its metadata must carry enough stable
attempt identity for recovery to distinguish a retry of the same attempt from a
new intentional duplicate.

#### Failure and compensation outcomes

The workflow applies compensation only to resources created by the failing
attempt:

1. Validation or metadata derivation failure before promotion creates no
   `MediaFile`. The workflow releases staging best-effort; if the source remains
   available, the same attempt may retry.
2. Promotion failure creates no `MediaFile`. Any partially created durable
   object is removed best-effort. If removal cannot be confirmed, it becomes an
   orphan candidate for reconciliation; the workflow must not create another
   logical upload to compensate for it.
3. Promotion succeeds but `MediaFile` persistence fails. The workflow deletes
   the promoted object best-effort and leaves no misleading durable media row.
   A failed delete is an orphan candidate, not a reason to create a second
   durable object on retry.
4. Persistence succeeds but the response is lost or times out. The workflow
   performs no compensation. A retry first looks up the owner/attempt identity
   and returns the existing `MediaFile` and Media Detail target without another
   promotion or row.
5. If the database result is ambiguous, the workflow performs that same lookup
   before replaying promotion or persistence. A unique owner/attempt boundary is
   the final guard against two records for one logical attempt.

Compensation must never delete a durable object after its `MediaFile` row is
committed. Conversely, orphan recovery must never synthesize a `MediaFile` from
an uncommitted object, because doing so could create a misleading record.

#### Staging cleanup and orphan reconciliation

Staging artifacts remain eligible for cleanup after the existing 24-hour
retention period, but cleanup must atomically claim an artifact (or acquire the
workflow's equivalent short-lived lease) before deleting it. A retry claims the
same attempt before promotion; cleanup defers while that claim is active. A
cleanup/retry race therefore cannot delete a source currently being promoted.

If cleanup wins before a retry can claim staging, the retry reports that the
source must be re-staged under the same `upload_attempt_id`. It must not silently
mint a new attempt identifier or create a second logical upload.

Reconciliation is limited to opaque durable objects in the configured private
media area. For each candidate it:

- does nothing when the matching owner/attempt record and durable path exist;
- defers while the attempt has an active claim or is within the reconciliation
  grace period;
- deletes an unclaimed, unreferenced orphan after the grace period; and
- records/defer-retries a failed delete without creating a `MediaFile`.

Reconciliation is therefore safe for response retries, promotion failures, and
cleanup races while preserving intentional duplicates: different attempt
identifiers may produce separate `MediaFile` rows with the same checksum, but
one attempt identifier can produce at most one row and one durable object.

Phase 2 does not use FFprobe, FFmpeg, queues, Redis, Horizon, faster-whisper, or transcript generation.
