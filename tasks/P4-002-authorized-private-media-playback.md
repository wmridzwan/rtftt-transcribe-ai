# P4-002 — Authorized Private Media Playback

## Status

DONE

## Ownership

Implementation Owner: UNASSIGNED
Reviewer: Claude Code (independent review; per AGENTS.md agent model)

## Authorization

This contract was authored under `DECISION-PHASE4-AUTHORIZATION-001` (Phase 4
authorized for task-contract authoring, 2026-09-19), audited/finalized on
2026-09-19, and authorized for implementation under
`DECISION-P4-WAVE1-AUTHORIZATION-001` (HPO, 2026-09-19): BACKLOG → READY. P4-001
is DONE, satisfying this task's dependency. Only P4-002, P4-004, and P4-005 are
authorized in Wave 1; P4-003 and P4-006 remain BACKLOG.

## Authorized Phase

Phase 4 — Transcript Experience baseline (ADR-019)

## Batch

Batch 1 (proposed; to be confirmed by the implementation authorization)

## Objective

Add an authorized application range-stream endpoint that serves private media to
the owning user (or an admin) for in-browser audio and video playback, without
exposing private filesystem paths and without loading the entire media object
into PHP memory.

## Context

- ADR-019; `PHASE4-PLANNING.md` (D4-02 decided)
- P4-001 (opaque media identity and workspace read model)
- Existing: `MediaActionController::download` (full download only),
  `MediaFile::storage()`, `MediaFilePolicy`, `config/media.php`
- Private storage disk: `RTFTT_MEDIA_DISK` (default `local`); media path is
  `media/{uuid}/{opaque-filename}`
- `MediaFile` fields: `uuid`, `mime_type`, `file_size_bytes`, `storage_path`,
  `media_type`

## Scope

Implement only:

- an authorized streaming route for private media, bound by `MediaFile` UUID
  (opaque identity), behind the existing `auth`/`verified` middleware and the
  existing `MediaFilePolicy::view` authorization;
- HTTP byte-range support per §HTTP Contract;
- `Content-Type` derived from `MediaFile::mime_type`;
- audio and video support;
- chunked streaming from the configured private disk, never loading the whole
  object into PHP memory;
- tests for the above.

## HTTP Contract

- `Accept-Ranges: bytes` on all successful responses.
- Full request (no `Range`): `200 OK`, `Content-Length` = full size, correct
  `Content-Type`, streamed body.
- `Range: bytes=0-`: `206 Partial Content`, `Content-Range: bytes 0-<size-1>/<size>`,
  `Content-Length` = `<size>`.
- `Range: bytes=N-M`: `206`, `Content-Range: bytes N-M/<size>`, `Content-Length`
  = `M-N+1`, where `N <= M` and `M < size`.
- `Range: bytes=N-`: `206`, `Content-Range: bytes N-<size-1>/<size>`,
  `Content-Length` = `<size>-N`.
- `Range: bytes=-N` (suffix): `206`, the last `N` bytes,
  `Content-Range: bytes <size-N>-<size-1>/<size>`.
- Unsatisfiable range (`N >= size`, or suffix `N == 0`): `416 Range Not
  Satisfiable` with `Content-Range: bytes */<size>`.
- Multiple ranges or a syntactically invalid `Range` header: ignore the range
  and return `200 OK` (single-range support only). Malformed ranges must not
  raise a fatal error or allocate unbounded memory.
- `HEAD` requests (if routed) return the same headers with no body.
- `Content-Type` must be the persisted `MediaFile::mime_type`.
- Range math must never produce a negative or overflowing length.

## Out of Scope

Do not implement:

- signed temporary storage URLs as the canonical baseline (D4-02);
- CDN, transcoding, HLS/DASH, adaptive bitrate (Phase 7);
- waveform or timeline features;
- seek/highlight binding or auto-scroll (P4-003);
- search/copy (P4-004);
- export changes (P4-005);
- any schema/migration change;
- any change to the Phase 2 private-storage contract.

## Dependencies

Requires:

- P4-001 (Transcript Experience Contract) — VERIFIED and closed DONE.

## Acceptance Criteria

1. An authenticated owner can stream their own private media (`200`).
2. An admin can stream media within the existing admin authorization boundary.
3. Unauthenticated requests are denied (redirect/401, never media content).
4. A cross-user request is denied (403/404, never media content).
5. `Range: bytes=0-` returns `206` with `Content-Range: bytes 0-<size-1>/<size>`.
6. `Range: bytes=N-M` returns `206` with correct `Content-Range` and
   `Content-Length` (`M-N+1`).
7. `Range: bytes=N-` returns `206` with correct `Content-Range` and
   `Content-Length` (`size-N`).
8. `Range: bytes=-N` returns `206` with the correct suffix `Content-Range`.
9. An unsatisfiable range returns `416` with `Content-Range: bytes */<size>`.
10. A malformed/multiple range returns `200` (range ignored) without error.
11. `Content-Type` equals the persisted `mime_type` for audio and video cases.
12. `Accept-Ranges: bytes` is present on successful responses.
13. No private filesystem path appears in the URL, headers, or body.
14. The media object is streamed in bounded chunks; the full object is never
    read into PHP memory (`Storage::get()` of the whole file is forbidden).
15. A path-traversal attempt via the route parameter cannot resolve outside the
    authorized media object.
16. Range headers cannot bypass authentication/authorization.
17. The Phase 2 private-storage contract is unchanged.
18. Relevant tests pass; Pint clean; PHPStan 0 errors.
19. No unrelated functionality is changed.

## Security / Privacy Requirements

- Route identity is the opaque `MediaFile` UUID; sequential ids are not used.
- Authorization is enforced before any storage read.
- The route reveals no disk name, root, or storage path.
- A media UUID cannot be used to read another user's file.
- Resource limits are respected for malformed ranges (no unbounded buffering).

## Test Requirements

Minimum tests:

- owner `200` full response;
- owner `206` for `bytes=0-`, `bytes=N-M`, `bytes=N-`, `bytes=-N`;
- `416` for unsatisfiable range;
- `200` (ignored range) for malformed/multiple range;
- unauthenticated denial;
- cross-user denial;
- missing physical file handling (`404`);
- `Content-Type` for one audio and one video case;
- assertion that no filesystem path is exposed;
- assertion that the whole-file read path is not used (bounded streaming).

## Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Path leakage | Opaque UUID route; no path in output; assertion test |
| Range math errors | Explicit tests for all four range forms + unsatisfiable |
| Whole-file memory read | Bounded chunk streaming; forbid `Storage::get()` |
| Unauthorized streaming | Policy check before storage access; denial tests |
| Malformed range resource abuse | Ignore/reject safely; no unbounded allocation |
| MIME mismatch | Use persisted `mime_type`; audio+video assertions |

## Implementation Notes

### Files Changed

- `routes/web.php` — added the `media.stream` route
  (`GET /media/{mediaFile:uuid}/stream`).
- `app/Http/Controllers/MediaActionController.php` — added `stream()` and the
  `resolveRange()` helper.
- `tests/Feature/MediaStreamingTest.php` (new).

### Important Decisions

- Opaque identity: route binds `MediaFile` by `uuid`; authorization
  (`MediaFilePolicy::view`) runs before any storage read.
- Single HTTP range only. Malformed, multi-range, non-`bytes` units, and
  `N > M` are ignored (200 full response); unsatisfiable ranges return 416 with
  `Content-Range: bytes */<size>`.
- Bounded streaming: `readStream()` + `fseek()` + 8 KiB `fread()` chunks; the
  whole object is never read into PHP memory (`Storage::get()` is not used).
- `Content-Type` from the persisted `mime_type`; `Accept-Ranges` and
  `Content-Length` always set; `Content-Range` on 206.
- `HEAD` is served by Laravel's GET/HEAD route pairing.

### Known Limitations

- `fseek()` on the read stream assumes a seekable stream (canonical private
  local disk). Remote/non-seekable storage adaptation is deferred (Phase 7).
- Only single-range requests are supported, by contract.

## Verification

Commands executed on 2026-09-19 (PHP 8.4, Pest 5.1):

- `vendor/bin/pest tests/Feature/MediaStreamingTest.php` → 13 passed,
  61 assertions.
- Full wave suite: 422 tests, 421 passed, 1 skipped (pre-existing 2FA),
  1400 assertions, 2 warnings (pre-existing baseline).
- `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress` → 0 errors.
- `vendor/bin/pint ... --format agent` → passed.

Result:

PASSED

## Review

Review File: `reviews/P4-002-independent-review.md`

Review Status: VERIFIED (independent review, 2026-09-20). No BLOCKER/HIGH/
MEDIUM findings. Non-blocking: LOW-1 (missing required "bounded streaming"
test assertion; behavior confirmed correct by source inspection), LOW-2
(missing range boundary-case tests for `bytes=0-0`/`M>=size`/`N>M`/`bytes=-0`/
oversized suffix; all hand-traced and confirmed RFC-7233-consistent), LOW-3
(hypothetical zero-byte file would report `Content-Length: 1` vs 0-byte body;
no confirmed reachable ingestion path). Authorization-before-storage-read,
opaque UUID identity, bounded chunked streaming (no `Storage::get()`/
`file_get_contents()`), and correct HEAD behavior (traced through actual
Symfony/Laravel framework source, not assumed) were all independently
confirmed. All reported quality gates (focused suite 13/61, full suite
422/421/1/~1400/2, PHPStan 0 errors, Pint clean) were independently
reproduced with an exact or equivalent match. Eligible for Human Product
Owner closure to DONE.

## Closure

Closed as DONE by the Human Product Owner on 2026-09-20
(DECISION-P4-002-CLOSURE-001), based on `reviews/P4-002-independent-review.md`
(P4-002 = VERIFIED; no BLOCKER/HIGH/MEDIUM; LOW-1/LOW-2/LOW-3 non-blocking,
retained as historical observations).

Canonical transition: VERIFIED → (HPO closure decision) → DONE. History
preserved: BACKLOG → Wave 1 authorization (DECISION-P4-WAVE1-AUTHORIZATION-001)
→ READY → implementation → REVIEW → independent VERIFIED → HPO closure → DONE.

Closure is governance/state reconciliation only; no implementation or test
change is authorized. P4-003's dependency on P4-002 DONE is now satisfied; its
implementation authorization is a separate decision.

## Completion

Required flow: BACKLOG → READY → IN_PROGRESS → REVIEW → VERIFIED → DONE

Implementation owner must not mark their own work VERIFIED. P4-003 depends on
this task being VERIFIED and closed DONE.
