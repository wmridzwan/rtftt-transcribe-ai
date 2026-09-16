# RTFTT Transcribe AI — Current State

Last Updated: 2026-09-15

## Current Branch

setup/ai-development-os

## Current Authorized Phase

Phase 2 — Authorized remediation of P2-003/P2-004A/P2-004A1/P2-005 findings

Status: Phase 2 ACCEPTED by the Human Product Owner on 2026-09-13 under
ADR-014. P2-003, P2-005, and P2-004A2 are DONE. P2-004A and P2-004A1 remain BLOCKED and
are deferred out of the current Phase 2 completion scope under ADR-013; they
are not VERIFIED or DONE. P2-007 is VERIFIED (promoted from BACKLOG by Human
Product Owner on 2026-09-15). Phase 3 remains not authorized. Phase 1
ACCEPTED (Human Product Owner, 2026-09-11).

## Baseline Verification

Latest full test suite (2026-09-13, after remediation cycle 2):

- 216 passed
- 1 skipped
- 649 assertions

Remediation focused suite: 47 passed / 177 assertions. FFprobe was available,
so the real WAV probe test executed successfully. Pint passed. Full PHPStan: 0
errors. Frontend build passed. `git diff --check` passed.

Skipped test:

Two-factor authentication flow because the Fortify
two-factor-authentication feature is intentionally not enabled.

## Current Implementation

Phase 1 foundation currently includes:

- authentication
- admin/user roles
- authorization and ownership isolation
- MediaFile domain
- Transcription domain
- TranscriptionSegment domain
- ProcessingJob domain
- Folder domain
- dashboard
- transcription pages
- media pages
- processing job pages
- settings
- realistic demo data
- transcript export
- prototype transcription workflow

## Phase 2 Checkpoint Contracts

P2-001 and P2-002 are implemented and DONE after independent verification. The centralized contract is in `config/media.php`; the exact accepted media matrix supports MP3, WAV, M4A, AAC, FLAC, OGG, MP4, MOV, and WEBM with mapped server-inspected MIME values. The application limit is exactly 500 MiB (524,288,000 bytes) per file, uploads are single-file, duration is not enforced, duplicates are allowed, and successful upload is intended to open Media Detail without initiating transcription.

The ingestion contract is temporary staging → server validation → metadata derivation → opaque private storage promotion → MediaFile persistence. A nullable, non-unique SHA-256 checksum is available for integrity and future duplicate detection; the server-generated representation is exactly 64 lowercase hexadecimal characters and client-supplied values are not accepted. Temporary artifacts older than 24 hours are eligible for cleanup; the cleanup implementation is deferred to a later task. P2-002A now centralizes media storage access on the configured disk and rejects a public disk boundary at application boot and before media storage access.

P2-003's earlier regression re-review remains preserved as historical VERIFIED
evidence. The cycle-2 independent re-review verified the changed claim-upsert
surface, and P2-003 is now DONE. P2-005 is DONE after its independent VERIFIED
verdict in the cycle-2 re-review. P2-004A2 is DONE after independent
VERIFIED verdict (round 2, 2026-09-15). P2-004A and P2-004A1 are BLOCKED
after the third consecutive CHANGES_REQUESTED cycle because the SQLite
concurrency safety contract is not explicitly defined, repository-controlled,
or proven with genuine independent connections/processes. P2-007 is now
in REVIEW; Phase 3 remains not authorized.

## Phase 2 Infrastructure Readiness

The current PHP CLI configuration reports `upload_max_filesize=2M`, `post_max_size=8M`, `max_file_uploads=20`, `max_execution_time=0`, `max_input_time=-1`, and `memory_limit=128M`. The first two limits are below the approved 500 MiB (524,288,000-byte) per-file application limit. Before P2-003, the effective PHP/web-server/Livewire receiving path must be configured for at least the 500 MiB application boundary with overhead. No host configuration was changed by this checkpoint.

## Active Task

P2-004A and P2-004A1 are BLOCKED and deferred out of the current Phase 2
completion scope under ADR-013. P2-003, P2-005, and P2-004A2 are DONE. Phase 2 is
ACCEPTED under ADR-014. Future cleanup requires separate authorization and
genuine independent-concurrency verification.
P2-001A, P2-002A,
P2-002B, and P2-002C completed the authorized follow-up batch and were closed
as DONE after independent verification.

## Tasks In Review

P2-001A, P2-002A, P2-002B, and P2-002C are DONE after independent verification recorded in `reviews/P2-001A-P2-002A-P2-002B-P2-002C-independent-review.md` and `reviews/P2-002A-independent-re-review.md`. P2-001 and P2-002 remain DONE. P2-003, P2-005, and P2-004A2 are DONE. P2-004A and P2-004A1 are BLOCKED after escalation at the three-cycle threshold.

TASK-P1-STATIC-001/002/003/004, TASK-004C and TASK-P1-CREATE-001 are DONE after independent Claude verification.

## Ready Tasks

P2-004A2 (Staging Claim CAS Protocol) is DONE. Closed by Human Product
Owner on 2026-09-15 after independent VERIFIED verdict (round 2,
`reviews/P2-004A2-independent-review.md`). OpenCode resolved both round-1
BLOCKER findings and the HIGH finding: the `CleanupStaging` crash-recovery
re-claim now composes directly into the delete path instead of dead-ending
(reproduced directly: a claim stuck in `held_by='cleanup'` for 20 minutes
was fully deleted after one run); `MediaIngestionService::stage()`'s
same-attempt retry renewal now checks the guarded `UPDATE`'s affected-row
count and returns the controlled retryable failure on a lost race instead
of silently writing; and both genuine independent-OS-process race tests
(previously timing out) now pass consistently (8/8, re-run three times, no
flakiness) after fixing the test harness's shared-database wiring. Full
regression suite, Pint, and PHPStan were independently re-run and are
clean (229/230 passed, 1 pre-existing skip, 0 failures). All acceptance
criteria are satisfied; two non-blocking LOW notes remain (see review).
This closure does not lift the Option D deferral (ADR-013) or mark
P2-004A/P2-004A1 DONE — those remain Human Product Owner actions.
P2-007 (Phase Integration Verification) is VERIFIED at
`tasks/P2-007-phase-integration-verification.md`, promoted by Human Product
Owner on 2026-09-15. Implementation Owner: OpenCode, per AGENTS.md.
Independent review round 1 returned CHANGES_REQUESTED; corrections applied
and re-submitted. Phase 3 task is not eligible or authorized.

## P2-003 Active Task

P2-003 is DONE. The canonical contract is recorded at
`tasks/P2-003-implement-real-upload-ingestion-workflow.md`. The independent
third-pass review returned VERIFIED for the earlier revision on 2026-09-13.
The cycle-2 regression re-review returned VERIFIED and Work closed the task as
DONE. P2-004A2 is also DONE after independent VERIFIED verdict (round 2,
2026-09-15). No Phase 3 or later work is authorized.

## Latest Completed Follow-Ups

- TASK-004A — Restore Settings Active State: DONE.
- TASK-004B — Restore Internal Livewire Navigation: DONE.
- TASK-003A — Expand Query-Parameter Modal Coverage: DONE.

Implementation owner: Codex. Independently VERIFIED by Claude Code in reviews/PHASE1-FOLLOWUPS-review.md; individual review indexes are under reviews/. Closed by Codex orchestration under the user's explicit close-VERIFIED-work authorization. No findings in the new independent review.

PHP 8.4 is available at C:/Users/Admin/.config/herd/bin/php84/php.exe. The user explicitly authorized required repository source/tests/governance sharing with Claude Code/Anthropic on 2026-09-11. No production deployment, destructive operation or commit performed.

## Completed Tasks

TASK-000 - Multi-Agent Handoff Test (DONE).

Task: tasks/TASK-000-agent-handoff-test.md

Implementation Owner: Codex

Independently VERIFIED by Claude Code in reviews/TASK-000-review.md.

No outstanding findings.

Repository-artifact handoff test completed and closed after verification.

TASK-001 - Enforce Media-Folder Ownership (DONE).

Task: tasks/TASK-001-enforce-media-folder-ownership.md

Implementation Owner: Codex

Independently VERIFIED by Claude Code in reviews/TASK-001-review.md.

Both controller and Livewire move paths require a destination belonging to the media owner. Admin management within the media owner's folders and null/root moves are preserved. Independently re-verified: focused suite 40 passed/100 assertions; full suite 122 passed/1 pre-existing skip/0 failures (no regressions); Pint passed. No outstanding BLOCKER or HIGH findings. Closed as DONE under the user's explicit instruction after confirming the independent VERIFIED review. The review artifact is preserved unchanged.

TASK-002 - Align Media Lifecycle and Display Names (DONE).

Task: tasks/TASK-002-align-media-lifecycle-and-display-names.md

Implementation Owner: Codex

Independently VERIFIED by Claude Code in reviews/TASK-002-review.md.

Media display-name accessor now correctly returns the persisted value (independently forensically confirmed the old accessor silently always fell back to original_filename even when a custom name was persisted — a real, previously-undetected, app-wide bug now fixed). Controller and active Livewire deletion paths both require explicit `accepted` cascade confirmation when transcriptions exist, satisfying ADR-005; the DB-level cascade chain (MediaFile -> Transcription -> TranscriptionSegment/ProcessingJob) was confirmed intact via migration inspection. Independently re-verified: focused suite 28 passed/89 assertions; full suite 129 passed/1 pre-existing skip/0 failures; Pint passed. One MEDIUM, non-blocking finding was preserved and resolved by TASK-002A: Livewire's destroy() now re-queries instead of checking a stale relation. Closed as DONE after confirming the independent VERIFIED review; follow-up TASK-002A is also DONE.

TASK-002A - Refresh Livewire Deletion State Before Cascade Confirmation (DONE).

Task: tasks/TASK-002A-livewire-deletion-race-window.md

Implementation Owner: Codex

Independently VERIFIED by Claude Code in reviews/TASK-002A-review.md.

Closes Finding 1 from reviews/TASK-002-review.md: Livewire's destroy() now checks `$this->mediaFile->transcriptions()->exists()` (a fresh query) instead of the relation collection cached at mount time. A new regression test creates a transcription after mount and before deletion and confirms cascade confirmation is now correctly required; independently traced and confirmed this test would have failed against the pre-fix code. Independently re-verified: focused suite 29 passed/93 assertions; full suite 130 passed/1 pre-existing skip/0 failures; Pint passed. One LOW, non-blocking documentation finding is preserved: the task's static-analysis checkbox overstated the PHPStan state; PHPStan shows only the same two pre-existing, unrelated issues documented in the Phase 1 baseline. Closed as DONE after confirming the independent VERIFIED review; the review artifact is preserved.

TASK-003 - Complete Prototype Action Wiring (DONE).

Task: tasks/TASK-003-complete-prototype-action-wiring.md

Implementation Owner: Codex

Independently VERIFIED by Claude Code in reviews/TASK-003-review.md.

Confirmed the previously dead media-list menu actions (Rename/Move/Delete dispatched a non-existent `openModal` event) and the dead transcription-list Rename link (`#rename` fragment with no listener) are now wired to their existing modal states via query parameters (`media.show?action=...`, `transcriptions.show?rename=1`) consumed by `Show::mount()` and the existing Alpine state respectively. Added a session-flash success banner to `sidebar.blade.php`, confirmed as the real live application shell (`layouts/app.blade.php` wraps every authenticated page in it), surfacing plain-controller redirect feedback that Livewire's toast system cannot catch. Independently re-verified: focused suite 41 passed/123 assertions; full suite 134 passed/1 pre-existing skip/0 failures; Pint passed; no new PHPStan findings. One LOW, non-blocking finding is preserved: the Livewire query-param test only directly covers the `rename` branch, not `move`/`delete` (structurally identical, low risk). Closed as DONE after confirming the independent VERIFIED review; the review artifact is preserved.

TASK-004 - Complete Navigation and Action Availability (DONE).

Task: tasks/TASK-004-complete-navigation-and-action-availability.md

Implementation Owner: Codex

Independently VERIFIED by Claude Code in reviews/TASK-004-review.md.

Role-aware processing-job links now hide dead-end admin-only links from non-admins (confirmed `jobs.*` routes are admin-gated, pre-existing). Export controls now gate on `TranscriptionStatus::Completed`, matching the controller's own pre-existing authorization rule exactly (the old `$hasFile`-based check was verified to be checking the wrong signal entirely, since exports never touch the physical file). `wire:navigate` removed from export links so real file downloads work. Media Download hidden/disabled when `Storage::exists()` is false — verified this is not theoretical: the seeder never writes real files for any seeded MediaFile, so every Download button in current demo data was a guaranteed 404 before this fix. Folders added to sidebar navigation with a Create Folder path in the empty state (reusing the existing modal, no new modal system). Settings navigation now reaches `/settings`; Profile/Security/Appearance reachability confirmed via the settings overview and the existing in-page settings sub-nav. Dead views/controllers (`FolderController::index`, `MediaController::show`, `folders/index.blade.php`, `media/show.blade.php`, `layouts/app/header.blade.php`) removed — independently proven safe via unchanged routes/web.php and a full-tree reference grep. Independently re-verified: focused suite 69 passed/208 assertions; full suite 140 passed/1 pre-existing skip/0 failures; Pint passed; PHPStan 0 errors on both changed controllers. One MEDIUM, non-blocking finding: the sidebar Settings item's "current" highlighting no longer covers Profile/Security/Appearance subpages (reachability unaffected). Two LOW, non-blocking findings: an undisclosed file change (`desktop-user-menu.blade.php`) and two `wire:navigate` removals outside the stated file-response scope. Closed as DONE after confirming the independent VERIFIED review; follow-up findings are tracked in READY TASK-004A and TASK-004B.

P2-003 - Implement Real Upload & Ingestion Workflow (DONE).

Task: tasks/P2-003-implement-real-upload-ingestion-workflow.md

Implementation Owner: Codex

Independently VERIFIED by Claude Code in reviews/P2-003-independent-review.md (third pass, 2026-09-13).

Implements the first real end-to-end single-file media upload and ingestion workflow: Upload UI → web receiving path → temporary staging → server validation → metadata derivation without media probing → private opaque storage promotion → MediaFile persistence → Media Detail. Uses a normal Laravel multipart endpoint with Blade/browser progress (not Livewire raw-file transport). Enforces the exact 500 MiB (524,288,000 bytes) per-file boundary. Implements P2-002B upload-attempt identity, retry, ambiguity, and compensation behavior. Independently re-verified: focused suite 16 passed/94 assertions; full suite 185 passed/12 skipped/566 assertions; Pint passed; PHPStan 0 errors; frontend build passed. Underwent two CHANGES_REQUESTED remediation cycles for test-coverage gaps (persistence-failure compensation and ambiguous duplicate-key retry tests rebuilt to exercise service-level branches); both resolved and verified on third pass. Closed as DONE after confirming the independent VERIFIED review. No Phase 3 or later work was introduced.

P2-004A2 — Staging Claim CAS Protocol (DONE).

Task: tasks/P2-004A2-staging-claim-cas-protocol.md

Implementation Owner: OpenCode

Independently VERIFIED by Claude Code in reviews/P2-004A2-independent-review.md (round 2, 2026-09-15). Closed as DONE by Human Product Owner on 2026-09-15.

Replaces the unproven `lockForUpdate()`-based claim protocol with a SQLite-safe compare-and-set protocol using guarded `UPDATE`/`INSERT ... OR IGNORE` statements. Implements crash-recovery re-claim (15-minute timeout window), controlled retryable failure when ingestion loses the race to cleanup, and file deletion outside any database transaction. Proves race safety with genuine independent OS processes using Symfony Process + shared file-based SQLite DB + filesystem rendezvous. Independently re-verified: CleanupStagingCommandTest 14/14 passed, StagingClaimCasProtocolTest 8/8 passed (both genuine OS-process race tests pass), IngestionCompensationContractTest 11/11 passed; full suite 229/230 passed (1 pre-existing skip, 0 failures); Pint passed; PHPStan 0 errors. This closure does not lift Option D (ADR-013) or mark P2-004A/P2-004A1 DONE. No Phase 3 or later work was introduced.

P2-006 - Failure / Retry Handling (CLOSED AS ALREADY COVERED).

Task: `tasks/P2-006-failure-retry-handling.md`

No implementation was required. P2-002B defines the failure/retry contract,
and P2-003 independently verifies same-attempt idempotency, cross-user
isolation, promotion and persistence compensation, ambiguous retry recovery,
staging cleanup, and the absence of processing/transcription side effects.
The remaining out-of-band staging cleanup/lease work remains blocked under
P2-004A/P2-004A1, and P2-005 is DONE after independent verification. P2-007
is READY. Phase 3 remains unauthorized.

## Blocked Tasks

- P2-004A — BLOCKED: SQLite cleanup/ingestion concurrency contract unresolved;
  third consecutive CHANGES_REQUESTED cycle.
- P2-004A1 — BLOCKED: SQLite cleanup/ingestion concurrency contract unresolved;
  third consecutive CHANGES_REQUESTED cycle.

## Governance Setup

The canonical agent operating model is the two-agent model (OpenCode as
Builder, Claude Code as Independent Reviewer, Human Product Owner as
Decider) defined in `AGENTS.md`, `.ai/guidelines/orchestration-policy.md`,
and `.ai/guidelines/ai-development-os.md`, accepted under **ADR-015**
(DECISIONS.md). ADR-015 supersedes ADR-010's five-role model (Work, OpenCode,
Claude Code, Codex, Human Product Owner) in full; the dedicated Work
orchestration layer and the Codex investigate/secondary-implementation role
are removed. ADR-010 in turn superseded the agent-role/orchestration portion
of ADR-004, whose repository-handoff principles remain preserved and
authoritative through ADR-015.

The orchestration policy is at `.ai/guidelines/orchestration-policy.md`; its
original independent verification is recorded in
`reviews/ORCHESTRATION-GOVERNANCE-review.md`, and the ADR-010 reconciliation
is independently VERIFIED in
`reviews/GOVERNANCE-RECONCILIATION-ADR010-review.md`. The LOW traceability
finding from that reconciliation is resolved by retaining both review
pointers here. Historical Phase 1 and Phase 2 task records correctly
reference execution under the previous ADR-010 model (Codex as an
implementation owner for several tasks, alongside OpenCode); those records
are preserved as historical truth and are not retroactively changed by
ADR-015.

## Governance Reconciliation

ADR-011 records the approved RTFTT / Voxora governance reconciliation. Voxora
is the long-term product and brand; RTFTT remains the current
engineering/repository identity. The repository-native authority model remains
controlling, with `AGENTS.md` as the operational entry point and
`.ai/guidelines/orchestration-policy.md` as the one canonical State-to-Action
Contract. The external governance suite is strategic/planning input until its
individual artifacts are explicitly reconciled and published; it does not
authorize implementation by itself.

Established Phase 1–7 numbering is preserved. Phase 3 remains FFmpeg/FFprobe
media processing, Phase 4 remains the independent faster-whisper worker, and
Phase 5 remains Laravel ↔ worker integration. Worker transport and operational
contract details, provider expansion, future ownership/multi-tenancy, media
parsing trust boundaries, and production/privacy requirements remain future
gates. No application, schema, test, route, configuration, or Phase 2 scope
change is authorized by this reconciliation.

## Governance Reconciliation Closure

Status: VERIFIED / DONE

The independent governance reconciliation review returned VERIFIED on
2026-09-13. Closure is limited to the documentation and governance records
listed in `reviews/GOVERNANCE-RECONCILIATION-ADR011-review.md`. It does not
authorize P2-003, Phase 3, product implementation, schema changes, runtime
changes, or any later phase.

## Decisions Required

DECISION-P1-001 resolved as Title REQUIRED; see ADR-006 in DECISIONS.md. TASK-P1-CREATE-001 is DONE. DECISION-P2-CONCURRENCY-001 is DECIDED as Option D under ADR-013. DECISION-P2-PHASE2-ACCEPTANCE-001 is DECIDED under ADR-014; Phase 2 is ACCEPTED. DECISION-P2-CONCURRENCY-002 is DECIDED as Option 1 under ADR-016; P2-004A2 is DONE. Option D remains in force until the Human Product Owner decides to lift it.

## Known Issues

Previous 59-error PHPStan baseline has been repaired; full analysis now reports 0 errors. ADR-010 governance reconciliation is closed after resolving its non-blocking LOW traceability finding. P2-003 and P2-005 are DONE; P2-004A and P2-004A1 are BLOCKED under the three-cycle escalation policy. P2-006 remains closure-only. P2-007 is VERIFIED. Phase 3 remains unauthorized.

## Future Gates

The following are not current blockers, but must be decided before their
respective future gates: media parsing/FFmpeg trust boundary; worker transport,
job/result schemas, storage, timeout, retry, heartbeat/cancellation,
idempotency, failure classification, and capacity; actor-versus-owner and
multi-tenancy semantics; and first-production-use deployment, rollback,
backup/restore, monitoring, failed-job visibility, retention/deletion,
derived-artifact deletion, log/privacy, and legal/privacy validation.

## Next Action

Phase 2 acceptance has been recorded under ADR-014. P2-004A and P2-004A1
remain BLOCKED and deferred from the current gate under ADR-013. P2-004A2 is
DONE. P2-007 is VERIFIED (promoted 2026-09-15). P2-006 remains closure-only.
Do not begin Phase 3 without separate Human Product Owner authorization.

## Phase Authorization

The Human Product Owner accepted the bounded Phase 2 scope in ADR-014 on
2026-09-13. P2-003, P2-005, and P2-004A2 are DONE; P2-004A and P2-004A1 are
BLOCKED and deferred from the current gate under ADR-013. P2-007 is VERIFIED
(promoted 2026-09-15). All processing/transcription
phases remain unauthorized.

See plan.md.

## Review status

Independent review verified the ADR-010 governance reconciliation, P2-001/P2-002 checkpoint, and earlier follow-ups. P2-001A, P2-002A, P2-002B, and P2-002C are DONE. TASK-P1-STATIC-001/002/003/004, TASK-004C and TASK-P1-CREATE-001 are DONE. DECISION-P1-001 is resolved by ADR-006. The cycle-2 independent review is preserved: P2-003, P2-005, and P2-004A2 are DONE; P2-004A and P2-004A1 are BLOCKED and deferred from the current gate under ADR-013. P2-007 is VERIFIED (promoted 2026-09-15). P2-006 remains closure-only.
