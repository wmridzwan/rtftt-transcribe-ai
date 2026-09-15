# P2-003 — Implement Real Upload & Ingestion Workflow

## Status

DONE

P2-003 was explicitly authorized by the Human Product Owner on 2026-09-13.
The independent remediation-cycle-2 re-review returned VERIFIED for the
current claim-upsert regression surface. The prior review and remediation
history remain preserved.

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code after implementation

## Authorized Phase

Phase 2 continuation candidate only. This task does not authorize Phase 3 or
any later phase.

## Objective

Implement the first real end-to-end single-file media upload and ingestion
workflow:

`Upload UI → web receiving path → temporary staging → server validation →
metadata derivation without media probing → private opaque storage promotion →
MediaFile persistence → Media Detail`

The canonical product boundary is exactly `524,288,000` bytes (500 MiB) per
file. The workflow must use the contracts established by P2-001, P2-001A,
P2-002, P2-002A, P2-002B, and P2-002C.

## Upload Transport Decision

### Selected approach

Use a normal Laravel `multipart/form-data` upload endpoint and controller,
with the existing Blade application UI providing the upload form and browser
progress presentation. Livewire may continue to provide surrounding UI and
navigation, but it is not the raw 500 MiB file transport for this task.

### Rationale

- The current upload screen is a Blade form using a normal POST convention;
  it is explicitly still demo-only.
- No application Livewire upload component, `WithFileUploads` workflow, or
  `config/livewire.php` override exists.
- The installed Livewire package defaults to a roughly 12 MiB temporary-file
  rule and a five-minute upload window. Using that path for a 500 MiB file
  would add a separate receiving boundary that is not currently configured.
- A normal Laravel multipart endpoint directly exercises the PHP and Nginx
  receiving path, is straightforward to feature-test, and keeps staging,
  validation, promotion, persistence, and retry identity in one bounded
  application workflow.
- Browser upload progress may be reported while the request is being sent,
  but the UI must not report ingestion completion until the server confirms
  successful persistence and returns the Media Detail destination.

Do not introduce chunked or resumable upload. The current accepted contract is
single-file upload and does not require chunking.

## Context and Contract References

- `AGENTS.md`
- `.ai/guidelines/orchestration-policy.md`
- `plan.md`
- `CURRENT_STATE.md`
- `architecture.md`
- `DECISIONS.md` — ADR-008, ADR-009, and ADR-011
- `config/media.php`
- `tasks/P2-001-confirm-upload-product-contract.md`
- `tasks/P2-001A-clarify-upload-size-unit.md`
- `tasks/P2-002-define-ingestion-lifecycle-contract.md`
- `tasks/P2-002A-enforce-private-media-disk.md`
- `tasks/P2-002B-define-ingestion-compensation-and-retry.md`
- `tasks/P2-002C-validate-generated-checksum-format.md`
- `tests/Feature/MediaUploadContractTest.php`
- `tests/Feature/IngestionCompensationContractTest.php`

## Scope

Implement only the bounded Phase 2 workflow required to:

- provide an authenticated upload UI;
- accept exactly one file per upload attempt;
- visibly report upload progress without claiming committed completion early;
- enforce the approved extension/MIME matrix server-side;
- enforce the exact `524,288,000`-byte application boundary;
- receive the file through the verified PHP/Nginx web path;
- stage the received file temporarily;
- derive available metadata without FFprobe or other media probing;
- generate the server-owned SHA-256 checksum;
- promote the file to an opaque path on the configured private disk;
- persist the authenticated owner, optional valid folder, metadata, checksum,
  storage identity, and `MediaStatus::Uploaded` in `MediaFile`;
- implement the P2-002B upload-attempt identity, retry, ambiguity, and
  compensation behavior;
- show clear validation and failure feedback;
- navigate successful uploads to `media.show`; and
- add the required contract, feature, integration, authorization, storage,
  retry, compensation, and regression tests.

## Receiving-Path Requirements

The implementation must verify the actual web request path, not CLI PHP
values alone.

### Repository configuration

- Preserve `config/media.php` at exactly `524_288_000` bytes.
- Preserve the approved media matrix, one-file rule, no-duration rule,
  duplicate-allowed rule, private storage disk, staging directory, durable
  directory, and 24-hour temporary retention value.
- Do not hard-code Herd-specific paths or limits into portable application
  logic.
- Do not add a Livewire raw-file transport unless this task is explicitly
  re-approved with a changed transport decision.

### Local environment verification during implementation

Verify the effective web-SAPI and active request path for:

- `upload_max_filesize` at or above the product file boundary;
- `post_max_size` above the full multipart body, including overhead;
- request/input and execution timeout behavior;
- the active Herd/Nginx site block and request-body limit;
- the effective PHP temporary upload directory and permissions;
- staging and durable private-storage paths;
- available capacity for temporary, staging, and durable copies; and
- a real upload near or at the 500 MiB boundary.

The current observed defaults (`2M`, `8M`, and an effective `128M` Nginx
site path) are insufficient and must not be treated as the product limit.

### Deployment requirement

Any deployment environment used later must provide equivalent receiving,
timeout, temporary-storage, durable-storage, and capacity guarantees. P2-003
does not implement production deployment or deployment configuration.

## Acceptance Criteria

The task is complete only when all of the following are true:

1. An authenticated user can upload exactly one supported media file.
2. The approved extension/MIME compatibility matrix is enforced server-side.
3. Exactly `524,288,000` bytes is accepted by application validation.
4. `524,288,001` bytes is rejected.
5. Unsupported extension, unsupported MIME, extension/MIME mismatch, missing
   file, and multi-file submissions are rejected.
6. No duration-based rejection is introduced.
7. Identical content remains allowed across separate intentional attempts.
8. The authenticated user becomes the `MediaFile` owner.
9. Upload-as-another-user behavior is not introduced.
10. Optional folder association obeys existing folder ownership rules.
11. The workflow follows staging → validation → metadata derivation → private
    promotion → persistence.
12. Successful persistence uses `MediaStatus::Uploaded`.
13. Durable media uses the configured private storage disk.
14. The durable path and storage identity are opaque and application-generated.
15. The original filename is retained separately from storage identity.
16. The default display name follows the established filename-derived rule.
17. The checksum is generated server-side as lowercase 64-character SHA-256
    hexadecimal text.
18. A client-supplied checksum cannot override the canonical checksum.
19. Retrying one upload attempt obeys the P2-002B identity and idempotency
    contract without creating a second committed record or object.
20. A separate intentional attempt may create a separate record for identical
    content.
21. Validation, promotion, persistence, and ambiguous-response failures obey
    the P2-002B compensation contract; committed media is not compensated.
22. A successful upload navigates to `media.show`.
23. Upload completion does not start Phase 3 processing or transcription.
24. The actual development web receiving path accepts the full product
    boundary, including required multipart overhead.
25. Progress is visible while receiving and does not falsely report ingestion
    completion before the server confirms persistence.
26. Validation and failure paths do not leave a committed orphan record or an
    unsafe durable object; unconfirmed cleanup remains an orphan candidate and
    is never auto-adopted.
27. Existing media-library authorization, storage, and regression tests pass.
28. Required formatting, static analysis, test, and frontend checks pass when
    their corresponding surfaces change.
29. No unrelated application, migration, runtime, Phase 3, or later-phase
    behavior changes.

## Test Strategy

### Contract and unit tests

- Assert the exact byte boundary and supported media matrix.
- Assert one-file, no-duration, duplicate-allowed, checksum, private-disk,
  staging, and route contracts.
- Use small synthetic values to test boundary logic where a physical 500 MiB
  fixture is unnecessary.

### Feature and integration tests

- Successful upload through the selected multipart endpoint.
- Exact-boundary acceptance and one-byte-over rejection.
- Supported and unsupported extension/MIME combinations.
- Missing, empty, mismatched, and multiple-file submissions.
- Staging, metadata derivation, private promotion, persistence, and redirect.
- Actual receiving-path verification near or at 500 MiB through the active
  development web path.

### Authorization and ownership negatives

- Unauthenticated upload rejection.
- Authenticated owner assignment.
- Cross-user access and download rejection.
- Admin behavior consistent with existing policy boundaries.
- Invalid or foreign folder association rejection.
- No upload-as-user path.

### Storage, checksum, retry, and compensation tests

- Opaque private storage path assertions.
- Public-disk guard behavior.
- Server-generated checksum and client-value rejection.
- Same-attempt retry idempotency.
- Separate-attempt duplicate acceptance.
- Promotion failure compensation.
- Persistence failure compensation.
- Ambiguous response recovery.
- No duplicate committed record/object and no unsafe orphan adoption.

### Existing verification

Run the repository’s established checks appropriate to the changed surfaces,
including the Composer test suite, Pint, PHPStan, and frontend build when UI
assets change. The implementation owner must record exact commands and
results, and the independent reviewer must verify them independently.

## Explicit Non-Scope

Do not implement or redesign:

- FFprobe, FFmpeg, media probing, duration probing, or codec probing;
- media conversion, transcoding, audio extraction, or waveform generation;
- transcription, faster-whisper, transcript generation, or provider routing;
- Redis, Horizon, transcription queues, worker orchestration, or callbacks;
- chunked or resumable upload;
- multi-tenancy, actor/owner schema, or collaboration semantics;
- production deployment, backup/restore, monitoring, or rollback;
- retention or privacy-policy redesign;
- new provider abstractions or fallback behavior; or
- any Phase 3 or later behavior.

## Dependencies

Completed contract dependencies:

- P2-001 — upload product contract;
- P2-001A — exact byte/unit clarification;
- P2-002 — ingestion lifecycle contract;
- P2-002A — private media-disk boundary;
- P2-002B — compensation and retry contract; and
- P2-002C — generated checksum format.

The receiving-path environment checks are implementation and integration
requirements of this task, not permission to lower or redefine the product
contract.

## Implementation-Isolation Plan

The current worktree contains unrelated modified application files and
untracked Phase 2/governance artifacts. Do not reset, discard, stash, move,
or commit those changes as part of this task publication.

Before implementation is authorized:

1. Preserve the current worktree unchanged.
2. Establish the agreed implementation baseline in a separate branch or
   worktree without staging unrelated work.
3. Ensure the baseline includes the accepted P2 contract artifacts that P2-003
   depends on, or obtain a separate Product Owner decision for a documentation-
   only baseline commit containing only those artifacts.
4. Keep the current dirty worktree available and untouched as the owner’s
   existing work.
5. Implement P2-003 only in the isolated worktree.
6. Before staging, inspect status and classify every changed file.
7. Stage only P2-003 implementation, test, and task-authorized configuration
   files.
8. Run `git diff --check`, inspect the staged diff, and verify no unrelated
   Phase 1, governance, runtime, or future-phase files are included.

A clean worktree cannot safely be created from the current committed HEAD
alone if the uncommitted P2 contract artifacts are required as the baseline.
That baseline decision must be made without moving or discarding the current
dirty work.

## Existing Uncommitted Dependencies

The current P2 contract is represented by these uncommitted or newly present
artifacts and must be preserved when an implementation baseline is prepared:

- `config/media.php`;
- `app/Models/MediaFile.php`;
- `app/Providers/AppServiceProvider.php`;
- `database/migrations/2026_09_11_120000_add_checksum_sha256_to_media_files_table.php`;
- `tests/Feature/MediaUploadContractTest.php`;
- `tests/Feature/IngestionCompensationContractTest.php`;
- the P2-001/P2-002 task records and independent reviews; and
- the reconciled `architecture.md`, `DECISIONS.md`, `CURRENT_STATE.md`,
  `plan.md`, and governance records.

This dependency list does not authorize staging or modifying those files.

## Review Gate

- Independent review by Claude Code is required before closure.
- The reviewer must inspect the implementation, migration, tests, receiving
  path evidence, and dirty-worktree isolation.
- The reviewer must independently verify that no Phase 3 behavior was added.

The actual PHP, Nginx/Herd, temporary-storage, and durable-storage receiving
path are implementation and integration gates within P2-003. They must be
verified and corrected during the task without lowering the product boundary;
they are not separate prerequisite projects.

## Implementation Notes

### Files Changed

- `app/Actions/MediaIngestionService.php`
- `app/Http/Controllers/MediaUploadController.php`
- `app/Models/MediaFile.php`
- `database/migrations/2026_09_13_120000_add_upload_attempt_id_to_media_files_table.php`
- `resources/views/media/upload.blade.php`
- `resources/views/media/index.blade.php`
- `resources/views/transcriptions/index.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/layouts/app/sidebar.blade.php`
- `routes/web.php`
- `tests/Feature/MediaIngestionTest.php`
- `plan.md`
- `CURRENT_STATE.md`
- `tasks/P2-003-implement-real-upload-ingestion-workflow.md`

### Important Decisions

- Raw upload transport uses a normal Laravel multipart endpoint with Blade UI
  and browser-side XMLHttpRequest progress.
- Livewire does not transport the raw file.
- `upload_attempt_id` is persisted as a nullable legacy-compatible field with
  a unique `(user_id, upload_attempt_id)` boundary for real ingestion records.
- Metadata is limited to request/file metadata; duration and codec values stay
  null because FFprobe/FFmpeg are Phase 3 concerns.
- Local Herd PHP and Nginx settings were corrected outside the repository only
  for development verification; no machine-specific values were added to
  portable application configuration.

### Isolation Execution

- The current dirty worktree was preserved; no reset, stash, move, overwrite,
  or unrelated staging was performed.
- A separate clean worktree was not created because the accepted P2 contract
  artifacts are themselves uncommitted dependencies in this worktree.
- Implementation was isolated by file scope to the P2-003 implementation,
  tests, migration, UI references, and required state records listed above.
- No files are staged and no commit was created; independent review must
  inspect the final diff against the pre-existing dirty state.

### Known Limitations

- The exact 500 MiB receiving-path verification was performed manually through
  the local Herd HTTPS endpoint rather than repeated in every automated test.
- Production receiving, capacity, deployment, backup, and monitoring settings
  remain outside P2-003.
- Staging cleanup and orphan reconciliation commands remain future work as
  specified by P2-002B; failed cleanup is logged as an orphan candidate and is
  never auto-adopted.

## Verification

Commands and results:

- `php artisan test --compact tests/Feature/MediaIngestionTest.php`: PASS — 16
  tests / 94 assertions.
- `php artisan test --compact`: PASS — 185 passed / 12 skipped / 566
  assertions.
- `vendor/bin/pint --dirty --format agent`: PASS (auto-fixed import ordering).
- `vendor/bin/phpstan analyse --no-progress`: PASS — 0 errors.
- `npm run build`: PASS; existing optional `fontaine` advisory only.

### CHANGES_REQUESTED Remediation (2026-09-13)

The independent review returned CHANGES_REQUESTED for five missing automated
test coverage gaps. All five are now remediated in `MediaIngestionTest.php`:

1. **Promotion-failure compensation** — Forces durable promotion failure by
   mocking `Storage::disk()` to return a mock adapter whose `put()` throws for
   durable paths while delegating `exists()`/`delete()` to the real fake disk.
   Asserts no MediaFile row is created and staging files are cleaned up.

2. **Persistence-failure compensation** — Calls `MediaIngestionService::ingest()`
   directly (not through the controller). Mocks `DatabaseManager::transaction()` to
   throw after durable promotion succeeds. Asserts staging and durable files are
   cleaned up by the outer catch compensation path and no committed MediaFile row
   persists.

3. **Ambiguous duplicate-key retry recovery** — Calls
   `MediaIngestionService::ingest()` directly. Mocks `DatabaseManager::transaction()`
   to insert a conflicting `MediaFile` row before executing the closure (simulating
   a concurrent-commit race). Asserts the service's inner catch block recovers by
   returning the existing record, cleaning up the duplicate durable file, and not
   creating a second committed row.

4. **Missing-file submission** — Posts a valid upload request but removes the
   uploaded file from `$_FILES` after validation. Asserts the controller rejects
   the submission with a `media_file` validation error.

5. **Unauthenticated upload rejection** — Posts to the upload endpoint without
   authentication. Asserts a 302 redirect to the login page.

### Second CHANGES_REQUESTED Remediation Pass (2026-09-13)

The second independent review returned CHANGES_REQUESTED for two tests that
did not exercise the intended code paths:

1. **Persistence-failure compensation** — The prior test used an invalid
   `folder_id` which was rejected by Laravel request validation before
   `MediaIngestionService::ingest()` was ever called. The fix calls `ingest()`
   directly with valid input and mocks `DatabaseManager::transaction()` to throw,
   ensuring the outer catch compensation path (durable cleanup + staging cleanup)
   is genuinely exercised.

2. **Ambiguous duplicate-key retry recovery** — The prior test pre-inserted a
   conflicting row, but the controller's `findByAttempt()` short-circuit returned
   it before `ingest()` was called. The fix calls `ingest()` directly and mocks
   `DatabaseManager::transaction()` to insert the conflicting row during the
   transaction (simulating the race), ensuring the inner catch block's recovery
   path (findByAttempt after duplicate-key, durable cleanup, return existing) is
   genuinely exercised.

No files are staged and no commit was created. Existing unrelated dirty
worktree changes remain preserved.

## Review

Review File: `reviews/P2-003-independent-review.md`

Review Status: VERIFIED by independent remediation-cycle-2 re-review. The
prior VERIFIED review and remediation history remain preserved.

## Next Action

P2-003 is closed as DONE. Closure does not authorize P2-007 or Phase 3.
