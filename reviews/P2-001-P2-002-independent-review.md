# Independent Review — P2-001 and P2-002

## Review Status

VERIFIED for the P2-001/P2-002 contract checkpoint, with documented MEDIUM follow-ups.

This is an independent source review by Codex under ADR-010. No application code,
task file, task status, machine-wide configuration, or P2-003+ work was changed.

## Scope and Authority

Reviewed against:

- `AGENTS.md`
- `.ai/guidelines/orchestration-policy.md`
- `CURRENT_STATE.md`
- `plan.md`
- `architecture.md`
- `DECISIONS.md` (ADR-008, ADR-009, ADR-010)
- `DECISION_QUEUE.md`
- `tasks/P2-001-confirm-upload-product-contract.md`
- `tasks/P2-002-define-ingestion-lifecycle-contract.md`
- the actual tracked diff and untracked P2 files in the working tree
- `config/media.php`
- `app/Models/MediaFile.php`
- `database/migrations/2026_09_09_000002_create_media_files_table.php`
- `database/migrations/2026_09_10_085138_add_folder_id_and_display_name_to_media_files_table.php`
- `database/migrations/2026_09_11_120000_add_checksum_sha256_to_media_files_table.php`
- `tests/Feature/MediaUploadContractTest.php`
- Phase 1 media controllers, Livewire component, policy, filesystem configuration,
  folder behavior, and media/transcription/processing migrations and tests

The repository correctly limits the authorized work to the P2-001/P2-002 checkpoint.
The real upload workflow, cleanup command, processing, and transcription work remain
out of scope.

## Verdicts

| Task | Verdict | Reason |
|---|---|---|
| P2-001 — Confirm Upload Product Contract | VERIFIED | The product contract and exact media matrix are recorded consistently, with no BLOCKER or HIGH finding. |
| P2-002 — Define Ingestion Lifecycle Contract | VERIFIED | The lifecycle sequence, status semantics, storage boundary, checksum shape, and recovery constraint are recorded, with no BLOCKER or HIGH finding. |

The task files remain `REVIEW`; this artifact returns the verdict to Work for normal
ADR-010 closure. They are not marked `DONE` here.

## Findings

### MEDIUM — P2-001 — 500 MB unit is not explicit

Evidence:

- `config/media.php:10` sets `max_upload_bytes` to `524_288_000`.
- `tests/Feature/MediaUploadContractTest.php:27` locks the same value.
- ADR-008, `architecture.md:95`, and the task files call this “500 MB” without
  distinguishing decimal MB from binary MiB.

`524_288_000` is exactly 500 MiB, not 500,000,000 decimal bytes. The value is
internally consistent and safe for the current contract checkpoint, but P2-003 must
make the unit explicit and test the exact accepted/rejected boundary.

Disposition: non-blocking follow-up; do not silently change the value without a
product decision if decimal MB was intended.

### MEDIUM — P2-002 — The named private disk is not yet an enforced application boundary

Evidence:

- `config/media.php:4` allows `RTFTT_MEDIA_DISK` to select a disk.
- `config/filesystems.php:33-47` shows `local` is private and `public` is a
  different public-root disk, but the media config does not prevent a public disk
  from being selected.
- Existing Phase 1 read/delete paths in
  `app/Http/Controllers/MediaActionController.php:39-57` and
  `app/Livewire/Media/Show.php:131-133` use the default `Storage` facade rather
  than an explicit `config('media.storage_disk')` disk.
- The current `.env` uses the private `local` default, and the existing policy
  checks preserve owner/admin access boundaries.

The current default is private and the contract checkpoint has no real upload
workflow, so this is not a present exposure. Before P2-003, the receiving,
existence, download, promotion, and deletion paths must use one explicitly private
media disk, or the configuration must reject public disks.

Disposition: non-blocking follow-up, but a P2-003 prerequisite.

### MEDIUM — P2-002 — Failure compensation and retry identity need an executable contract

Evidence:

- ADR-009 and `architecture.md:99` correctly state that filesystem promotion and
  database persistence need explicit recovery and that a retry is tied to one upload
  attempt.
- The documented sequence is correct:
  `temporary staging → validation → metadata derivation → opaque private storage
  promotion → MediaFile persistence → Media Detail`.
- No upload-attempt identity, persisted attempt state, promotion compensation rule,
  or orphaned durable-file reconciliation rule is defined in the current files.

This is acceptable for a contract-only checkpoint because no workflow exists yet,
but P2-003 must specify what happens when promotion succeeds and persistence fails,
when persistence succeeds after a response retry, and when staging cleanup races a
retry. The rule must preserve allowed intentional duplicates while preventing a
single upload attempt from creating misleading duplicate records.

Disposition: non-blocking follow-up for this checkpoint; required before implementing
the real upload workflow.

### LOW — Checksum format validation is intentionally deferred

The schema is safe for the stated storage policy: `checksum_sha256` is a nullable
64-character string with a non-unique index (`database/migrations/2026_09_11_120000_add_checksum_sha256_to_media_files_table.php:11-14`),
and it is mass assignable in `app/Models/MediaFile.php:49`. The tests verify nullability,
index non-uniqueness, and duplicate checksum rows. The schema does not enforce
lowercase hexadecimal content; that is appropriate to defer to server-side
checksum generation/validation, but P2-003 should not accept client-supplied arbitrary
checksum values.

## Acceptance and Contract Verification

- MIME/extension matrix: PASS by source comparison. `config/media.php:22-35`,
  ADR-008, and the contract test agree on MP3, WAV, M4A, AAC, FLAC, OGG, MP4, MOV,
  and WEBM with the listed server-inspected MIME values.
- Single-file semantics: PASS. `max_files_per_upload` is `1` (`config/media.php:12`).
- Size semantics: PASS with the MEDIUM unit-clarity finding above. The configured
  boundary is `524_288_000` bytes.
- Duration: PASS. `max_duration_seconds` is `null` (`config/media.php:14`), and
  the docs state no duration limit.
- Duplicate policy: PASS. `allow_duplicate_uploads` is true, the checksum index is
  non-unique, and the test creates two rows with the same checksum.
- Post-upload behavior: PASS as a contract. `post_upload_route` is `media.show`
  (`config/media.php:18`), and ADR-008 states that upload does not initiate
  transcription.
- Status semantics: PASS. ADR-009 and `architecture.md:95` reserve `processing`
  and `ready` for later processing and use `uploaded` for successful persistence.
- Storage privacy and opacity: PASS for the current local default. The local disk
  root is `storage/app/private`, it differs from the public disk, and the documented
  durable layout excludes user identity and original filename. The configurable-disk
  enforcement caveat is recorded above.
- Display name and rename behavior: PASS as a contract. ADR-008 preserves the
  original filename and physical identity while allowing a display-name default and
  later rename.
- Folder/admin behavior: PASS by compatibility review. The media policy preserves
  owner-or-admin view/update/delete access; folder moves constrain destination folders
  to the media owner.
- Deletion compatibility: PASS. Existing controller and Livewire deletion paths
  authorize deletion, require accepted cascade confirmation when transcriptions
  exist, delete the physical path when present, and delete the `MediaFile`; the
  Phase 1 foreign-key chain cascades related transcriptions, segments, and jobs.
- Processing boundary: PASS. The documentation explicitly excludes FFprobe, FFmpeg,
  queues, Redis, Horizon, faster-whisper, and transcription from this checkpoint.

## PHP Runtime Upload-Limit Finding

The repository records the known finding in `CURRENT_STATE.md:61`, ADR-008, and both
task files: `upload_max_filesize=2M` and `post_max_size=8M` are below the approved
application boundary, and the effective PHP/web-server/Livewire receiving path must
be raised and verified before P2-003.

I did not change machine-wide configuration. Independent runtime execution was not
available in this review environment: the repository-recorded PHP 8.4 path
`C:\Users\Admin\.config\herd\bin\php84\php.exe` could not be launched. Therefore
the 2M/8M values are recorded as repository evidence, not as a fresh runtime probe.

## Verification Results

Read-only checks performed:

- `git diff --check`: PASS (only environment warnings about the global Git ignore
  file; no diff whitespace errors).
- Source inspection of the task files, ADR-010 governance, product/lifecycle ADRs,
  config, migration, model, Phase 1 policy/storage/deletion paths, and tests: PASS
  with the findings above.
- Focused Pest test run: NOT EXECUTED because the configured PHP executable was not
  available to launch in this environment.
- Full Pest suite, Pint, PHPStan, and frontend build: NOT EXECUTED in this review.
  The task files record prior implementation-owner results (163 passed, 1 skipped,
  456 assertions; Pint/PHPStan/build passed), but those results were not treated as
  independent execution evidence.

## P2-003 GATE

**CLOSED.** No authorization is granted for P2-003 or any later task. The PHP/web
receiving-limit prerequisite, explicit 500 MB unit, media-disk enforcement, and
executable failure/retry compensation contract must be resolved before real upload
implementation begins.

## Conclusion

P2-001 and P2-002 are independently **VERIFIED** for the authorized contract
checkpoint. Work may perform normal ADR-010 closure, but must preserve the documented
MEDIUM follow-ups and the closed P2-003 gate.
