# Local Private-Storage Topology — Production Contract (P7-004)

Binding: D7-03 local private storage (object storage deferred, NOT
rejected — no object-storage code, config, or dependency exists in
this task's diff; `StorageTopology` fails closed on any non-local
driver). This document defines the storage truth once; P7-007's
manifest and P7-011's purge consume it without redefining it.

## 1. Topology

- Backend: single local private disk — `media.storage_disk`
  (`RTFTT_MEDIA_DISK`, default `local`; advisory in the P7-001
  registry, owner P7-004).
- Private boundary: `MediaFile::assertPrivateStorageDisk()` (P2-002A)
  rejects public visibility and public-root sharing at every
  `MediaFile::storage()` access; re-proven by
  `StorageCompatibilityTest` (disk driver `local` + assertion green).
- Identities: opaque UUID-based paths under the `media/` directory;
  no user identity or original filename in the path (P2-002B,
  unchanged).
- Staging (`media/.staging`) vs durable (`media/`) separation
  unchanged; quarantine (`quarantine/`, P7-006) is excluded from the
  servable tree AND from backup mirrors (proven by the quarantine
  compat test against `BackupManager`).

## 2. Streaming / range contract (re-proven, one explicit fix)

- Seekable-file delivery; range GETs never load the full object into
  PHP memory (8 KiB bounded chunks; 1 MiB bounded-delivery test).
- Matrix green: full 200, `0-`/`N-M`/`N-`/suffix 206 with exact
  `Content-Range`/`Content-Length`, clamped over-long ends, oversized
  suffixes, single-byte ranges, 416 + `bytes */N` marker for
  unsatisfiable/zero-length-suffix ranges, malformed/multi ranges  ignored as full 200, auth/404/MIME/HEAD/no-leak behavior intact
  (pre-existing `MediaStreamingTest` + new `MediaRangeEdgeTest`).
- Zero-byte disposition (TD-011, reachable: empty uploads ingest as
  0-byte rows): explicit branch serves an honest empty 200
  (`Content-Length: 0`) and 416s every range. Fixed a real defect
  where the generic path claimed `Content-Length: 1` for a 0-byte
  object (`MediaActionController::stream`).

## 3. Durability (single-node limits, documented)

- No replication: one node, one volume. Durability rests on (a) the
  filesystem, (b) daily P7-007 backup mirrors with integrity
  verification, (c) fail-closed writes (no silent partial objects;
  P2-002B compensation preserved).
- fsync discipline: application writes go through Laravel Flysystem
  local adapter (atomic renames where the adapter provides them);
  crash-mid-write artifacts are staging/orphan candidates handled by
  existing compensation, never adopted.

## 4. Capacity / health

- Floor: `deployment.min_free_bytes` (default 1 GiB), evaluated by
  `StorageTopology` (P7-001's `ProductionPostureChecks` capacity rule
  already enforces the same floor at boot; this task reports it, never
  forks it).
- Scripted procedure: `storage:validate-topology` (driver, root,
  writability, floor) — exit 0/1, retained output; surfaced additively
  in `deployment:verify` (`Topology:` lines) and
  `observability:diagnostics` (`Storage disk:`/`Storage root:`).
- Degraded behavior: unwritable/full/degraded storage fails closed
  with structured errors; health signals surface through verify +
  diagnostics (additive sub-checks; pre-existing checks unbroken).

## 5. Revision / export compatibility

- Revision-aware export suite green; prepared-audio retention
  re-validated; translation artifact paths untouched; P7-006 scan path
  and P7-007 manifest semantics compatibility-tested (no behavior
  change on either surface).

## 6. P7-009 handoff (storage limits the capacity run consumes)

- Single-node local volume; 1 GiB free-space floor; 8 KiB stream
  chunks; no concurrent-write scaling claims; quarantine excluded
  from servable + backup trees. G-01/G-10 proof stays with P7-009;
  nothing here substitutes the gate run.
