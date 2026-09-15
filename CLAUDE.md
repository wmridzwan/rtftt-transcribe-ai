<laravel-boost-guidelines>
=== .ai/ai-development-os rules ===

\# AI Development Operating System

\## Purpose

This repository may be worked on by multiple AI coding agents including

Claude Code and OpenCode.

The repository is the shared source of truth between agents.

Do not rely on another agent's chat history or summary when repository

state provides the answer.

\## Source of Truth

Interpret project information in this order:

1\. The current explicit user instruction or Human Product Owner decision.

2\. `AGENTS.md` and the canonical State-to-Action Contract in
   `.ai/guidelines/orchestration-policy.md` for agent authority and lifecycle
   behavior.

3\. Accepted ADRs in `DECISIONS.md` for durable product, architecture,
   security, and workflow decisions. An ADR does not authorize execution by
   itself.

4\. `plan.md` for authorized roadmap and phase scope.

5\. `CURRENT_STATE.md` for current operational state, gates, and status.

6\. The relevant task file for task-level scope and acceptance criteria.

7\. `architecture.md` for repository architecture within accepted decisions
   and the authorized plan.

8\. Source, schema, tests, configuration, CI, and Git history as evidence of
   what exists. Evidence does not create authorization by itself.

9\. `PROJECT_CONTEXT.md` and other future-looking material for context only.

10\. Framework and package guidelines.

Future ideas and proposed external governance documents are not
implementation authorization until they are reconciled into repository-native
artifacts and explicitly approved.

\## Canonical Agent Architecture

The repository follows a two-agent operating model — **OpenCode** (Builder) and **Claude Code** (Reviewer) — with the **Human Product Owner** as the decision-maker and task closer. One implementation task must have only one active implementation owner.

### OpenCode — Builder

OpenCode is the implementation agent. OpenCode implements the assigned task, creates/updates tests, runs verification (tests, lint, typecheck), and moves the task to REVIEW. OpenCode must not mark its own work VERIFIED, close its own tasks as DONE, expand product scope, make Human Product Owner decisions, or begin unrelated tasks automatically.

### Claude Code — Independent Reviewer

Claude Code is the independent reviewer. Claude reviews implementation correctness, verifies acceptance criteria, inspects architecture and security boundaries, assesses regressions and edge cases, and produces durable review artifacts. Claude returns VERIFIED or CHANGES_REQUESTED. Claude must not modify implementation code during review, become the implementation owner, review its own implementation, or make product decisions.

### Ridzwan / Human Product Owner — Decider

The Human Product Owner owns decisions involving product scope, materially different UX behavior, major architecture, security-sensitive decisions, destructive operations, production deployment, milestone acceptance, phase completion, and phase authorization. The Human Product Owner closes VERIFIED tasks as DONE and decides BLOCKED tasks. Agents may provide evidence and recommendations. Agents must not silently make these decisions. Unresolved decisions belong in DECISION_QUEUE.md. Resolved durable decisions belong in DECISIONS.md.

### Repo — Remember

The repository is the shared durable memory and source of truth. Routine agent-to-agent communication must happen through repository artifacts rather than requiring manual relay between agents. Chat history is not the canonical project state when repository state exists.

A role may be changed explicitly for a specific task.

The canonical State-to-Action Contract (who acts on each task lifecycle state) is defined once in `.ai/guidelines/orchestration-policy.md`; this file cross-references it rather than duplicating the table.

\## Task Ownership

One implementation task must have only one active implementation owner.

Do not modify another agent's active task unless explicitly reassigned.

Before editing code:

1\. Read CURRENT\_STATE.md.

2\. Read the relevant task specification.

3\. Inspect git status.

4\. Read applicable project rules.

5\. Confirm the work is authorized by plan.md.

\## Task Lifecycle

Allowed states:

\- BACKLOG

\- READY

\- IN\_PROGRESS

\- REVIEW

\- CHANGES\_REQUESTED

\- VERIFIED

\- DONE

\- BLOCKED

Normal flow:

READY -> IN\_PROGRESS -> REVIEW -> VERIFIED -> DONE

If review finds blocking issues:

REVIEW -> CHANGES\_REQUESTED -> IN\_PROGRESS -> REVIEW

\## Implementation Agent

The implementation owner must:

1\. Work only within the assigned task scope.

2\. Follow architecture.md and DECISIONS.md.

3\. Add or update appropriate tests.

4\. Run relevant verification.

5\. Update the task file with implementation notes.

6\. Move the task to REVIEW when implementation verification passes.

7\. Update CURRENT\_STATE.md when project state changes.

The implementation agent must not mark its own implementation VERIFIED.

\## Independent Reviewer

The reviewer should not modify implementation code unless explicitly

reassigned as the implementation owner.

Review:

\- correctness

\- acceptance criteria

\- architecture compliance

\- regressions

\- authorization and ownership boundaries

\- security

\- edge cases

\- performance where relevant

\- test adequacy

Write durable review results under reviews.

Formal independent review is based on independent reconstruction of evidence,
not vendor or model identity alone. A fresh review context or session that did
not implement the artifact is preferred. Review artifacts must distinguish CI
evidence, implementer-reported results, and commands reproduced by the
reviewer. Do not claim reviewer reproduction when the command was not run.
Read-only tests and static analysis may be executed when the environment
permits it.

Finding severity:

\- BLOCKER

\- HIGH

\- MEDIUM

\- LOW

BLOCKER or HIGH findings prevent VERIFIED status.

\## Verification

A task may become VERIFIED only after:

\- relevant tests pass

\- required formatting and static analysis pass

\- acceptance criteria are satisfied

\- no unresolved BLOCKER or HIGH findings remain

VERIFIED does not authorize production deployment.

\## Human Decision Gates

Stop and request human input when work requires:

\- changing product scope

\- changing a major architecture decision

\- choosing between materially different product behaviors

\- destructive database or production actions

\- security-sensitive external access

\- production deployment

\- milestone UX or product acceptance

Routine implementation, review, and fix cycles do not require human

intervention.

\## Durable Handoffs

Agents communicate through repository artifacts:

\- plan.md - authorized roadmap

\- CURRENT\_STATE.md - current operational state

\- tasks/ - implementation contracts

\- reviews/ - independent review results

\- DECISIONS.md - durable product and engineering decisions

\- Git history - implementation history

Important project information must not exist only in chat output.

The user should not need to manually relay routine implementation

summaries or review findings between agents.

\## Git Safety

Before implementation:

\- inspect git status

\- do not overwrite unrelated uncommitted changes

\- do not force-push

\- do not rewrite shared history

\- do not delete branches or worktrees without authorization

Prefer isolated branches or worktrees for parallel implementation.

\## Scope Discipline

Do not automatically start another task, module, or phase merely because

the current task is complete.

Continue automatically only within work explicitly authorized by the

current task or instruction.

Future phases remain unauthorized until plan.md or the user authorizes them.

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>

## Orchestration Governance

The shared orchestration policy is [`.ai/guidelines/orchestration-policy.md`](.ai/guidelines/orchestration-policy.md). The full State-to-Action Orchestration Contract lives there. Key rules:

- **REVIEW is Claude Code's responsibility.** A task in REVIEW must be routed to Claude Code for independent review.
- **CHANGES_REQUESTED preserves ownership.** OpenCode fixes its own CHANGES_REQUESTED items; there is only one implementation agent.
- **VERIFIED does not mean DONE.** After Claude Code returns VERIFIED, the Human Product Owner must close the task as DONE.
- **BLOCKED is scoped.** Unrelated authorized runnable work continues.

[DECISION_QUEUE.md](DECISION_QUEUE.md) is the durable location for unresolved Human Product Owner decisions. A blocked task does not block unrelated runnable work; READY tasks with satisfied dependencies continue, and only affected tasks become BLOCKED. Product, UX, architecture, security, destructive-operation, production, phase-completion, and phase-authorization decisions belong to the Human Product Owner. Phase authorization remains a Human Product Owner gate.
