# AGENTS.md — OpenCode Quick Reference

## Tech Stack (exact versions)

- **PHP**: 8.4 (at `C:/Users/Admin/.config/herd/bin/php84/php.exe`)
- **Laravel**: 13.17+
- **Livewire**: 4.1+ with Flux UI 2.13+ and Blaze 1.0+
- **Tailwind CSS**: 4.0+ (via `@tailwindcss/vite`)
- **Auth**: Laravel Fortify (two-factor auth intentionally disabled)
- **Database**: SQLite (dev), in-memory for tests
- **Testing**: Pest 5.1
- **Build**: Vite 8.0+ with vite-plus
- **Static Analysis**: PHPStan level 7 (Larastan)
- **Formatter**: Laravel Pint (preset: laravel)

## Essential Commands

```bash
# Setup
composer setup                    # Full install + migrate + npm build
composer dev                      # Start server + queue + Vite concurrently

# Testing
php artisan test --compact        # Full test suite
vendor/bin/pest                   # Direct Pest runner
vendor/bin/pest tests/Feature/MediaIngestionTest.php  # Single file
vendor/bin/pest --filter=testName                        # Filter by name

# Code Quality (run after ANY PHP file change)
vendor/bin/pint --dirty --format agent    # Fix formatting
composer lint:check                       # Pint dry-run
composer types:check                      # PHPStan analysis

# Frontend
npm run build                     # Production build
npm run dev                       # Dev server with HMR

# Laravel
php artisan route:list            # Inspect routes
php artisan make:test --pest {name}  # Create Pest test
php artisan make:model --help     # Check model options
```

## Critical Gotchas

1. **Vite Manifest Error**: If you see "Unable to locate file in Vite manifest", run `npm run build` or ask user to run `npm run dev`.

2. **Pint Formatting**: ALWAYS run `vendor/bin/pint --dirty --format agent` after modifying PHP files. Do NOT use `--test` flag for fixing.

3. **Tinker Quoting**: Use single quotes to prevent shell expansion:
   ```bash
   php artisan tinker --execute 'User::where("active", true)->count();'
   ```

4. **Artisan Commands**: Always pass `--no-interaction` to non-interactive scripts.

5. **Test Creation**: Use `php artisan make:test --pest {name}` — do NOT include `Feature/` or `Unit/` in the name.

## Naming Discipline (enforced)

- Use **MediaFile** (not File) — avoids PHP/Laravel File ambiguity
- Use **ProcessingJob** (not Job) — avoids Laravel queue Job conflict

## Scope Boundaries

**Current Phase**: Phase 2 COMPLETE_WITH_DEFERRED_DEBT (closed 2026-09-17)

**Phase 3**: Real Transcription Engine (ADR-017). CLOSED (2026-09-19,
DECISION-PHASE3-CLOSURE-001).
Batch 1 CLOSED (P3-001/P3-002/P3-003 DONE; canonical model large-v3).
Batch 2 CLOSED (P3-004/P3-005/P3-006 DONE).
Batch 3 CLOSED (2026-09-19, DECISION-P3-BATCH3-CLOSURE-001; ADR-018):
P3-007 is DONE (HPO closure 2026-09-19); P3-008 is DONE (HPO closure 2026-09-19;
independent review VERIFIED, live Redis and real faster-whisper `large-v3` gates
passed). Phase 3 = CLOSED.

**Phase 4**: Transcript Experience baseline (ADR-019). CLOSED (2026-09-20,
DECISION-PHASE4-CLOSURE-001). P4-001..P4-006 = DONE (P4-004 corrective cycle
DECISION-P4-004-CORRECTIVE-CLOSURE-001; P4-006 DECISION-P4-006-CLOSURE-001;
DECISION-P4-006-FINDING-001 CLOSED). Boundary:
Phase 4 = Transcript Experience baseline; Phase 5 = Translation; Phase 6 =
Advanced Transcript UX; Phase 7 = Production Hardening.

**Phase 5**: Translation. AUTHORIZED FOR IMPLEMENTATION (2026-09-21,
DECISION-PHASE5-AUTHORIZATION-001; ADR-022 freezes D5-01..D5-09; ADR-021
authorizes cross-phase Playwright). DONE (HPO closures 2026-09-22): P5-001,
P5-001A, P5-002, P5-002A, P5-002B, P5-003, P5-004, P5-004B, P5-004C, P5-005,
P5-006, P5-007. P5-008 = IMPLEMENTED_PENDING_REVIEW (real self-hosted gate
executed after B-004 provisioning; independent review pending). Phase 5 is NOT
closed. Phase 3/4 baseline committed; B-001/B-002 resolved.

**Phase 6 / Phase 7**: NOT GENERALLY AUTHORIZED. Controlled Parallel Execution
Authorization (2026-09-21, ADR-023; `PHASE5-7-CONTROLLED-PARALLEL-EXECUTION.md`)
allows only the early-start (Phase 6) and early-hardening (Phase 7) allowlists
in `PHASE5-7-EXECUTION-CLASSIFICATION.md`. Phase 6 cannot close before Phase 5
is CLOSED; Phase 7 cannot close before Phase 6 is CLOSED; P7-012 must not run
before Phase 6 is CLOSED.

Phase 3 boundary (ADR-017):
- provider-neutral Laravel transcription domain
- Redis queue (without Horizon initially)
- authenticated internal Python worker (private/internal HTTP)
- shared private filesystem with opaque media references
- FFmpeg media preparation
- self-hosted faster-whisper inference
- transcript and segment persistence
- multilingual/code-switching (BM, English, Chinese, Tamil)
- retry, recovery, failure hardening
- real end-to-end integration verification

**Do NOT implement**:
- Phase 4 implementation beyond the closed Phase 4: Phase 4 is CLOSED
  (DECISION-PHASE4-CLOSURE-001); do not reopen Phase 4
- Phase 6/7 beyond their early-start/early-hardening allowlists
  (`PHASE5-7-EXECUTION-CLASSIFICATION.md`); do not treat the parallel-execution
  authorization as general Phase 6/7 authorization
- Phase 3 code outside the closed Batch 3 scope (P3-007, P3-008)
- P3-008 final integration verification before P3-007 is independently VERIFIED
- Automatic domain retry, Horizon, or provider-abstraction redesign
- AI features, billing, public registration

**Preserve these frameworks**: Laravel, Livewire, Blade, Flux UI, Tailwind, Fortify, Pest

## Testing Patterns

- Tests use `beforeEach(function () { Storage::fake('local'); })` for media tests
- Create models via factories: `User::factory()->create()`
- Use `actingAs($user)` for auth tests
- Assert redirects: `$response->assertRedirect(route('media.show', $mediaFile))`
- Feature tests in `tests/Feature/`, Unit in `tests/Unit/`
- FFprobe test auto-skips when ffprobe unavailable

## Code Style

- PHP 8 constructor property promotion
- Explicit return types and type hints
- Curly braces for all control structures
- TitleCase for Enum keys
- PHPDoc blocks preferred over inline comments
- Check sibling files for conventions before creating new ones

## Agent Model — OpenCode + Claude Code

### Roles

| Role | Agent | Responsibility |
|------|-------|----------------|
| **Builder** | OpenCode | Implement, test, investigate, move to REVIEW |
| **Reviewer** | Claude Code | Independent review, return VERIFIED / CHANGES_REQUESTED |
| **Decider** | Human Product Owner | Approve scope, architecture, close VERIFIED tasks |

### Task Lifecycle

```
READY → IN_PROGRESS → REVIEW → VERIFIED → DONE
         ↑              ↓
         └── CHANGES_REQUESTED (max 3 cycles, then BLOCKED)
```

### Rules

- **OpenCode**: Implements tasks, creates tests, moves to REVIEW. Must NOT mark own work VERIFIED or close own tasks.
- **Claude Code**: Independent reviewer. Produces durable review artifacts in `reviews/`. Returns VERIFIED or CHANGES_REQUESTED.
- **Human Product Owner**: Closes VERIFIED tasks, decides scope/architecture/phase, decides BLOCKED tasks.

### Three-Cycle Escalation

After 3rd CHANGES_REQUESTED → task becomes BLOCKED → Human Product Owner decides (reassign, defer, accept, or close).

## Key Configuration

- Media config: `config/media.php` (500 MiB limit, SHA-256 checksums)
- FFprobe path: `RTFTT_FFPROBE_PATH` env var
- Storage disk: `RTFTT_MEDIA_DISK` env var (default: local)
- Test env: SQLite in-memory, sync queue, array cache/session/mail

## Automated Orchestration (OpenCode <-> Claude Code handoff)

`scripts/orchestrate.mjs` watches `tasks/*.md` for `## Status` changes and
drives the State-to-Action Contract in
`.ai/guidelines/orchestration-policy.md` automatically:

- Status becomes `REVIEW` -> invokes `claude -p` to perform the independent
  review and write a `reviews/` artifact.
- Status becomes `CHANGES_REQUESTED` -> invokes `opencode run` to fix it,
  up to `maxChangeRequestedCycles` (default 3; the 3rd cycle escalates to
  BLOCKED and is logged for the Human Product Owner instead of retried).
- Status becomes `VERIFIED` or `BLOCKED` -> logged only. Closing VERIFIED as
  DONE, and deciding BLOCKED tasks, remain Human Product Owner actions and
  are never automated.
- READY/IN_PROGRESS/BACKLOG assignment is not automated by default
  (`autoAssignReadyTasks: false` in `scripts/orchestrate.config.json`).

Commands:

```bash
npm run orchestrate        # watch tasks/, dry-run (logs only, spawns nothing)
npm run orchestrate:once   # scan every task file once, dry-run, exit
npm run orchestrate:live   # watch and actually spawn claude/opencode
```

`orchestrate:live` requires `ORCHESTRATE_UNATTENDED=1` as a second, explicit
opt-in, because it runs `claude` with `--permission-mode acceptEdits` and
`opencode` with `--auto` so they can proceed without a human approving every
tool call. Logs and per-task cycle counts are written to
`storage/orchestration/` (gitignored).

## Git Safety

- Inspect `git status` before implementation
- Do not overwrite unrelated uncommitted changes
- Do not force-push or rewrite shared history
- Prefer isolated branches for parallel work
