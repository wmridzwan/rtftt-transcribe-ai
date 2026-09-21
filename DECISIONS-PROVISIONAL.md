# Provisional Decisions

This file records provisional implementation decisions made under the Phase 5–7
Controlled Parallel Execution Authorization (§18). A provisional decision must
be local, reversible, non-contractual, non-persistent in architectural meaning,
non-security-sensitive, and non-tenancy-sensitive.

A provisional decision may **not** determine: translation ownership; translation
invalidation; revision/translation relationships; datastore architecture;
storage architecture; tenancy; authorization semantics; retention semantics;
external API contract; or irreversible schema assumptions. Those require an
explicit Human Product Owner decision.

| # | Date | Task | Decision | Rationale | Reversible? | Superseded by |
|---|---|---|---|---|---|---|
| — | — | — | No provisional decisions recorded yet. | — | — | — |

## Rules

1. Record every provisional decision here before relying on it.
2. If a decision turns out to be contractual, stop and raise a blocker in
   `BLOCKERS.md` requesting an HPO decision instead.
3. A provisional decision is automatically invalid once a relevant durable ADR
   or HPO decision is recorded.