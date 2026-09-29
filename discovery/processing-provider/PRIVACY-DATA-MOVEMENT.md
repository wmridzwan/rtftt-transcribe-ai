# DISC-J + D03 — Privacy, consent, 500MiB/browser

Research date: 2026-09-27. FACT = repo-verified; INFERENCE = reasoned; UNKNOWN = needs benchmark/contract.

## J01 — Data-movement matrix

FACT today (server self-hosted): worker `http://localhost:8000` + bearer; translation text-only, no media bytes (`HttpTranslationProvider`); ClamAV loopback-only; CSP no external scripts; logs/backups node-local. Modes Auto/ThisDevice/Server/Cloud/LocalOnly DO NOT EXIST in repo.

| Artifact | Server=today (FACT) | ThisDevice/LocalOnly (would require) | Auto/Cloud (would require) |
|---|---|---|---|
| Original media | leaves device → private disk, staged→durable, server checksum | must not leave device; local inference+storage | leaves device by definition; needs destination + consent |
| Prepared audio/chunks | server-side, `prepared_audio_retention='ephemeral'` (lifecycle UNKNOWN) | local-only temp + eviction proof | leaves device if prepared server-side; per-chunk binding |
| Transcript | server-persisted | stays local or explicitly synced | leaves on sync/export |
| Translation | self-hosted text-only | local model or explicit opt-in | hosted = new egress, needs ADR + consent |
| Metadata | server DB + local logs | telemetry-shaped metadata must not emit | cloud sync/backup of metadata is egress |

## J02 — Local/Private minimum (INFERENCE proposal, HPO decision required)

No Local/Private definition exists (UNKNOWN). Proposed minimum: no media/bytes/text leave device; no external inference/translation/scan; no telemetry/crash payload with content or filenames; backups encrypted local-only or excluded; verifiable by egress audit (loopback-only URLs, CSP, no external endpoints).
Risks: structured JSON logs carry ids/timings (node-local FACT; forwarder via LOG_STACK/papertrail/slack env UNKNOWN); `report($exception)` in upload controller (destination UNKNOWN); backup dir node-local 7 generations (FACT) — any off-host copy = full egress; hosted translation switch = new egress class (per config comment).

## J03 — Fallback consent options (no selection)

No consent construct exists. Options: (a) never fall back (fail closed); (b) ask each time (default deny); (c) auto-allowed within stated boundary; (d) account-default preference. HPO selection required later.

## D03 — 500MiB vs browser (FACT constraints → INFERENCE limits)

FACT: limit 524,288,000 exact, double-enforced server-side; upload UI single whole-file XHR+FormData, progress only, no slice/chunk/resume; retry resubmits same attempt (idempotent server-side, resends all bytes); server streams (1 MiB hash, stream copy); no WASM FFmpeg/IndexedDB/OPFS/chunked-upload code in `resources/`.
INFERENCE: 500 MiB whole-file POST risks PHP/proxy limits + timeouts (server php.ini UNKNOWN); interruption restarts from zero; in-browser transcode needs 2–3× peak memory — likely infeasible low-end; chunked/resumable needs new endpoint + per-chunk checksum + reassembly + revised claim model; local 500 MiB persistence needs quota + eviction policy (none exists).
