# Independent Review — Authorized P2 Follow-up Batch

Date: 2026-09-11  
Reviewer: Codex acting as independent reviewer  
Scope: `P2-001A`, `P2-002A`, `P2-002B`, `P2-002C` only

This review does not modify implementation code, start P2-003, or change any
task status. The four task files remain `REVIEW`.

## Verdicts

| Task | Verdict | Summary |
|---|---|---|
| P2-001A — Clarify Upload Size Unit | VERIFIED | Exact binary unit and byte boundary are consistent in active contract files and tests. |
| P2-002A — Enforce Private Media Disk Boundary | CHANGES_REQUESTED | The implementation is structurally aligned, but the negative regression test does not reach the guard and the focused suite is therefore not green. |
| P2-002B — Define Ingestion Compensation and Retry Contract | VERIFIED | Contract-only scope is proportionate and covers identity, compensation, retry, staging cleanup, and orphan reconciliation without adding workflow infrastructure. |
| P2-002C — Validate Generated Checksum Format | VERIFIED | Server-assignment guard, schema shape, duplicate policy, and client mass-assignment protection are consistent. One LOW documentation-state finding remains. |

## Review basis

Read and compared:

- `AGENTS.md`, `.ai/guidelines/orchestration-policy.md` including the State-to-Action Contract, `plan.md`, and `CURRENT_STATE.md`;
- ADR-008, ADR-009, and ADR-010 in `DECISIONS.md`;
- all four task files and `reviews/P2-001-P2-002-independent-review.md`;
- the actual tracked/untracked working-tree diff;
- `config/media.php`, `config/filesystems.php`, `app/Models/MediaFile.php`, `app/Providers/AppServiceProvider.php`;
- touched media controller, Livewire deletion/display paths, media views, migration, media-management tests, contract tests, and the future-work compensation test inventory.

## Product Owner requirements

### P2-001A — exact upload boundary: PASS

The active contract uses `500 MiB (524,288,000 bytes)` and does not use the
ambiguous decimal `500 MB` wording. Evidence:

- `config/media.php:16-17` records the exact comment and `524_288_000` value;
- `architecture.md:95`, ADR-008, `CURRENT_STATE.md:53`, and the task record use
  `500 MiB` and the exact byte value;
- `tests/Feature/MediaUploadContractTest.php:28-32` asserts both
  `524_288_000` and `500 * 1024 * 1024`;
- the static active-contract search found no ambiguous `500 MB` wording. The
  originating review retains its old wording as historical evidence of the
  resolved finding, not as the active product contract.

### P2-002A — one private storage boundary: PARTIAL / CHANGES_REQUESTED

The source design is aligned with the requirement:

- `MediaFile::storage()` is the single boundary at `app/Models/MediaFile.php:87-92`;
- `assertPrivateStorageDisk()` rejects a missing disk, `visibility: public`, or
  a disk sharing the configured public disk root at `app/Models/MediaFile.php:94-113`;
- application boot invokes the guard at `app/Providers/AppServiceProvider.php:25-28`;
- existence checks, downloads, and deletion use the boundary in
  `app/Http/Controllers/MediaActionController.php:38-59`,
  `app/Livewire/Media/Show.php:131-135`, and both media views through
  `MediaFile::hasPhysicalFile()`;
- the source scan found no direct default-disk storage calls in the touched
  application media paths;
- authorization, ownership, cascade confirmation, and deletion ordering remain
  intact in the controller/Livewire paths and existing policy.

Finding:

- **MEDIUM — P2-002A test does not exercise the fail-fast guard.**
  `tests/Feature/MediaUploadContractTest.php:53-57` calls
  `app(AppServiceProvider::class)->boot()`. The implementation-owner result
  records that this fails while resolving the provider's required `$app`
  constructor dependency, before `assertPrivateStorageDisk()` runs. The same
  failure is reported in the P2-002C verification as the one failure in the
  complete contract file. This is a verification/test defect, not evidence that
  the guard itself accepts the public disk. Correct the test to invoke the
  boundary through a valid application path (for example the model boundary),
  then rerun the focused media tests. Until that is done, the required
  public-disk protection is not independently demonstrated by a passing test.

### P2-002B — proportionate identity, compensation, retry, cleanup, orphan rules: PASS

The contract is explicit in `architecture.md:101-175` and ADR-009's extension at
`DECISIONS.md:340-370`:

- one owner-scoped opaque `upload_attempt_id` represents one intentional upload;
  completion/response retries reuse it, while intentional duplicates receive a
  new identifier;
- checksums are not idempotency keys and duplicates remain allowed;
- validation/promotion/persistence failures compensate only resources created by
  that attempt; committed media is never compensated;
- failed or unconfirmed deletion becomes an orphan candidate and is never
  auto-adopted into a new `MediaFile`;
- post-commit response retries look up the owner/attempt result before replay;
- staging cleanup claims/leases before deletion, defers while a retry is active,
  and requires re-staging with the same attempt identity if cleanup wins;
- reconciliation defers young/active candidates, deletes old unclaimed
  unreferenced objects, and records/retries failed deletes without synthesizing a
  row;
- `tests/Feature/IngestionCompensationContractTest.php` is a future-work TODO
  inventory only. No upload controller, queue, outbox, cleanup command, or
  persisted state machine was added.

LOW process finding: `tasks/P2-002B-define-ingestion-compensation-and-retry.md:40-45`
leaves the first three acceptance checkboxes unchecked even though its completion
notes state that those contract requirements were implemented. This does not
change the contract verdict, but the task record should be reconciled before
closure.

### P2-002C — server-generated checksum contract: PASS

Evidence:

- `config/media.php:6-10` defines SHA-256, lowercase hexadecimal encoding, and
  length 64;
- `MediaFile::assignGeneratedChecksumSha256()` at
  `app/Models/MediaFile.php:121-134` accepts only exactly 64 lowercase hex
  characters;
- `checksum_sha256` is removed from `$fillable`, so arbitrary client mass
  assignment is not trusted;
- the migration at
  `database/migrations/2026_09_11_120000_add_checksum_sha256_to_media_files_table.php:11-14`
  makes the column nullable and indexed but non-unique;
- the contract test covers invalid formats, valid server assignment, duplicate
  checksum rows, and nullable prototype rows;
- ADR-009 and `architecture.md` explicitly say the server computes the checksum,
  client values are rejected, and no deduplication is performed.

LOW documentation-state finding: `CURRENT_STATE.md:75` and `:185` conflict;
the current task file and the active task/review sections correctly say
P2-002C is in `REVIEW`, while the later historical review-status paragraph says
it “remains READY.” `CURRENT_STATE.md:81` also labels a section “Ready Tasks” while
listing tasks already in `REVIEW`. Reconcile those stale state statements before
or during normal task closure.

## Scope, authorization, and compatibility

- No filename or diff evidence shows P2-003 work. No upload route/controller,
  receiving implementation, cleanup command, queue, outbox, or larger state
  machine was added.
- `plan.md:103`, the four task files, ADR-008/009, and `CURRENT_STATE.md` keep
  P2-003 and later work unauthorized. The contract TODO test inventory does not
  implement that work.
- Existing owner/admin policy behavior remains unchanged. Controller and
  Livewire operations still authorize view/delete, preserve cascade confirmation,
  and delete the physical path before the `MediaFile` row. Folder ownership rules
  remain outside the changed storage boundary.
- The checksum migration is nullable/non-unique and does not introduce
  deduplication or reject existing prototype rows.

## Verification performed

- `git diff --check`: PASS.
- Static contract/scope checks: PASS for exact byte value, active `MiB` wording,
  four task statuses being `REVIEW`, absence of P2-003 filenames, and absence of
  direct default-disk calls in the touched application media paths.
- PHP/Pest/Pint/PHPStan: NOT EXECUTED in this review. No `php` executable is on
  PATH; the repository-recorded Herd path could not be accessed/launched in this
  environment. This is an unavailable runtime, not a newly observed PHP test
  failure. The known P2-002A test failure above is reported from the
  implementation-owner verification record and is treated separately.
- Prior implementation-owner reports were not treated as independent execution
  evidence.

## P2-003 GATE: CLOSED

**CLOSED.** This review grants no authorization to start P2-003 or any later
task. P2-003 remains gated on the explicit application/runtime receiving-limit
prerequisite and on completion of the P2-002A test correction; no real upload
workflow is approved by this review.

The verdicts are returned to Work through this artifact. Work must not mark any
of the four tasks `DONE` based on this review; the task files remain in `REVIEW`.
