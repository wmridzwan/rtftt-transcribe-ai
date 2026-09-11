# TASK-004 Review — Complete Navigation and Action Availability

Reviewer: Claude Code
Date: 2026-09-11
Task: tasks/TASK-004-complete-navigation-and-action-availability.md
Implementation Owner: Codex

## Verdict

VERIFIED — no BLOCKER or HIGH findings. Two LOW findings and one MEDIUM finding, all non-blocking.

## Scope of Review

Independently inspected, without modifying implementation code:

- `git diff` for every file in the task's "Files Changed" list, plus `git status --short` to catch anything the list omitted.
- `routes/web.php` and `routes/settings.php` (unchanged by this diff — confirmed via `git diff`/`git log` — used as ground truth for which routes are admin-gated, which route names exist, and which views/controllers were already dead before this task).
- `app/Http/Controllers/TranscriptionExportController.php` and `database/seeders/DatabaseSeeder.php` (not in the task's file list, but necessary to independently verify the export-gating and download-gating fixes actually correct a real mismatch rather than just moving code around).
- `resources/views/components/settings/layout.blade.php` (the in-page settings sub-navigation, to assess the settings "current" state finding below).

## Independent Verification (re-run, not reused from the task file)

- `php artisan test --compact tests/Feature/PageRenderTest.php tests/Feature/TranscriptExportTest.php tests/Feature/MediaManagementTest.php tests/Feature/FolderManagementTest.php` → 69 passed, 208 assertions. Matches the task's claim.
- `php artisan test --compact` (full suite) → 141 tests, 140 passed, 1 skipped (pre-existing, intentional 2FA skip), 347 assertions, **0 failures**. No regressions (134 → 140, matching the new tests added).
- `vendor/bin/pint --test --format agent` on every changed application/test file (including the undisclosed `desktop-user-menu.blade.php`, see Finding 1) → passed.
- `vendor/bin/phpstan analyse app/Http/Controllers/FolderController.php app/Http/Controllers/MediaController.php` → **0 errors** (the only PHP class files this task touched; `app/Livewire/Media/Show.php` was not touched by this task, only its Blade view was).
- Did not re-run `npm run build` myself (no JS/CSS source was touched by this diff, only Blade/PHP), but confirm there is no plausible mechanism for this diff to affect the frontend build.

## Role-Correct Navigation

Confirmed via `routes/web.php` (unchanged by this diff) that `jobs.index`/`jobs.show` sit behind `Route::middleware('admin')` — pre-existing, not new. Before this task, `dashboard.blade.php` and `transcriptions/show.blade.php` rendered clickable links to these routes for **all** users, meaning a non-admin would click through to a 403. The fix wraps both in `@if (auth()->user()->isAdmin())`, rendering a plain non-interactive element for non-admins and preserving the real link for admins. Both branches are directly tested (`normal users see processing summaries without admin job links`, `admins retain processing job links`), and I confirmed the admin-only middleware these tests are protecting against is real, not assumed.

## Export Gating

This is the most consequential fix in the task, and I verified it goes deeper than moving a condition around. The old UI condition was:
```php
$hasFile = $transcription->mediaFile && $transcription->mediaFile->storage_path
    && in_array($transcription->mediaFile->status->value, ['uploaded', 'ready']);
```
I read `TranscriptionExportController` (all four export methods) and confirmed each one **already, independently** enforces `abort(403, ...)` unless `$transcription->status === TranscriptionStatus::Completed` — this server-side rule is pre-existing and untouched. Critically, none of the four export methods touch `Storage` or the media file at all; they only read `$transcription->segments()`/`full_text` from the database. This means the old UI condition was checking an entirely wrong signal (physical media file state) for a decision that only ever depended on transcription status — it could show an enabled Export button that would 403 (media ready, transcription not yet Completed) or a disabled one for an export that would have succeeded (transcription Completed, media file since removed/reprocessed). The new `$canExport = $transcription->status === TranscriptionStatus::Completed` is now byte-for-byte the same rule the controller enforces. Confirmed via the new test (`export controls depend on completed status and use normal links`) and independently re-run.

## Real Download Behavior

Confirmed the `wire:navigate` attribute was removed from all eight export links (four in the list view, four in the detail view). This matters concretely: Livewire's `wire:navigate` intercepts same-origin link clicks and fetches the destination as an HTML page fragment to morph into the DOM via AJAX, rather than letting the browser perform a normal navigation. On a file-download endpoint (`Content-Disposition: attachment`), this would prevent the browser's native "Save As" handling from ever firing — the response would instead be swallowed by Livewire's navigation layer. Removing it restores real browser-native downloads. The new test asserts no `wire:navigate` appears within 80 characters of the export URL in the rendered HTML for a completed transcription, which I consider an adequate (if not elegant) regression guard for a Blade-only test suite.

## Storage::exists Handling / Media Download Gating

Both `media/index.blade.php` and `livewire/media/show.blade.php` now gate the Download control behind `$mediaFile->storage_path && Storage::exists($mediaFile->storage_path)`. I checked whether this fix has any real-world bite or is purely theoretical: `database/seeders/DatabaseSeeder.php` assigns every seeded `MediaFile` a `storage_path` of `'media/'.Str::random(40).'.'.$extension` but **never actually writes a file to that path** (no `Storage::put()` call exists for these records). Combined with `MediaActionController::download()`'s pre-existing `abort(404, 'Physical file not found.')` guard, this means **every** Download button in the current demo/seed data was already a guaranteed dead end before this fix — a real, currently-live bug, not a hypothetical one. The fix correctly closes it. One caveat: this check runs inline per row in the media list (`Storage::exists()` is a disk I/O call per row per page load) — a minor N+1-shaped cost, out of scope to address here and unlikely to matter until real (Phase 2) storage volume, but worth knowing if the media list ever gets slow.

## Folder / Settings Discoverability

- **Folders navigation**: confirmed added to the sidebar's Files group, `:current="request()->routeIs('folders.*')"`, consistent with the existing Media Library pattern. Directly tested.
- **Empty-state Create Folder CTA**: the new button reuses the exact same `wire:click="$set('showCreateModal', true)"` binding already used by the page's persistent header button and the pre-existing `folders.create-modal` — no new modal system, satisfying the Out of Scope constraint. Directly tested.
- **Settings → `/settings`**: `desktop-user-menu.blade.php` and `sidebar.blade.php` both now point at `route('settings.index')`. I confirmed Profile/Security/Appearance remain reachable: `settings/index.blade.php` links to "Edit Profile", and `resources/views/components/settings/layout.blade.php` (pre-existing, unchanged) provides Security/Appearance links from within any settings sub-page. Reachability is fully satisfied.

### Finding 1 (MEDIUM, non-blocking) — Settings sidebar loses "current" state on its subpages

`sidebar.blade.php`'s Settings item changed from `:current="Str::startsWith(request()->url(), url('settings'))"` (matched any `/settings/*` URL) to `:current="request()->routeIs('settings.index')"` (matches only the exact overview route). I checked `routes/settings.php`: the subpages are named `profile.edit`, `security.edit`, `appearance.edit` — none start with `settings.`, so even a `settings.*` wildcard (the pattern this same file correctly uses for `media.*` and `folders.*`) would not have preserved the old behavior. The net effect: visiting Profile, Security, or Appearance no longer highlights "Settings" as the active sidebar item, unlike Media Library and Folders, whose subpages do highlight correctly. I also checked whether the in-page settings sub-nav (`components/settings/layout.blade.php`) compensates with its own active-state indicator — it does not (pre-existing, unrelated to this diff). So a user on `/settings/security` currently gets no visual cue anywhere in the UI that they are "in Settings." This does not break any acceptance criterion as written (reachability is intact) and is purely cosmetic/orientational, but it sits squarely in this review's "folder/settings discoverability" focus area and looks like an unintended side effect of the routing-based rewrite rather than a deliberate decision (it isn't mentioned in Implementation Notes or Known Limitations). **Recommendation:** use a URL-prefix or multi-route check (e.g. `request()->routeIs('settings.index', 'profile.edit', 'security.edit', 'appearance.edit')`) to restore the previous, correct behavior. Not required to block VERIFIED.

## Safe Removal of Dead Surfaces

Verified independently, not assumed:

- `routes/web.php` has **no diff** in this task (confirmed via `git diff`/`git log -- routes/web.php`), and it already pointed `folders.index` at `App\Livewire\Folders\Index` and `media.show` at `App\Livewire\Media\Show` **before** this task. This proves `FolderController::index()`, `MediaController::show()`, `resources/views/folders/index.blade.php`, and `resources/views/media/show.blade.php` were genuinely unreachable dead code, not just "probably" dead.
- Grepped the whole app/resources tree for any remaining reference to the removed `folders.index`/`media.show` **views** and to `layouts/app/header.blade.php` — zero hits outside the (still-live, different) `livewire.folders.index` / `livewire.media.show` views.
- Removing the two controller methods left no orphaned imports (`Folder`, `MediaFile`, `View` are all still used by the remaining methods in both controllers) — confirmed by reading the full resulting files, consistent with PHPStan reporting 0 errors on both.

## Finding 2 (LOW, non-blocking) — Undisclosed file change

`resources/views/components/desktop-user-menu.blade.php` was modified (the Settings menu item now points at `route('settings.index')` instead of `route('profile.edit')`) but is **not listed** in the task's "Files Changed" section, and isn't in the task's Context list either. The change itself is correct and in-scope (consistent with the sidebar's own Settings link change); this is purely a documentation-completeness gap, similar in kind to a pattern I've flagged in prior task reviews.

## Finding 3 (LOW, non-blocking) — Two `wire:navigate` removals outside the stated scope

The scope explicitly calls for removing `wire:navigate` "from file responses." I found two additional removals that are not file responses:
- `jobs/show.blade.php`: the "Transcription" link (`route('transcriptions.show', ...)`) lost `wire:navigate`.
- `transcriptions/show.blade.php`: the admin-only job-detail link (`route('jobs.show', $job)`) lost `wire:navigate`.

Both are plain internal page links, not downloads. These look like an incidental side effect of restructuring those blocks into `@if (isAdmin) <a ...> @else <div ...> @endif` conditionals, rather than a deliberate part of the file-response fix. Functionally harmless (the links still work; the browser just does a full page reload instead of a Livewire SPA transition), but it's an undocumented behavior change outside what the task describes, and "no unrelated behavior changes" is one of this task's own acceptance criteria. **Recommendation:** confirm this was intentional, or restore `wire:navigate` on these two links. Not required to block VERIFIED given the impact is a minor navigation-speed regression, not a correctness issue.

## Regression Risk

- Full suite re-run independently: 140 passed / 1 pre-existing skip / 0 failures.
- PHPStan clean on both changed PHP files.
- Dead-code removal independently proven safe via route/reference inspection above, not assumed from the task's own claim.

## Acceptance Criteria

All twelve boxes are checked and each maps to a specific, independently-verified test or direct code/route inspection above, with the two non-blocking findings noted (settings "current" state narrowing; two out-of-scope `wire:navigate` removals) as items worth a small follow-up rather than defects in what was delivered.

## Disposition

Recommend moving TASK-004 from REVIEW to VERIFIED. Findings 1–3 are non-blocking; Finding 1 (MEDIUM) is the most worth a fast-follow given it directly touches this review's named focus area. This review does not authorize DONE/closure or any production action.
