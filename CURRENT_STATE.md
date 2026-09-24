# RTFTT Transcribe AI — Current State

Last Updated: 2026-09-24

## Current Branch

setup/ai-development-os

## Current Authorized Phase

Phase 2 — COMPLETE_WITH_DEFERRED_DEBT

Phase 3 — CLOSED (2026-09-19)

Phase 4 = CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001). P4-001..P4-006 = DONE
(P4-004 corrective cycle DECISION-P4-004-CORRECTIVE-CLOSURE-001; P4-006
DECISION-P4-006-CLOSURE-001; DECISION-P4-006-FINDING-001 CLOSED).

Phase 5 = CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001). All P5 tasks DONE
(P5-001, P5-001A, P5-002, P5-002A, P5-002B, P5-003, P5-004, P5-004B, P5-004C,
P5-005, P5-006, P5-007, P5-008). P5-008 was independently VERIFIED by a fresh
review (`reviews/P5-008-independent-review.md`; no BLOCKER/HIGH/MEDIUM) and
closed DONE (`DECISION-P5-008-CLOSURE-001`). Corrective provenance:
`DECISION-P5-008-CORRECTIVE-001`; ADR-024 (canonical runtime
`transformers==5.17.0`/`torch==2.14.0`/`sentencepiece==0.2.2`; canonical model
`facebook/nllb-200-distilled-600M`). Real-model, real-Redis, and real
browser-to-real-model E2E evidence: `PHASE5-P5-008-INTEGRATION-EVIDENCE.md`;
runbook `verification/p5-008/README.md`. Quality: worker 46, translation 193,
full suite 626/625, Pint clean, PHPStan 0. Final closure report:
`PHASE5-CLOSURE-REPORT.md`. LOW/INFO debt carried non-blocking
(`DECISION-PHASE5-DEBT-CARRYFORWARD-001`). Phase 3/4 baseline committed
(B-001/B-002 resolved); working tree clean.

Phase 6 = AUTHORIZED FOR CONTRACT AUTHORING + IMPLEMENTATION (2026-09-23,
`DECISION-PHASE6-AUTHORIZATION-001`; D6-01..D6-09 + DC-01 adopted via
`DECISION-PHASE6-OWNER-DECISIONS-001`; ADR-025). No blanket READY; each task is
promoted only after its contract + dependencies are reconciled. Current state:
P6-001 = DONE (independently VERIFIED corrective re-review; closed
`DECISION-P6-001-CLOSURE-001`; final domain semantics frozen in
`PHASE6-EDITING-DOMAIN-CONTRACT.md`); P6-006 = DONE
(`DECISION-P6-006-CLOSURE-001`); P6-002 = DONE (promoted READY
`DECISION-P6-002-READY-001`; implemented; corrective cycle resolved the original
MEDIUM strict-ancestor undo finding; fresh independent corrective re-review
VERIFIED; closed `DECISION-P6-002-CLOSURE-001`). The P6-001/P6-002 foundation is
now frozen downstream input. P6-003 canonical contract is authored
(`tasks/P6-003-text-editing-undo-redo.md`) and was explicitly promoted to READY
by the HPO (batch `DECISION-P6-003-P6-007-READY-BATCH-001`); it was implemented,
independently reviewed VERIFIED with no remaining BLOCKER/HIGH/MEDIUM finding
(`reviews/P6-003-P6-007-independent-review.md`), and closed **DONE**
(`DECISION-P6-003-CLOSURE-001`). P6-007 canonical contract is authored
(`tasks/P6-007-source-translation-comparison.md`; under
`DECISION-P6-007-SCOPE-001`, presentation-only) and was promoted READY by the
same batch decision; it was implemented, reviewed CHANGES_REQUESTED for one
MEDIUM presentation-truthfulness finding, corrected (the per-row "edited after
the translation was produced" note is now gated on a persisted translation
existing for that row), confirmed closed by a fresh independent corrective
re-review (VERIFIED; no remaining BLOCKER/HIGH/MEDIUM/LOW/INFO), and closed
**DONE** by the HPO (`DECISION-P6-007-CLOSURE-001`). P6-004's canonical contract
is authored (`tasks/P6-004-timing-editing-validation.md`) and was promoted READY
by the HPO (`DECISION-P6-004-READY-001`); it is now implemented against the
frozen P6-001 D6-03 timing invariants (timing-only, append-only revision edits;
`EditKind::Timing` → `TimingChanged` with no staleness persistence; machine
timing immutable; playback/navigation driven by active-revision timing) with unit
and feature tests, a 10/10 real-browser (DC-01) suite, and a durable pre-review
artifact, and is `IMPLEMENTED_PENDING_REVIEW` awaiting a fresh independent review.
P6-005 remains dependency-blocked until P6-004 is DONE. The missing
`reviews/P6-002-corrective-independent-re-review.md` record-completeness gap is
retained and P6-002 is not reopened. Phase 7 remains NOT
GENERALLY AUTHORIZED; only P7-005 (Observability Foundation) was authorized early
(`DECISION-P7-005-AUTHORIZATION-001`) and is DONE
(`DECISION-P7-005-CLOSURE-001`). P6-005/P6-008/P6-009 and all other Phase 7
work were not started. Remaining Phase 6 candidate eligibility and the next batch
recommendation: `PHASE6-7-ELIGIBILITY-MATRIX.md` §R.

Phase 7 remains NOT GENERALLY AUTHORIZED (ADR-023;
`PHASE5-7-EXECUTION-CLASSIFICATION.md`); early-hardening runs only where contracts
prove independence; Phase 7 cannot close before Phase 6; P7-012 remains
FINAL_GATE_ONLY. No phase closes automatically.

Phase 3 Batch 1 = COMPLETE / CLOSED

P3-001 = DONE
P3-002 = DONE
P3-003 = DONE

Final independent verification = VERIFIED

Canonical model = large-v3
Turbo = non-default / experimental

Benchmark decision = DECIDED
Escalation decision = DECIDED

H5 = resolved
H6 = resolved by final HPO model decision

Phase 3 Batch 2 = CLOSED

P3-004 = DONE
P3-005 = DONE
P3-006 = DONE

Batch 2 independent review = VERIFIED (P3-006 HIGH-1 resolved via Correction
Cycle 1 re-review)
Batch 3 = CLOSED (2026-09-19)
P3-007 = DONE
P3-008 = DONE

Phase 3 = CLOSED (2026-09-19)
Phase 4 = NOT AUTHORIZED

Status: Phase 2 closed as COMPLETE_WITH_DEFERRED_DEBT by the Human Product
Owner on 2026-09-17. Phase 3 Batch 1 was authorized by HPO on 2026-09-17
(DECISION-P3-BATCH1-001), reviewed across multiple cycles, remediated, and
accepted/closed as DONE on 2026-09-18 with canonical model **large-v3**.
Phase 3 Batch 2 was authorized by the HPO on 2026-09-18
(DECISION-P3-BATCH2-001) for P3-004/P3-005/P3-006. One independent Claude
Batch 2 review returned P3-004 = VERIFIED, P3-005 = VERIFIED, P3-006 =
CHANGES_REQUESTED (HIGH-1: genuine independent-process concurrency evidence
for the `ProcessingJob` CAS claim), Batch 2 overall = CHANGES_REQUESTED.
Correction Cycle 1 (P3-006 only) added a real two-independent-process SQLite
race test; the focused independent re-review returned P3-006 = VERIFIED,
Batch 2 overall = VERIFIED, HIGH-1 = RESOLVED. The HPO closed all three tasks
VERIFIED → DONE and recorded Phase 3 Batch 2 = CLOSED on 2026-09-19
(DECISION-P3-BATCH2-CLOSURE-001). Phase 3 Batch 3 (P3-007/P3-008) was
authorized by the HPO on 2026-09-19 (DECISION-P3-BATCH3-001) after resolving
owner decisions B3-01 through B3-07 (ADR-018); P3-007 was implemented,
independently VERIFIED, and closed as DONE on 2026-09-19
(DECISION-P3-007-CLOSURE-001). P3-008 integration verification was executed on
2026-09-19 (live Redis and real faster-whisper `large-v3` gates passed),
independently VERIFIED, and closed as DONE on 2026-09-19
(DECISION-P3-008-CLOSURE-001). The HPO recorded Phase 3 Batch 3 = CLOSED
(DECISION-P3-BATCH3-CLOSURE-001), then closed Phase 3 as a whole on 2026-09-19
(DECISION-PHASE3-CLOSURE-001). Phase 3 = CLOSED. Phase 4 is reconciled by
ADR-019; contracts P4-001..P4-006 were authored and audited, and P4-001 was
authorized for implementation and promoted BACKLOG → READY
(DECISION-P4-001-AUTHORIZATION-001). P4-001 implementation is complete and was
independently reviewed on 2026-09-19 (`reviews/P4-001-independent-review.md`):
VERIFIED, no BLOCKER/HIGH/MEDIUM findings (LOW-1/LOW-2 non-blocking), and closed
as DONE by the HPO (DECISION-P4-001-CLOSURE-001). Wave 1 (P4-002, P4-004, P4-005) was then
authorized (DECISION-P4-WAVE1-AUTHORIZATION-001), implemented, independently
VERIFIED, and closed as DONE (DECISION-P4-002-CLOSURE-001,
DECISION-P4-004-CLOSURE-001, DECISION-P4-005-CLOSURE-001); Phase 4 Wave 1 =
CLOSED. The browser-verification strategy is resolved
(DECISION-P4-BROWSER-VERIFICATION-001), and P4-003 was authorized and promoted
READY (DECISION-P4-003-AUTHORIZATION-001); it was implemented, independently
VERIFIED, and closed as DONE (DECISION-P4-003-CLOSURE-001). P4-006 was
authorized (DECISION-P4-006-AUTHORIZATION-001) and executed; its final
integration gate FAILED on V4-14 and V4-18, a real-browser defect in the frozen
P4-004 `transcriptSearch` component (`this.$el` resolves to the event target in
child-element handlers). The HPO authorized a P4-004 corrective reopen
(DECISION-P4-004-REOPEN-001); the correction was independently re-verified
(`reviews/P4-004-corrective-independent-re-review.md`) and P4-004 was re-closed
DONE (DECISION-P4-004-CORRECTIVE-CLOSURE-001). The P4-006 finding is CLOSED
(DECISION-P4-006-FINDING-001), and P4-006 was released to READY and re-executed;
the fresh final-gate rerun PASSED all mandatory V4-01..V4-25 items
(`P4-006-INTEGRATION-VERIFICATION-RERUN-EVIDENCE.md`), and the independent
review (`reviews/P4-006-independent-review.md`) returned VERIFIED (no
BLOCKER/HIGH/MEDIUM; LOW-1/INFO-1 non-blocking). P4-006 was then closed DONE
(DECISION-P4-006-CLOSURE-001), completing the Phase 4 task gate; Phase 4 was
closed by the HPO (DECISION-PHASE4-CLOSURE-001, 2026-09-20). Residual LOW/INFO
debt is retained (`reviews/PHASE4-final-closure.md`); Phase 4 is not represented
as defect-free. Phase 5 = CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001).
Option D remains in force. Phase 1 ACCEPTED (Human Product Owner, 2026-09-11).

## Baseline Verification

Latest full test suite (2026-09-17, Phase 2 closure):

- 229 passed
- 1 skipped
- 688 assertions

Focused upload-related suite: 25 passed / 83 assertions. FFprobe was available.
Pint passed. Full PHPStan: 0 errors. Frontend build passed.

Batch 2 verification (2026-09-18, incl. P3-006 Correction Cycle 1):

- Full PHP suite: 334 total, 333 passed, 1 skipped, 1028 assertions, 2 warnings
  (the skip and warnings are the pre-existing Batch 1 baseline).
- Focused Batch 2 suites: TranscriptPersistence 9, SegmentPersistence 7,
  AtomicCompletion 2, ProcessTranscriptionJob 13,
  TranscriptionQueueOrchestration 9, ProcessTranscriptionPayload 2,
  TranscriptionClaimConcurrency 1 (genuine two-process SQLite race).
- Pint clean; PHPStan 0 errors (run with `--memory-limit=1G`; the local PHP
  CLI `memory_limit=128M` crashes PHPStan's parallel worker).
- Python worker untouched; worker suite re-run for regression: 33 passed.
- Queue backend exercised: Laravel `database` queue with real serialization +
  `queue:work --once`; `Queue::fake` for dispatch assertions; Redis-outage
  path exercised against an unavailable endpoint. Real working Redis was not
  available in this environment.

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
or proven with genuine independent connections/processes. P2-007 is
DONE (closed 2026-09-17). Phase 3 = CLOSED (2026-09-19; Batch 1/Batch 2/Batch 3
closed, P3-007 DONE; P3-008 DONE).

## Phase 2 Infrastructure Readiness

The current PHP CLI configuration reports `upload_max_filesize=2M`, `post_max_size=8M`, `max_file_uploads=20`, `max_execution_time=0`, `max_input_time=-1`, and `memory_limit=128M`. The first two limits are below the approved 500 MiB (524,288,000-byte) per-file application limit. Before P2-003, the effective PHP/web-server/Livewire receiving path must be configured for at least the 500 MiB application boundary with overhead. No host configuration was changed by this checkpoint.

## Active Task

Phase 3 Batch 1 (P3-001, P3-002, P3-003) is COMPLETE / CLOSED.

History: implementation completed 2026-09-17; three consecutive
CHANGES_REQUESTED cycles triggered HPO escalation; HPO authorized narrow H6
remediation while Batch 1 remained BLOCKED; H5 confirmed fixed; H6 expanded
benchmark completed (36 samples, both models); post-escalation review found a
script-detector defect; corrected analysis showed turbo corruption on 3/15
real Tamil samples; HPO final decision selected **large-v3** as the initial
canonical Phase 3 model (turbo = non-default/experimental); the Final
HPO-Decision Verification returned VERIFIED; HPO accepted and closed Batch 1
on 2026-09-18.

Canonical transition: BLOCKED → (HPO closure decision; prerequisite: Final
HPO-Decision Verification = VERIFIED) → DONE. No implementation change was
authorized by closure.

Phase 3 Batch 2 authorized by the HPO on 2026-09-18
(DECISION-P3-BATCH2-001): P3-004 (Transcript Persistence), P3-005 (Segment
Persistence + Atomic Completion), P3-006 (Redis Queue Orchestration +
Idempotent Delivery). P3-004/P3-005/P3-006 were promoted to READY, implemented
sequentially by OpenCode, independently reviewed, corrected (P3-006 Correction
Cycle 1), re-reviewed, and closed as DONE by the HPO on 2026-09-19
(DECISION-P3-BATCH2-CLOSURE-001). Phase 3 Batch 2 = CLOSED. Batch 3 was
subsequently authorized on 2026-09-19 (DECISION-P3-BATCH3-001; ADR-018) with
P3-007/P3-008 promoted to READY. Option D remains in force.
P2-001A, P2-002A,
P2-002B, and P2-002C completed the authorized follow-up batch and were closed
as DONE after independent verification.

## Tasks In Review

P2-001A, P2-002A, P2-002B, and P2-002C are DONE after independent verification recorded in `reviews/P2-001A-P2-002A-P2-002B-P2-002C-independent-review.md` and `reviews/P2-002A-independent-re-review.md`. P2-001 and P2-002 remain DONE. P2-003, P2-005, and P2-004A2 are DONE. P2-004A and P2-004A1 are BLOCKED after escalation at the three-cycle threshold.

TASK-P1-STATIC-001/002/003/004, TASK-004C and TASK-P1-CREATE-001 are DONE after independent Claude verification.

## Ready Tasks

Phase 3 Batch 1 tasks are DONE (closed by HPO 2026-09-18):
- P3-001 (Transcription Domain Contract) — DONE
- P3-002 (Provider + Worker Transport Contract) — DONE
- P3-003 (Real Internal Provider) — DONE

Review verdict history (preserved in full):
Cycle 1 = CHANGES_REQUESTED
Cycle 2 = CHANGES_REQUESTED
Cycle 3 = CHANGES_REQUESTED — escalation triggered
Cycle 4 = CHANGES_REQUESTED — post-escalation governance review
Post-Escalation Independent Review = CHANGES_REQUESTED
Final HPO-Decision Verification = VERIFIED

Phase 3 Batch 2 tasks are DONE (closed by HPO 2026-09-19; independent review
VERIFIED, P3-006 HIGH-1 resolved via Correction Cycle 1):
- P3-004 (Transcript Persistence) — DONE
- P3-005 (Segment Persistence + Atomic Completion) — DONE
- P3-006 (Redis Queue Orchestration + Idempotent Delivery) — DONE

P3-007 is DONE (HPO closure 2026-09-19, DECISION-P3-007-CLOSURE-001).
P3-008 is DONE (HPO closure 2026-09-19, DECISION-P3-008-CLOSURE-001).
Phase 3 Batch 3 = CLOSED (DECISION-P3-BATCH3-CLOSURE-001).
Option D remains in force.

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
is DONE (closed 2026-09-17). Phase 3 = CLOSED (2026-09-19; Batch 1/Batch 2/Batch 3
closed, P3-007 DONE; P3-008 DONE).

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

Phase 3 boundary amended by ADR-017 (2026-09-17): Phase 3 is the complete
Real Transcription Engine (transcription domain, provider, internal Python
worker, FFmpeg, faster-whisper, Redis queue, transcript/segment persistence,
multilingual support, retry/recovery, integration verification). The earlier
Phase 3/4/5 decomposition is superseded. Future roadmap phases (6, 7) and
their boundaries remain to be reconciled after Phase 3 approval. No
application, schema, test, route, configuration, or Phase 2 scope change is
authorized by this reconciliation.

## Governance Reconciliation Closure

Status: VERIFIED / DONE

The independent governance reconciliation review returned VERIFIED on
2026-09-13. Closure is limited to the documentation and governance records
listed in `reviews/GOVERNANCE-RECONCILIATION-ADR011-review.md`. It does not
authorize P2-003, Phase 3, product implementation, schema changes, runtime
changes, or any later phase.

## Decisions Required

DECISION-P3-ESCALATION-001 = DECIDED (2026-09-18). HPO exception:
Batch 1 BLOCKED, narrow H6 remediation authorized. Escalation outcome:
Accept with known limitations (large-v3). DECISION-P3-BENCHMARK-GATE-001
= DECIDED — large-v3 selected as initial canonical Phase 3 model; turbo is
non-default/experimental. No further model-selection gate is required for
Batch 1. DECISION-P3-BATCH1-001 DECIDED. DECISION-P3-BATCH2-001 DECIDED —
Phase 3 Batch 2 authorized (P3-004/P3-005/P3-006); Batch 3 authorized 2026-09-19
(DECISION-P3-BATCH3-001); B3-01 through B3-07 DECIDED (ADR-018).
DECISION-P1-001 resolved as Title REQUIRED. DECISION-P2-CONCURRENCY-001
is DECIDED as Option D. DECISION-P2-PHASE2-ACCEPTANCE-001 is DECIDED.
DECISION-P2-CONCURRENCY-002 is DECIDED. Option D remains in force.
P2-004A/P2-004A1 remain BLOCKED.

## Known Issues

Previous 59-error PHPStan baseline has been repaired; full analysis now reports 0 errors. ADR-010 governance reconciliation is closed after resolving its non-blocking LOW traceability finding. P2-003 and P2-005 are DONE; P2-004A and P2-004A1 are BLOCKED under the three-cycle escalation policy. P2-006 remains closure-only. P2-004A2 and P2-007 are DONE. Phase 2 is closed as COMPLETE_WITH_DEFERRED_DEBT. Phase 3 Batch 1 is COMPLETE / CLOSED (P3-001/P3-002/P3-003 DONE); canonical model large-v3. Phase 3 Batch 2 is CLOSED (P3-004/P3-005/P3-006 DONE; independent review VERIFIED, P3-006 HIGH-1 resolved via Correction Cycle 1). Batch 3 is CLOSED (2026-09-19, DECISION-P3-BATCH3-CLOSURE-001); P3-007 and P3-008 are DONE (DECISION-P3-007-CLOSURE-001, DECISION-P3-008-CLOSURE-001). Phase 3 = CLOSED (2026-09-19, DECISION-PHASE3-CLOSURE-001). Phase 4 task contracts are authored; P4-001 = DONE (DECISION-P4-001-CLOSURE-001); Wave 1 CLOSED (P4-002/P4-005 = DONE); P4-004 = DONE (corrective cycle, DECISION-P4-004-CORRECTIVE-CLOSURE-001); P4-003 = DONE (DECISION-P4-003-CLOSURE-001); P4-006 = DONE (DECISION-P4-006-CLOSURE-001); Phase 4 = CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); Phase 5 = CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001).

Non-blocking Wave 1 suite observation: the full PHP suite repeatedly passed at 422 total / 421 passed / 1 pre-existing skip / 0 failures, while the assertion count varied between 1400 and 1402 across independent runs. This variation is not attributed to Wave 1 and does not warrant a correction cycle.

## Phase 3 Batch 1 Non-Blocking Follow-Up Debt

These LOW/INFO findings from the Final HPO-Decision Verification are tracked
as non-blocking debt. They do not prevent Batch 1 closure and are NOT promoted
into Batch 2 scope.

- Malformed-2xx `HttpTranscriptionProvider` response coverage residual.
- Unused `requested_language` parameter in the segment-language helper.
- `MediaAccessError` safe-message asymmetry.
- P3-002 real end-to-end bearer-auth residual.
- Raw benchmark results are git-ignored (reproducible via committed tooling,
  not directly committed).
- Two test warnings.

## Future Gates

The following are not current blockers, but must be decided before their
respective future gates: actor-versus-owner and multi-tenancy semantics; and
first-production-use deployment, rollback, backup/restore, monitoring,
failed-job visibility, retention/deletion, derived-artifact deletion,
log/privacy, and legal/privacy validation. The Phase 3 model-selection gate is
closed for Batch 1 (large-v3, DECIDED). Naturalistic code-switch quality
verification remains a P3-008 requirement.

## Phase 2 Deferred Debt Register

### Historical / cleanup debt

- P2-004A / P2-004A1 historical BLOCKED state under ADR-013.
- Automated abandoned-staging cleanup remains deferred while Option D stays in force.

### P2-004A2 test-coverage debt

- No direct test currently exercises the real `MediaIngestionService::stage()` production path for the cleanup-claim-loss scenario.
- Existing CAS/race behavior remains independently VERIFIED; this is regression-coverage debt, not a current correctness failure.

### P2-004A2 LOW observations

- Crash-recovery delete path does not increment `Eligible` counter or fire `StagingCleanupCandidateObserved` (cosmetic/observability inconsistency).
- `file deletion happens outside database transaction` test name overstates the property it directly asserts.

### DEFERRED DEPLOYMENT REQUIREMENT

The application contract proves 524,288,000 bytes accepted and 524,288,001 bytes rejected. However, the final Phase 2 evidence does not contain an independently retained artifact proving an actual 500 MiB multipart upload through the intended production/deployment HTTP stack.

Before production deployment, the deployed environment must verify/configure:
- effective web PHP SAPI upload limit
- effective `post_max_size`
- web-server/proxy request-body limit
- multipart overhead
- request timeout
- temporary storage capacity
- durable storage capacity
- actual request arrival at Laravel validation

### DEFERRED UX VERIFICATION

The upload progress and client-side success flow were verified through implementation/source inspection and server-side contract tests, but not through retained browser-runtime evidence.

Future browser/E2E verification should cover:
- visible upload progress
- no premature success presentation
- upload failure presentation
- successful Media Detail navigation

### Documentation drift

- CURRENT_STATE.md baseline test counts updated to current values (229/230, 688 assertions).
- Stale P2-007 "in REVIEW" references corrected to DONE.

## Next Action

Phase 3 Batch 3 = AUTHORIZED (2026-09-19). The HPO resolved owner decisions
B3-01 through B3-07 and recorded them in `DECISION_QUEUE.md` and ADR-018
(`DECISIONS.md`), then authorized Batch 3 via DECISION-P3-BATCH3-001 for P3-007
and P3-008. P3-007 was independently VERIFIED and closed as DONE by the HPO on
2026-09-19 (DECISION-P3-007-CLOSURE-001); no BLOCKER/HIGH/MEDIUM remain, and
MEDIUM-1 was reconciled to the non-blocking LOW-1. P3-008 integration
verification was executed on 2026-09-19 against the frozen P3-007 behavior:
live Redis and real FFmpeg/faster-whisper `large-v3` gates passed; the
independent review returned VERIFIED with no BLOCKER/HIGH/MEDIUM; and the HPO
closed P3-008 as DONE (DECISION-P3-008-CLOSURE-001) and recorded Phase 3
Batch 3 = CLOSED (DECISION-P3-BATCH3-CLOSURE-001). Evidence:
`PHASE3-P3-008-INTEGRATION-EVIDENCE.md` and
`reviews/P3-008-independent-review.md`. The HPO then closed Phase 3 as a whole
on 2026-09-19 (DECISION-PHASE3-CLOSURE-001). Phase 3 = CLOSED.

Phase 4 planning followed on 2026-09-19: the HPO resolved D4-01 through D4-07,
ratified ADR-019 (PROPOSED → ACCEPTED), and authorized Phase 4 for task-contract
authoring only (DECISION-PHASE4-AUTHORIZATION-001). Task contracts P4-001
through P4-006 were authored and audited; P4-001 was then authorized for
implementation and promoted BACKLOG → READY (DECISION-P4-001-AUTHORIZATION-001),
implemented, independently reviewed (VERIFIED; no BLOCKER/HIGH/MEDIUM), and
closed as DONE (DECISION-P4-001-CLOSURE-001). Wave 1 (P4-002, P4-004, P4-005)
was then authorized (DECISION-P4-WAVE1-AUTHORIZATION-001), implemented,
independently VERIFIED, and closed as DONE
(DECISION-P4-002/004/005-CLOSURE-001); Wave 1 = CLOSED. The
browser-verification strategy is resolved and P4-003 was authorized
(DECISION-P4-BROWSER-VERIFICATION-001, DECISION-P4-003-AUTHORIZATION-001);
P4-003 was implemented, independently VERIFIED, and closed as DONE
(DECISION-P4-003-CLOSURE-001). P4-006 executed and found a P4-004 defect; the
HPO authorized a P4-004 corrective reopen (DECISION-P4-004-REOPEN-001), the
correction was independently re-verified and P4-004 was re-closed DONE
(DECISION-P4-004-CORRECTIVE-CLOSURE-001). The finding is CLOSED
(DECISION-P4-006-FINDING-001); P4-006 was released and re-executed, and the fresh
final-gate rerun PASSED. The independent review
(`reviews/P4-006-independent-review.md`) returned VERIFIED (no
BLOCKER/HIGH/MEDIUM); P4-006 was closed DONE (DECISION-P4-006-CLOSURE-001) and
Phase 4 was closed by the HPO (DECISION-PHASE4-CLOSURE-001, 2026-09-20).

Phase 5 followed under ADR-022/ADR-023. All P5 tasks are DONE; P5-008 was
executed against the canonical self-hosted NLLB stack, independently VERIFIED
(`reviews/P5-008-independent-review.md`; no BLOCKER/HIGH/MEDIUM), and closed DONE
(`DECISION-P5-008-CLOSURE-001`). The HPO closed Phase 5 on 2026-09-23
(`DECISION-PHASE5-CLOSURE-001`); LOW/INFO debt carried non-blocking
(`DECISION-PHASE5-DEBT-CARRYFORWARD-001`). Final report:
`PHASE5-CLOSURE-REPORT.md`. Phase 6/7 eligibility is reconstructed from
`PHASE5-7-EXECUTION-CLASSIFICATION.md`; no Phase 6/7 task is authorized yet.

Canonical Phase 3
model:
large-v3 (turbo = non-default/experimental). See `PHASE4-PLANNING.md`,
`PHASE4-TASK-CONTRACT-AUDIT.md`, `DECISION_QUEUE.md`, and
`PHASE3-BATCH3-PLANNING.md`.

## Phase Authorization

Phase 2 is closed as COMPLETE_WITH_DEFERRED_DEBT (2026-09-17). P2-003,
P2-005, P2-004A2, P2-007 are DONE. P2-004A/P2-004A1 are BLOCKED under
ADR-013. Option D remains in force.

Phase 3 Batch 1 authorized by HPO (2026-09-17, DECISION-P3-BATCH1-001);
accepted and closed as DONE (2026-09-18). Canonical model: large-v3.

Phase 3 Batch 2 authorized by HPO (2026-09-18, DECISION-P3-BATCH2-001):
P3-004, P3-005, P3-006. One independent Claude batch review returned
P3-004/P3-005 VERIFIED and P3-006 CHANGES_REQUESTED; Correction Cycle 1
(P3-006 only) resolved HIGH-1, and the focused independent re-review returned
P3-006 = VERIFIED. The HPO closed P3-004/P3-005/P3-006 as DONE and recorded
Phase 3 Batch 2 = CLOSED (2026-09-19, DECISION-P3-BATCH2-CLOSURE-001).
Phase 3 Batch 3 authorized by HPO (2026-09-19, DECISION-P3-BATCH3-001):
P3-007 (Failure / Retry / Recovery Hardening), P3-008 (Real Phase Integration
Verification). Owner decisions B3-01 through B3-07 DECIDED (ADR-018). P3-007 is
DONE (independently VERIFIED, HPO closure 2026-09-19,
DECISION-P3-007-CLOSURE-001); P3-008 integration verification is complete,
independently VERIFIED, and closed as DONE
(DECISION-P3-008-CLOSURE-001). P3-008 final
execution used live Redis and real faster-whisper `large-v3` (dependency on
P3-007 verification satisfied). Phase 3 Batch 3 = CLOSED
(DECISION-P3-BATCH3-CLOSURE-001). Phase 3 = CLOSED (2026-09-19,
DECISION-PHASE3-CLOSURE-001).

Phase 4 = CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001).
DECISION-PHASE4-AUTHORIZATION-001 authorized contract authoring; ADR-019
ACCEPTED. Boundary: Phase 4 = Transcript Experience baseline; Phase 5 =
Translation; Phase 6 = Advanced Transcript UX; Phase 7 = Production Hardening.
P4-001..P4-006 = DONE (P4-004 corrective cycle
DECISION-P4-004-CORRECTIVE-CLOSURE-001; P4-006 DECISION-P4-006-CLOSURE-001;
DECISION-P4-006-FINDING-001 CLOSED). Residual LOW/INFO debt retained
(`reviews/PHASE4-final-closure.md`). Phase 5 = CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001).
See plan.md.

## Review status

P2-007 is DONE. P2-006 remains closure-only. Phase 3 Batch 1 COMPLETE /
CLOSED. Batch 1 review history: Cycle 1/2/3/4 = CHANGES_REQUESTED;
Post-Escalation Independent Review = CHANGES_REQUESTED; Final HPO-Decision
Verification = VERIFIED. H5 resolved; H6 resolved by final HPO model decision.
Canonical model: large-v3. DECISION-P3-BENCHMARK-GATE-001: DECIDED.
DECISION-P3-ESCALATION-001: DECIDED (Accept with known limitations).

Phase 3 Batch 2: P3-004 = VERIFIED, P3-005 = VERIFIED, P3-006 = VERIFIED
(Correction Cycle 1 re-review; HIGH-1 RESOLVED). Batch 2 overall = VERIFIED.
P3-004/P3-005/P3-006 = DONE (HPO closure 2026-09-19). Batch 3 = CLOSED
(2026-09-19, DECISION-P3-BATCH3-CLOSURE-001); P3-007 = DONE (DECISION-P3-007-CLOSURE-001)
and P3-008 = DONE (DECISION-P3-008-CLOSURE-001; independent review VERIFIED,
no BLOCKER/HIGH/MEDIUM; mandatory B3-06/B3-07 gates accepted). Phase 3 = CLOSED
(2026-09-19, DECISION-PHASE3-CLOSURE-001). Phase 4 task contracts are authored; P4-001 = DONE (DECISION-P4-001-CLOSURE-001); Wave 1 CLOSED (P4-002/P4-005 = DONE); P4-004 = DONE (corrective cycle, DECISION-P4-004-CORRECTIVE-CLOSURE-001); P4-003 = DONE (DECISION-P4-003-CLOSURE-001); P4-006 = DONE (DECISION-P4-006-CLOSURE-001); Phase 4 = CLOSED (2026-09-20, DECISION-PHASE4-CLOSURE-001); Phase 5 = CLOSED (2026-09-23, DECISION-PHASE5-CLOSURE-001).
