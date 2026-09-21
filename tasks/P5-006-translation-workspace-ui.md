# P5-006 — Translation Workspace UI

## Status

IMPLEMENTED_PENDING_REVIEW — 2026-09-21. Promoted and authorized by the HPO
instruction of 2026-09-21 ("implement P5-006 after the pre-UI hardening"). Independent
review pending. Not VERIFIED; not DONE. P5-008 has not been started.

Implementation Owner for this task: Claude Code (explicit HPO reassignment). The same
agent performed the preceding independent review, so **an independent reviewer that did not
implement it (a fresh session) is required.**

## Implementation Notes

- Dedicated workspace page `GET /transcriptions/{transcription}/translations` (target tabs,
  selector, progress, failure/retry, completed view with Original/Translation toggle, copy,
  TXT/SRT/VTT/DOCX export). Linked from the transcript page for completed transcripts only.
- Actions: `POST /transcriptions/{transcription}/translations` (start),
  `POST /translations/{translation}/retry`, `GET /translations/{translation}/status` (polling).
  All in `routes/translation.php`; `routes/web.php` was not touched.
- Authorization at the controller/form-request layer (`TranscriptionPolicy`), before any
  service call; retry then reloads the persisted row and evaluates eligibility on it.
- Non-retryable failures: no Retry action, a clear user-safe message from
  `TranslationFailure::userMessage()`, and the persisted failure code shown as a reference.
- Evidence: `reviews/pre-review/P5-006-pre-review.md`,
  `P5-006-BROWSER-VERIFICATION-EVIDENCE.md`. The translation worker used in the browser run is
  a deterministic test double; the real provider/model gate remains P5-008.

## Ownership

Implementation Owner: OpenCode
Reviewer: Claude Code

## Authorized Phase

Phase 5 — Translation (ADR-022)

## Objective

Deliver the baseline translation experience inside the existing transcript
workspace (D5-07): target-language selection, translate action, progress,
failed/retry surface, source/translated toggle, and copy of translated text.
No side-by-side comparison (Phase 6).

## Scope

1. Target-language selector for `ms`, `en`, `zh`, `ta`.
2. Translate action enqueuing translation for the selected target.
3. Progress state reflecting `queued`/`translating`.
4. Failed + manual retry surface (consumes P5-005).
5. Original/translated toggle reusing Phase 4 workspace primitives.
6. Copy translated full text / segment (Unicode-safe).
7. Translated export entry points (consumes P5-007).
8. Browser verification using Playwright under ADR-021.

## Non-Scope

- side-by-side comparison (Phase 6, D6-06);
- editing (Phase 6);
- waveform/timeline;
- any change to source transcript rendering semantics.

## Dependencies

- P5-002 DONE; P5-005 DONE; P5-007 DONE.

## Acceptance Criteria

1. User can select a target and initiate translation.
2. Progress and failure states are accurate and accessible.
3. Toggle switches between source and translated without mutating source.
4. Copy works for full translated transcript and per-segment.
5. Unicode (`ms`, `en`, `zh`, `ta`) round-trips verbatim.
6. Ownership isolation holds; cross-user access denied.
7. Browser evidence retained under ADR-021; tests, Pint, PHPStan pass.

## Verification Requirements

Feature tests + real browser evidence artifact.

## Expected Reviewer

Claude Code.

## Browser Evidence Required

Yes (ADR-021).

## Owner Decision Dependencies

D5-02, D5-07, D5-08 (frozen); DC-01 resolved (ADR-021).