# Independent Re-review — P2-002A

Date: 2026-09-11  
Reviewer: Codex, fresh independent re-review  
Decision basis: ADR-010 independent-review responsibilities  
Scope: P2-002A only

## Verdict

**P2-002A: VERIFIED**

The correction resolves the prior MEDIUM finding. The public-disk regression
test now invokes the model storage boundary directly, reaches
`assertPrivateStorageDisk()`, and demonstrates rejection of the public disk.
The private-disk path, media existence/download/deletion paths, authorization,
ownership, cascade confirmation, and deletion ordering remain intact. No
P2-003 implementation or authorization was introduced.

This artifact records the review only. The P2-002A task remains `REVIEW`; this
review does not mark it `VERIFIED` or `DONE`, and no task/state file was
modified.

## Review basis

Read and compared:

- ADR-010 in `DECISIONS.md`, the orchestration policy, `CURRENT_STATE.md`, and
  `plan.md`;
- the prior review
  `reviews/P2-001A-P2-002A-P2-002B-P2-002C-independent-review.md`;
- `tasks/P2-002A-enforce-private-media-disk.md`;
- the current tracked and untracked working-tree changes;
- `config/media.php` and the local/public filesystem disk definitions;
- `MediaFile::storage()`, `MediaFile::assertPrivateStorageDisk()`, and
  `MediaFile::hasPhysicalFile()`;
- the `AppServiceProvider` boot guard;
- controller, Livewire, and Blade media storage operations; and
- the corrected `tests/Feature/MediaUploadContractTest.php` plus the relevant
  `MediaManagementTest.php` coverage.

## Findings and evidence

### Public-disk guard regression: PASS

The corrected test at `tests/Feature/MediaUploadContractTest.php:52-57`
overrides `media.storage_disk` to `public` and calls
`MediaFile::storage()` directly. This avoids the prior invalid attempt to
construct `AppServiceProvider` manually, so execution reaches the guard.

`MediaFile::storage()` at `app/Models/MediaFile.php:87-92` calls
`assertPrivateStorageDisk()` before resolving the disk. The guard at
`app/Models/MediaFile.php:94-113` reads the selected disk configuration and
rejects both `visibility: public` and a root equal to the configured public
disk root. The configured public disk has `visibility: public` at
`config/filesystems.php:41-48`; therefore the corrected test proves the
expected `LogicException` before any public-disk operation can be returned.

The application boot guard at `app/Providers/AppServiceProvider.php:25-28`
also invokes the same assertion during normal application boot.

### Private-disk behavior and all touched media operations: PASS

The configured media disk remains `local` in `config/media.php:4`, and the
local disk root is `storage/app/private` in `config/filesystems.php:33-39`.
All touched application media filesystem operations use the single boundary:

- controller deletion and download use `MediaFile::storage()` at
  `app/Http/Controllers/MediaActionController.php:38-59`;
- Livewire deletion uses it at `app/Livewire/Media/Show.php:131-135`;
- media-list and media-detail existence checks use
  `MediaFile::hasPhysicalFile()` in the two touched media views; and
- the application source scan found no direct default-storage existence,
  download, or deletion calls in `app/` or `resources/`.

The added media-management regression at
`tests/Feature/MediaManagementTest.php:556-582` sets the filesystem default to
`public`, fakes both disks, writes only to `local`, then proves media detail
visibility, download content, and deletion through the configured private
disk. This specifically guards against falling back to the default public
disk.

### Authorization, ownership, and deletion behavior: PASS

The controller still authorizes deletion and download before storage access at
`MediaActionController.php:28` and `:52`; Livewire still authorizes deletion
at `Show.php:121`. Existing controller and Livewire tests retain owner versus
other-user coverage. Cascade confirmation is still validated before physical
deletion, and the physical object is deleted before the `MediaFile` row at
`MediaActionController.php:30-44` and `Show.php:123-137`. The policy remains
owner-or-admin, with no ownership rule changes in this correction.

### Scope and ADR-010 gate: PASS

The task explicitly excludes real upload workflow/UI, runtime configuration,
production changes, P2-002B/P2-002C work, and P2-003. The current diff and
source scan show no receiving workflow, upload controller, queue/outbox,
cleanup implementation, FFmpeg/whisper processing, or other P2-003 work.
`plan.md:103`, the task record, and `CURRENT_STATE.md` continue to keep
P2-003 unauthorized.

## Verification results

- `git diff --check`: **PASS**.
- Static source/scope scan for direct default-disk media operations:
  **PASS**; none found in application media paths.
- Public-disk test reachability: **PASS by source trace**; the corrected call
  reaches the guard before `Storage::disk()` is returned.
- PHP/Pest tests: **NOT RUN — runtime unavailable**. PHP is not on PATH, and
  the repository-recorded Herd PHP executable at
  `C:\Users\Admin\.config\herd\bin\php84\php.exe` remained inaccessible
  after a scoped permission check. This is an unavailable runtime, not a test
  failure.
- Pint: **NOT RUN — runtime unavailable** for the same reason; no formatting
  failure was observed.
- PHPStan: **NOT RUN — runtime unavailable** for the same reason; no static
  analysis failure was observed.
- Repository status after review: implementation changes were not modified;
  only this review artifact was added by this re-review.

## P2-003 GATE: CLOSED

**CLOSED.** This review authorizes no P2-003 or later work. The real upload
workflow remains outside this task and remains gated by the repository’s
explicit Phase 2 authorization and receiving-limit prerequisites.
