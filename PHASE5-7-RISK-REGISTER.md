# Phase 5–7 — Risk Register

Date: 2026-09-20
Status: PLANNING ONLY — NOT AUTHORIZED FOR IMPLEMENTATION
Authority: `PHASE5-PLANNING.md`, `PHASE6-PLANNING.md`, `PHASE7-PLANNING.md`,
`PHASE5-7-WAVE-PLAN.md`, `PHASE5-7-DEPENDENCY-GRAPH.md`,
`PHASE5-7-DECISION-REGISTER.md`

Likelihood / Impact scale: LOW, MEDIUM, HIGH.
"Owner decision required" references the register ID (D5-*, D6-*, D7-*, DC-*).

## Register

| # | RISK | PHASE | LIKELIHOOD | IMPACT | MITIGATION | OWNER DECISION | BLOCKS WHAT |
|---|---|---|---|---|---|---|---|
| R-01 | Translation quality below product expectation, especially Bahasa Melayu + code-switch | 5 | MEDIUM | HIGH | Real-model gate (D5-09); benchmark/fixture matrix; acceptance criteria in P5-001; provider comparison | D5-04, D5-09 | P5-004, P5-008, Phase 5 closure |
| R-02 | Provider/runtime cost and capacity of self-hosted translation | 5, 7 | MEDIUM | MEDIUM | RTF-style measurement; capacity gate in P7-009; provider seam enables future hosted option | D5-04, D7-08 | P5-004, P7-009 |
| R-03 | Mixed-language / code-switch alignment drift | 5 | MEDIUM | HIGH | Segment-aligned contract (D5-01); preserve index/timestamps/source marker; multilingual fixtures | D5-01 | P5-001, P5-002, P5-008 |
| R-04 | Model latency / long transcripts causing timeouts | 5 | MEDIUM | MEDIUM | Grouped-context policy with bounded windows; provider timeout contract; stale recovery | D5-01, D5-09 | P5-004, P5-005 |
| R-05 | UX complexity / scope explosion across comparison, editing, waveform | 5, 6 | HIGH | MEDIUM | Classification IN/DEFER/OUT; explicit non-scope; owner decisions gate inclusion | D5-07, D6-06, D6-08, D6-09 | P5-006, P6-007, P6-008 |
| R-06 | Edit/translation synchronization: edits silently misalign translations | 6 | MEDIUM | HIGH | Stable segment identity + explicit translation invalidation (D6-04); immutable layer (D6-01) | D6-01, D6-04 | P6-005, Phase 6 closure |
| R-07 | Schema evolution risk (translations, edit layer, stable segment keys) | 5, 6, 7 | MEDIUM | MEDIUM | Additive migrations only; contract-first (P5-001/P6-001); no historical migration edits | D5-05, D5-06, D6-01 | P5-002, P6-002 |
| R-08 | SQLite scaling / write-concurrency limits in production | 7 | MEDIUM | HIGH | D7-01 decision; load evidence; documented ceiling or PostgreSQL migration | D7-01 | P7-002, P7-009, P7-012 |
| R-09 | Storage scaling + range/seekability regression (S3/object storage) | 7 | MEDIUM | HIGH | Hybrid storage plan; range-stream tests; zero-byte/range-edge coverage; no full-object PHP load | D7-03 | P7-004, P7-009 |
| R-10 | Queue recovery gaps: stuck/abandoned jobs, no dead-letter visibility | 7 | MEDIUM | HIGH | Redis certification; supervisor; stale-attempt recovery; failed-job visibility; dead-letter policy | D7-02 | P7-003, P7-007 |
| R-11 | Browser automation flakiness erodes verification trust | 5, 6, 7 | HIGH | MEDIUM | Cross-phase browser ADR (DC-01); deterministic waits; supported matrix; fix V4-08 flake | DC-01, D7-05 | All browser gates; P7-010 |
| R-12 | Production concurrency limits on transcription/streaming | 7 | MEDIUM | HIGH | Load targets (D7-08); worker scaling; capacity evidence before gate | D7-08 | P7-009, P7-012 |
| R-13 | Large-file runtime (500 MiB upload/FFprobe/transcription) unproven in prod stack | 2 debt, 7 | MEDIUM | HIGH | Deferred deployment requirement test in deployed stack; large-file validation P7-009 | D7-08 | P7-009, P7-012 |
| R-14 | Security/privacy exposure (headers, CSP, rate limiting, upload abuse, secrets) | 7 | MEDIUM | HIGH | Security hardening P7-006; env validation P7-001; dependency audit; malware decision | D7-04, D7-06 | P7-006, P7-012 |
| R-15 | Retention/deletion and derived-artifact policy undefined | 7 | HIGH | MEDIUM | D7-06 decision before deletion; cleanup task P7-011; legal/privacy validation | D7-06 | P7-011, P7-012 |
| R-16 | Backup/restore or rollback unproven | 7 | MEDIUM | HIGH | Restore drill; rollback evidence; DR runbook; RPO/RTO decision | D7-07 | P7-007, P7-008, P7-012 |
| R-17 | Observability gaps (no structured logs/metrics/health) | 7 | MEDIUM | MEDIUM | P7-005; minimum correlation fields from ADR-017 | — | P7-005, P7-012 |
| R-18 | Option D abandoned-staging cleanup debt persists | 2 debt, 7 | MEDIUM | LOW | P7-011 cleanup task requires separate authorization; Option D stays in force | D7-06 + explicit Option D decision | P7-011 |
| R-19 | Actor-vs-owner / tenancy semantics undefined at production | 7 | MEDIUM | HIGH | DC-02 decision before gate if multi-user/admin-on-behalf-of is in scope | DC-02 | P7-006, P7-012 |
| R-20 | Pre-existing browser JS error (`showRenameModal`) | 4 debt, 7 | MEDIUM | LOW | Fix in P7-010 with regression test | — | P7-010 |
| R-21 | Translation source-language `und` policy ambiguity | 5 | MEDIUM | MEDIUM | Explicit contract in P5-001 (translate-with-autodetect vs passthrough vs fail) | D5-01 (sub-policy) | P5-001, P5-002 |
| R-22 | Cross-phase scope creep (P6/P7 absorbing P5 work) | 5, 6, 7 | MEDIUM | MEDIUM | Dependency graph + non-scope lists + per-phase authorization | — | Wave authorization clarity |
| R-23 | Over-claiming mocks as real integration | 5, 6, 7 | MEDIUM | HIGH | Real-model/browser gates; explicit evidence classification in test strategy | D5-09, DC-01 | P5-008, P6-009, P7-012 |

## Risk Detail (material risks)

### R-01 — Translation quality
The product's core value is natural Bahasa Melayu Malaysia output and preserving
meaning under code-switching. A self-hosted model may underperform on `ms` or
mixed input. Mitigation must be evidence-based: a real-model gate with a
representative fixture (including a code-switch sample) and explicit acceptance
criteria authored in P5-001. This risk cannot be retired on mock tests.

### R-06 — Edit/translation synchronization
If Phase 6 edits segments while translations reference segment indexes, any
split/merge or timing change can silently misalign derived text. The immutable
edit layer (D6-01) plus explicit translation invalidation (D6-04) is the
designed control. Deferring D6-04 does not remove the risk; it only defers the
control.

### R-08 / R-09 / R-10 — Production infrastructure
These three risks dominate Phase 7 because the current runtime is
development-oriented (SQLite, database queue default, local private disk). Each
requires a decision (D7-01/02/03) and evidence before the production gate.
Planning must not assume the choice; each option has different verification.

### R-11 — Browser flakiness
The Phase 4 record retains an intermittent V4-08 timing flake. Since Phase 5–7
rely more heavily on browser behavior (translation UI, editing, production
browser matrix), flakiness must be eliminated and browser verification must be
governed by DC-01; otherwise gate verdicts become non-reproducible.

### R-23 — Evidence integrity
The repository's governance distinguishes unit/feature/mock evidence from real
integration evidence (ADR-018 B3-06/B3-07). Phase 5–7 must classify evidence
explicitly and must not represent mocks as real provider/model/browser
integration. This is both a technical and a governance risk.

## Test-Evidence Classification (per phase)

| Evidence type | P5 | P6 | P7 |
|---|---|---|---|
| Unit | required | required | required |
| Feature | required | required | required |
| Integration (real provider/model) | required for canonical target(s) (D5-09) | n/a (edit layer is app-internal) | required |
| Browser (real) | required (P5-006, P5-008) | required (editing gate) | required (matrix, gate) |
| Real FFmpeg/media | not required for translation (text-only); regression | regression | required (large-file) |
| Real transcription worker | regression | regression | required |
| Real translation provider/model | required (D5-09) | regression | required |
| Concurrency | required (retry claim; ADR-013/016 precedent) | required if concurrent edits considered | required (queue/DB/stream) |
| Performance / load | basic latency | basic render | required (P7-009) |
| Production smoke | n/a | n/a | required (P7-012) |

## Explicit Non-Actions

- This register asserts no decisions and authorizes no work.
- Owner-decision references are OPEN/CANDIDATE only.
- Phase 5/6/7 implementation remains NOT AUTHORIZED.