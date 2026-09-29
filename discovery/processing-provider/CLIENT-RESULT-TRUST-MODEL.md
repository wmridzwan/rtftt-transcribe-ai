# DISC-I — Client trust model (options only, no selection)

Research date: 2026-09-27. Read-only repo analysis. No product decision.

## I01/I02 — Binding options (FACT patterns → INFERENCE options)

FACT reusable patterns: MediaFile `user_id` + UUID `upload_attempt_id` + server-computed `checksum_sha256` (`^[0-9a-f]{64}$`, 1 MiB streaming chunks; client checksum never trusted); `findByAttempt` idempotency, cross-user reuse → 403; guarded `UPDATE WHERE held_by` claim + expiry (`StagingClaim`: user/attempt/path/held_by/claimed_at/expires_at+24h); transcription claim (transcriptionId+attemptId match, terminal skip, newer-attempt guard, CAS Queued→Running); translation claim (token-fenced CAS + token-fenced fail); writer fences (ownership equality, `authorize('update')`, lockForUpdate, stale-authority no-op, duplicate-index checks, ±0.0005s timing check, allowlist `LanguageIdentifier` → `und`); revision anchor `active_revision_id → version/parent`; `contract_version=1.0` + server-controlled model.

INFERENCE options for a future client result POST (no selection):
(a) server-issued opaque per-request job token, all mutations `WHERE token=…`; (b) checksum binding (`media_file_id` + `checksum_sha256` + `upload_attempt_id`, mismatch→reject); (c) single-use nonce + `expires_at`; (d) one-time accept claim row (`held_by`, `expires_at`), race→retryable reject; (e) `contract_version` reject-unknown; (f) ownership re-assert at write (`media.user_id===transcription.user_id===request.user`); (g) monotonic revision guard (accept only stated `active_revision_id`/version; superseded→no-op/reject).

## I03 — Threat model (FACT policies → mitigation directions)

FACT: owner-or-admin policies, `authorize('view'/'update')`, server-side taxonomy only, IDs-only queue payloads, `tries=1` manual retry.

| Threat | Mitigation direction |
|---|---|
| Fabricated result | token + checksum + schema version + server-side count/index/timestamp validation |
| Oversized payload | server byte/count caps before persistence |
| Replay | token-fenced CAS + idempotent same-token return / different-token throw |
| Cross-account injection | authorize + (user,attempt) scoping + user_id equality at write |
| Altered timestamps | tolerance check vs authoritative segments or reject; copy timing from source |
| Invalid language | allowlist only, unknown→`und` |
| Stale result | newer-attempt/revision guard → no-op; P6-005 staleness precedent |
| Duplicate submit | same-token idempotent return; same-attempt returns existing row |

Max result sizes, exact caps, token lifetimes: UNKNOWN — require later contract + HPO trust-model decision.
