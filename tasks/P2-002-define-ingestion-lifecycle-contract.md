# P2-002 — Define Ingestion Lifecycle Contract

Status: DONE

Implementation Owner: Codex

Scope completed:

- Defined staging, validation, promotion, persistence, failure, idempotency, cleanup, deletion, and storage-boundary contracts.
- Added the nullable, non-unique SHA-256 checksum field for integrity and future duplicate detection.
- Preserved existing `MediaStatus` meanings; upload completion uses `uploaded`, while `processing` and `ready` remain reserved for later processing phases.
- Centralized the private disk, staging path, durable path prefix, and 24-hour temporary retention policy.

The real upload workflow, cleanup command, and processing infrastructure are not implemented by this task.

Verification:

- Focused contract suite: 6 passed / 20 assertions across P2-001 and P2-002 contract tests.
- Full suite: 163 passed / 1 skipped / 456 assertions.
- Pint passed.
- PHPStan passed with 0 errors.
- Frontend build passed.

Infrastructure finding:

- Current PHP CLI limits are `upload_max_filesize=2M` and `post_max_size=8M`; they are below the approved 500 MiB (524,288,000-byte) application limit. This is a P2-003 prerequisite and was not changed here.

Independent review: VERIFIED in `reviews/P2-001-P2-002-independent-review.md`. Work closed this task as DONE under ADR-010 after the independent verdict. The non-blocking private-disk, failure-compensation/retry, and checksum-format findings are tracked in `tasks/P2-002A-enforce-private-media-disk.md`, `tasks/P2-002B-define-ingestion-compensation-and-retry.md`, and `tasks/P2-002C-validate-generated-checksum-format.md`.
