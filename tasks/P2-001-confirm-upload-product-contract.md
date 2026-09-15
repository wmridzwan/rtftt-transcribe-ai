# P2-001 — Confirm Upload Product Contract

Status: DONE

Implementation Owner: Codex

Scope completed:

- Centralized the approved audio/video extension and MIME compatibility matrix in `config/media.php`.
- Encoded the exact 500 MiB (524,288,000-byte) per-file application limit.
- Encoded the single-file, no-duration-limit, duplicate-allowed, private-storage, display-name, folder, video, admin, and original-media-download decisions in the Phase 2 ADR and architecture documentation.

The real upload workflow is not implemented by this task.

Verification:

- Focused contract suite: 6 passed / 20 assertions across P2-001 and P2-002 contract tests.
- Full suite: 163 passed / 1 skipped / 456 assertions.
- Pint passed.
- PHPStan passed with 0 errors.
- Frontend build passed.

Infrastructure finding:

- Current PHP CLI limits are `upload_max_filesize=2M` and `post_max_size=8M`; they are below the approved 500 MiB (524,288,000-byte) application limit. This is a P2-003 prerequisite and was not changed here.

Independent review: VERIFIED in `reviews/P2-001-P2-002-independent-review.md`. Work closed this task as DONE under ADR-010 after the independent verdict. The review's non-blocking unit-clarity finding is tracked in `tasks/P2-001A-clarify-upload-size-unit.md`.
