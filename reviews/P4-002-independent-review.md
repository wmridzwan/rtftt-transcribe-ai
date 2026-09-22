# P4-002 — Independent Review: Authorized Private Media Playback

Reviewer: Claude Code (independent reviewer role per AGENTS.md / ADR-015 /
`.ai/guidelines/orchestration-policy.md`)
Date: 2026-09-20
Scope: `tasks/P4-002-authorized-private-media-playback.md` only. This review
does not implement fixes, does not modify implementation code or tests, does
not mark P4-002 DONE, and does not authorize P4-003 or P4-006. It is
independent of the P4-004 and P4-005 reviews conducted alongside it in the
same session; each task's verdict stands on its own contract.

## 1. Contract Checked

`tasks/P4-002-authorized-private-media-playback.md` (Status at review start:
`REVIEW`; authorized under `DECISION-P4-WAVE1-AUTHORIZATION-001`; dependency
P4-001 = DONE, satisfied).

## 2. Implementation Inspected

- `routes/web.php` — `GET /media/{mediaFile:uuid}/stream` (`media.stream`),
  inside the `auth`/`verified` middleware group, opaque UUID route-model
  binding.
- `app/Http/Controllers/MediaActionController.php` — `stream()` and
  `resolveRange()` (full file read).
- `app/Policies/MediaFilePolicy.php` — `view()` (owner-or-admin), reused
  unchanged.
- `app/Models/MediaFile.php` — `storage()` (private-disk boundary assertion,
  Phase 2/ADR unchanged), `hasPhysicalFile()`.
- `tests/Feature/MediaStreamingTest.php` (full file read, 182 lines, 13 tests).
- Framework internals traced directly in `vendor/symfony/http-foundation/{Response,StreamedResponse}.php`
  and `vendor/laravel/framework/src/Illuminate/Routing/Router.php` to verify
  actual HEAD behavior rather than assuming it (see §6).

## 3. Authorization Ordering (Section 5 of review brief)

Traced the control flow of `stream()` line by line:

1. `$this->authorize('view', $mediaFile)` — runs first, before any storage
   call.
2. `$storage->exists($mediaFile->storage_path)` and `$storage->size(...)` —
   metadata-only calls, only reached after authorization passes.
3. `$storage->readStream(...)` is only opened **inside the deferred streaming
   closure** passed to `response()->stream()`, which Laravel does not execute
   until the response is actually sent — i.e., strictly after authorization,
   after 404 checks, and after range resolution.

Route-model binding by UUID happens before the controller method runs (a DB
lookup only, not a storage read); a non-existent UUID 404s before
`authorize()` is reached, which is ordinary, non-leaking Laravel behavior (no
media content or path is exposed either way). No code path allows a stream to
open, existence to be probed, or bytes to be read before authorization is
satisfied. **Confirmed: authorization strictly precedes any storage read.**

## 4. Opaque Identity

Route uses `{mediaFile:uuid}` (confirmed in `routes/web.php:44`), unlike the
pre-existing `download`/`rename`/`destroy`/`move` routes on the same
controller which still bind by internal key — this is intentional and
correct for this new, in-scope route only; the older routes are unchanged
pre-existing behavior, out of P4-002's scope. No filesystem path appears in
the URL, and the `does not expose the private storage path` test
(`MediaStreamingTest.php:166-181`) asserts the storage path string is absent
from both headers and body; independently re-verified the assertion is
substantive (it checks the real `$media->storage_path` value and the literal
string `'media/'`, not a placeholder). Path-traversal is not possible: the
value bound to `$mediaFile` is a UUID resolved via Eloquent's route-key
binding against the `uuid` column, never used to construct a filesystem path
directly in the controller (the controller only ever uses
`$mediaFile->storage_path`, a value fully controlled server-side at ingestion
time, never derived from request input).

## 5. Range Parsing — Independent Audit

Read `resolveRange()` in full and hand-traced every case listed in the review
brief against the implementation (not the tests):

| Input | Traced result | Correct? |
|---|---|---|
| `bytes=0-` | start=0, end=size-1, 206 | Yes |
| `bytes=N-M` (N≤M<size) | start=N, end=M, 206, length=M-N+1 | Yes |
| `bytes=N-` (N<size) | start=N, end=size-1, 206 | Yes |
| `bytes=-N` (suffix) | start=max(0,size-min(N,size)), end=size-1, 206 | Yes |
| `bytes=0-0` | start=0, end=min(0,size-1)=0, length=1 | Yes (first byte only) |
| `bytes=-0` | suffix=0 → explicit 416 branch | Yes (correct per contract's "suffix `N==0`" rule) |
| `bytes=-<size>` | min(size,size)=size, start=0 → whole file, 206 | Yes (RFC 7233-consistent) |
| `bytes=-<larger than size>` | min(N,size)=size, start=0 → whole file, 206 | Yes (RFC 7233-consistent fallback) |
| `bytes=N-M`, M≥size | `end = min(end, size-1)` clamps to size-1 | Yes (RFC 7233: last-byte-pos ≥ length ⇒ remainder of representation) |
| `bytes=N-M`, N>M | falls through to `$full` (200, range ignored) | Reasonable (RFC 7233 treats an inverted byte-range-spec as invalid, to be ignored); not explicitly enumerated in the task's HTTP Contract table, but consistent with adopted HTTP semantics — not a defect |
| `bytes=N-`, N≥size | explicit `>= $size` check → 416 | Yes |
| `bytes=<size-1>-` (open range at last byte) | start=size-1, end=size-1, length=1 | Yes |
| multi-range (`0-1,3-4`) | `str_contains($spec, ',')` → 200 ignored | Yes |
| non-`bytes=` unit / malformed | regex fails or prefix check fails → 200 ignored | Yes |

No negative or overflowing length is possible: `$length = max(0, $end - $start + 1)`, and `$end`/`$start` are always clamped within `[0, size-1]` before this line for the partial-content path.

**One latent, low-likelihood edge case found by trace (not by test):** if
`$size === 0` (a hypothetical zero-byte media object), the full-response path
computes `$end = max(0, $size - 1) = 0` and `$length = max(0, 0 - 0 + 1) = 1`,
so the `Content-Length` header would report `1` while the actual streamed
body would be `0` bytes (the `fread()` loop hits EOF immediately). This is a
genuine header/body mismatch for a zero-byte file. No upload-size-floor
validation was found in `app/Actions/MediaIngestionService.php` or
`config/media.php` that provably forecloses a zero-byte `MediaFile` row, so
this is not proven unreachable, but it is also not demonstrated reachable
through any current ingestion path, and it has no security consequence (no
path leakage, no cross-user exposure). Recorded as LOW-3.

## 6. Response Semantics and HEAD

Full/partial/416/ignored-range status codes, `Content-Type`, `Accept-Ranges`,
`Content-Length`, and `Content-Range` all match the contract exactly for
every code path traced in §5, and this matches the passing test assertions.

For HEAD, the review brief explicitly warns not to assume the GET/HEAD route
pairing is sufficient. Traced the actual framework behavior instead of
assuming:

- `Illuminate\Routing\Router::toResponse()` calls `$response->prepare($request)`
  unconditionally on every response (`vendor/laravel/framework/.../Router.php:946`).
- `Symfony\Component\HttpFoundation\Response::prepare()`, for a HEAD request,
  captures `Content-Length`, calls `$this->setContent(null)`, then restores
  the captured `Content-Length` header (`vendor/symfony/http-foundation/Response.php:283-290`).
- `StreamedResponse::setContent(null)` sets `$this->streamed = true`
  (`vendor/symfony/http-foundation/StreamedResponse.php:135-144`) — note this
  is the opposite of the naive assumption that `null` content leaves the
  stream flag unset.
- `StreamedResponse::sendContent()` short-circuits (`if ($this->streamed) return $this;`)
  before ever invoking the streaming callback (`StreamedResponse.php:113-127`).

Net effect: for a HEAD request, the controller still runs (so headers are
computed correctly, confirmed by the passing `answers HEAD requests with
range headers` test), but the `fseek`/`fread` closure is **never invoked** —
there is no full (or partial) media read for HEAD, satisfying the acceptance
criterion precisely, and this was confirmed via framework source, not via test
naming.

## 7. Bounded Streaming

Grepped `MediaActionController.php` for `Storage::get(`, `file_get_contents(`,
`stream_get_contents(` — no matches. Read the streaming closure in full: it
opens a stream (`readStream()`), seeks to `$start` once, then loops
`fread($stream, min(8192, $remaining))` decrementing `$remaining` by the
actual bytes read each iteration, stopping at `feof()` or an empty/`false`
read. `$remaining` is seeded from `$length`, which is itself clamped to the
resolved range — the loop structurally cannot emit more than the requested
range regardless of file size, and cannot spin forever on a failed read
(`$buffer === false || $buffer === ''` breaks the loop). No BLOCKER/HIGH
concern found here — this is the review's most safety-critical surface and it
is implemented correctly.

**Missing required test:** the task's own "Test Requirements" list explicitly
asks for "assertion that the whole-file read path is not used (bounded
streaming)." No such assertion (e.g., a `Storage` spy/mock expectation) exists
in `MediaStreamingTest.php`. The correctness claim above is fully supported by
direct source inspection, but the *test suite itself* does not enforce it
against regression. Recorded as LOW-1.

## 8. Seekability Assumption

The task's "Known Limitations" states `fseek()` assumes a seekable stream and
that the canonical disk is local. This matches the current, authoritative
Phase 2 storage contract (`MediaFile::storage()` resolves
`config('media.storage_disk')`, default `local`; no remote/S3-style adapter is
in scope anywhere in the current repository). This is an acceptable
current-scope limitation, not a contract violation — Phase 7 (Production
Hardening) is where remote-storage certification is reserved
(`PHASE4-PLANNING.md` §C). No finding.

## 9. Missing File / Error Handling

`abort(404, 'Physical file not found.')` when `storage_path` is empty or the
object doesn't exist on disk — confirmed by the passing `returns 404 when the
physical file is missing` test. The 404 response is Laravel's standard error
page; it does not echo `$mediaFile->storage_path`, the disk name, or any
internal exception detail. No leakage found.

## 10. Test Reproduction

Independently re-executed (not read from the report):

```
vendor/bin/pest tests/Feature/MediaStreamingTest.php
→ 13 passed, 61 assertions
```

Exact match to the implementer's reported figures. Inspected each test
individually: the range/full-response tests assert real byte content via
`streamedContent()` (not just headers/status), so a broken range-math
implementation (e.g., an off-by-one in `$end`/`$length`) would fail these
tests, not just a status-code check. Mutation-style reasoning: flipping
`$end - $start + 1` to `$end - $start` would break the `bytes=2-5` test's
`Content-Length: 4` and `streamedContent() === 'CDEF'` assertions
simultaneously; removing the `$start >= $size` guard would break the 416 test.
This gives confidence the passing tests are substantive, not merely
status-code theater.

## 11. Cross-Task Scope Audit

- No `<audio>`/`<video>` element, seek binding, or highlight logic (P4-003)
  found anywhere in the diff — confirmed by a full read of
  `resources/views/transcriptions/show.blade.php` (shared with P4-004) and a
  repository-wide grep for `media.stream` (only the route definition and this
  controller/test reference it).
- No schema/migration change (confirmed via `git status`; the untracked
  migrations present in the working tree are dated 2026-09-18/19 and belong to
  already-DONE Phase 3 Batch 2/3 work, not this task).
- Phase 2 private-storage contract unchanged: `MediaFile::storage()` and its
  `assertPrivateStorageDisk()` guard are untouched by this diff.
- P4-002's own footprint is exactly: `routes/web.php` (+1 route),
  `MediaActionController.php` (+`stream()`, +`resolveRange()`),
  `tests/Feature/MediaStreamingTest.php` (new). No unrelated functionality
  changed.

## 12. Shared Regression

Full suite independently re-run five times during this review session (see
the Wave 1 summary report for the consolidated figures): 422 tests, 421
passed, 1 pre-existing skip, 0 failures in every run. Assertion count
fluctuated between 1400 and 1402 across runs — this is a pre-existing
non-determinism somewhere in the full shared suite (not in P4-002's own
focused suite, which was independently re-run twice with an identical 61/61
assertion count both times), so it is not attributed to this task. See the
Wave 1 report §D for detail.

## 13. Findings Summary

| ID | Severity | Finding | Blocking? |
|----|----------|---------|-----------|
| LOW-1 | LOW | The task's own required test — "assertion that the whole-file read path is not used" — is missing from `MediaStreamingTest.php`. Behavior is independently confirmed correct by source inspection (no `Storage::get`/`file_get_contents`/`stream_get_contents`; bounded `fread` loop). | No |
| LOW-2 | LOW | No test exercises `bytes=0-0`, `bytes=N-M` where `M>=size`, `bytes=N-M` where `N>M`, `bytes=-0`, or a suffix range larger than the file. Independently hand-traced all five in §5 and confirmed RFC-7233-consistent, correct behavior for each. | No |
| LOW-3 | LOW | For a hypothetical zero-byte `MediaFile`, the full-response path would report `Content-Length: 1` while the actual streamed body is 0 bytes (header/body mismatch). No confirmed ingestion path produces a zero-byte `MediaFile`, and there is no security/leakage consequence. | No |

No BLOCKER, HIGH, or MEDIUM finding was identified.

## 14. Acceptance Criteria Assessment

All 19 acceptance criteria were checked against the traced control flow and
the independently reproduced tests (§3–§10 above); all are satisfied. AC14
(bounded streaming) and AC9/AC10 (unsatisfiable/malformed ranges) are
satisfied by source-code proof (§5, §7) even though AC's associated minimum
test list is incompletely implemented (LOW-1, LOW-2) — the review brief's
instruction to "not accept a test name... without reviewing source behavior"
is why this review treats the acceptance criteria as met on the strength of
the code trace, while still recording the test-coverage gap as a
non-blocking finding.

## 15. Final Verdict

```text
P4-002 = VERIFIED
```

No BLOCKER, HIGH, or MEDIUM finding. LOW-1, LOW-2, LOW-3 are non-blocking and
recorded as historical observations / candidate follow-up test hardening.
P4-002 is eligible to return to the Human Product Owner for closure
consideration. This review does not itself authorize P4-003.
