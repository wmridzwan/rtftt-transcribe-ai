# P2-002A — Enforce the Private Media Disk Boundary

## Status

DONE

## Ownership

Implementation Owner: Codex
Reviewer: Claude Code

## Authorized Phase

Phase 2 checkpoint follow-up; prerequisite for any future upload workflow. This follow-up does not authorize P2-003.

## Objective

Ensure the configured media disk is explicitly private and that future receiving, promotion, existence, download, and deletion paths use the same enforced media disk.

## Context

Originating independent review: `reviews/P2-001-P2-002-independent-review.md` (MEDIUM: named private disk is not yet an enforced application boundary).

## Scope

- Define and enforce one private media-disk boundary.
- Align configuration and future media storage access with the existing ownership and authorization guarantees.

## Out of Scope

- Real upload workflow or UI.
- P2-003 implementation, runtime configuration, or production changes.
- Public storage exposure.

## Dependencies

- Must be completed before any real upload workflow begins.

## Acceptance Criteria

- [x] Public disks cannot be selected as the media storage boundary.
- [x] Media storage access paths use the explicit private media disk.
- [x] Authorization, ownership, and deletion behavior remain intact.
- [x] Relevant static verification and independent review pass; runtime PHP tests were unavailable in the review environment.

## Review Reference

`reviews/P2-001-P2-002-independent-review.md`

## Implementation Notes

- Added one `MediaFile::storage()` boundary backed by `config('media.storage_disk')`.
- Added fail-fast validation at application boot and before media storage access. A configured public-visibility disk or disk rooted at the public disk root is rejected.
- Routed media existence checks, downloads, and controller/Livewire deletion through the configured media disk. Authorization, ownership, cascade confirmation, deletion, and duplicate semantics were preserved.
- Corrected the public-disk regression test to invoke `MediaFile::storage()` directly, so it reaches and demonstrates the existing guard without resolving `AppServiceProvider` through the container.
- No upload UI/workflow, queue, processing, transcription, P2-002B, P2-002C, or P2-003 work was implemented.

## Files Changed

- `app/Http/Controllers/MediaActionController.php`
- `app/Livewire/Media/Show.php`
- `app/Models/MediaFile.php`
- `app/Providers/AppServiceProvider.php`
- `resources/views/livewire/media/show.blade.php`
- `resources/views/media/index.blade.php`
- `tests/Feature/MediaManagementTest.php`
- `tests/Feature/MediaUploadContractTest.php`
- `tasks/P2-002A-enforce-private-media-disk.md`
- `CURRENT_STATE.md`

## Verification

- `git diff --check`: PASS.
- Media source scan: PASS; no default-disk existence, download, or deletion calls remain in the media controller, Livewire component, or media views.
- Focused Pest tests: NOT RUN; PHP is unavailable on PATH and the repository-recorded Herd PHP executable is inaccessible/unavailable in this environment.
- Pint: NOT RUN; PHP is unavailable, so `vendor/bin/pint --dirty --format agent` could not start.
- Independent review: VERIFIED in `reviews/P2-002A-independent-re-review.md` after the public-disk regression test was corrected.

Work closed the independently VERIFIED task as DONE under ADR-010. P2-003 remains closed.
