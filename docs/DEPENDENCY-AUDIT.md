# Dependency Audit — P7-006 Baseline (2026-09-26)

Executed by the builder during P7-006 implementation; retained as the
AC5 evidence baseline. Re-run on every dependency change; CI fails on
new advisories (see `.github/workflows/tests.yml`, Dependency audit
step).

## composer audit (2026-09-26)

Command: `composer audit --format=plain`

Result: **No security vulnerability advisories found.**

## npm audit (2026-09-26)

Command: `npm audit --omit=dev`

Result: **found 0 vulnerabilities.**

## Triage

| Finding | Disposition | Expiry |
|---|---|---|
| (none — both audits clean) | No fix / upgrade / risk acceptance required | Re-run per change; full re-audit at P7-012 |

No HPO-accepted risk exists, so no expiry applies. If a future audit
reports an advisory, record here one row per advisory with disposition
`fix` (version + PR), `upgrade` (planned version), or `HPO-accepted
risk` (decision ID + review date, after which the audit step fails
again until re-decided).
