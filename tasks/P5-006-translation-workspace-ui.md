# P5-006 — Translation Workspace UI

## Status

BACKLOG — requires HPO READY promotion.

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