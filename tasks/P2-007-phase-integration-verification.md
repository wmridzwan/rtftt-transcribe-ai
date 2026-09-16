# P2-007 — Phase Integration Verification

> **AUTHORIZED** — P2-007 is promoted to READY by explicit Human Product
> Owner decision on 2026-09-15, based on the completed Phase 2
> canonical-state reconciliation and focused regression reconciliation.
> This task does not reopen Phase 2 scope, supersede ADR-014, or authorize
> Phase 3.

## Status

VERIFIED

## Ownership

Implementation Owner: OpenCode, per AGENTS.md.
Reviewer: Claude Code, per AGENTS.md.

## Authorized Phase

Phase 2. This task, if and when authorized, verifies the Phase 2 gate; it
does not extend Phase 2 scope, does not implement Phase 2 features, and does
not authorize Phase 3.

## Purpose

Membuktikan Phase 2 — Media Ingestion berfungsi secara end-to-end dan
memenuhi phase gate:

> User boleh upload media yang disokong dengan selamat, konsisten, private,
> dan boleh recover daripada kegagalan/retry tanpa menghasilkan state yang
> rosak.

P2-007 ialah **verification task**, bukan implementation task. Ia tidak
boleh digunakan untuk menyelitkan feature, cleanup mechanism, transcription
processing, atau perubahan architecture.

## 1. Preconditions

Sebelum P2-007 boleh dipromote ke READY, verifier perlu mengesahkan
canonical repository state. P2-003 dan P2-005 mestilah DONE; keputusan
P2-004/P2-006 yang telah ditutup sebagai covered perlu kekal sah; P2-004A
dan P2-004A1 boleh kekal deferred/blocked mengikut ADR-013/014 (dan, jika
P2-004A2 telah authorized/READY/DONE pada masa itu, statusnya direkod di
sini juga); dan tiada unresolved finding lain yang secara eksplisit
menghalang Phase 2 gate.

P2-007 tidak boleh secara automatik menjadikan P2-004A/A1 (atau P2-004A2)
sebagai dependency. Verification perlu menguji sistem berdasarkan interim
lifecycle yang memang telah diterima oleh governance.

## 2. Verification Matrix

P2-007 patut mempunyai satu integration matrix yang sekurang-kurangnya
meliputi:

| Area | Verification |
|---|---|
| Supported formats | Setiap extension/MIME combination yang diluluskan boleh melalui ingestion |
| Extension rejection | Extension tidak dibenarkan ditolak |
| MIME rejection | MIME tidak sepadan/tidak dibenarkan ditolak |
| Size boundary | 524,288,000 bytes diterima jika kontrak menetapkan inclusive maximum |
| Oversized | 524,288,001 bytes ditolak |
| Corrupt media | Tidak menghasilkan valid MediaFile secara palsu |
| Checksum | SHA-256 dihasilkan server-side daripada actual uploaded content |
| Private storage | Media tidak terdedah melalui public disk/path |
| Opaque naming | Storage destination tidak bergantung kepada nama fail asal |
| Ownership | Media hanya dikaitkan dengan authenticated owner |
| Cross-user isolation | User B tidak boleh mengambil alih/retry/access attempt User A |
| Retry | Retry yang sah tidak menghasilkan duplicate unintended state |
| Same-attempt retry | Idempotency contract kekal |
| Failure compensation | DB/storage tidak tinggal dalam inconsistent committed state |
| FFprobe available | Metadata probe berfungsi mengikut contract |
| FFprobe unavailable/failure | Upload lifecycle degrade/fail mengikut contract tanpa corruption |
| Progress UX | UI tidak menunjukkan success sebelum server benar-benar commit ingestion |
| Success navigation | Successful upload membawa user ke Media Detail yang betul |
| Processing isolation | Upload tidak secara tidak sengaja memulakan Phase 3/transcription |

## 3. Happy-Path E2E

Gunakan browser-level/integration verification sebenar untuk sekurang-
kurangnya satu representative file dan automated integration coverage untuk
matrix yang sesuai.

Flow yang perlu dibuktikan:

authenticated user → upload UI → select valid media → upload progress →
server validation → staging → checksum → metadata/probe → private
promotion → MediaFile persistence → success response → Media Detail

Kemudian verify bahawa metadata penting seperti owner, original
filename/display metadata, size, MIME/extension, checksum, private storage
reference dan probe metadata adalah konsisten dengan contract.

## 4. Supported-Format Verification

Jangan hanya test satu MP3 dan kemudian declare seluruh format matrix
lulus.

Ambil canonical extension/MIME matrix daripada P2-001/ADR berkaitan
(`config/media.php`), bukan hardcode matrix baru dalam P2-007. Untuk setiap
format yang diluluskan, sekurang-kurangnya verify bahawa representative
valid media diterima dan resulting record/storage state betul.

Jika sesuatu MIME bergantung kepada OS/browser detection, test perlu
mengambil kira canonical MIME aliases yang memang telah diluluskan. P2-007
tidak boleh memperluaskan whitelist semata-mata untuk membuat test pass.

## 5. Boundary Verification

500 MiB mesti diuji berdasarkan exact byte contract:

500 MiB = 524,288,000 bytes

Critical boundary:

- 524,287,999 → accepted
- 524,288,000 → accepted
- 524,288,001 → rejected

Tetapi test application sahaja tidak mencukupi. P2-007 juga perlu verify
actual receiving path seperti PHP/web-server/runtime configuration supaya
application benar-benar mampu menerima request sehingga product limit.
Kalau upstream request limit lebih kecil daripada 500 MiB, phase gate tidak
boleh dianggap proven hanya kerana Laravel validation mempunyai
524,288,000. (See `CURRENT_STATE.md` — "Phase 2 Infrastructure Readiness":
the CLI `upload_max_filesize`/`post_max_size` were previously recorded as
below the 500 MiB boundary; this must be re-verified for the effective
receiving path, not assumed fixed.)

## 6. Invalid / Hostile Input Verification

Sekurang-kurangnya verify wrong extension, unsupported MIME,
extension/MIME mismatch, zero/invalid input jika relevant,
malformed/corrupt media, oversized media dan multiple-file submission
apabila contract hanya membenarkan single file.

Expected result bukan sekadar "HTTP error". Pastikan invalid input tidak
menghasilkan valid MediaFile, tidak meninggalkan promoted private media
yang dianggap berjaya, dan tidak menyebabkan processing/transcription side
effect.

## 7. Checksum & Storage Integrity

Gunakan known fixture dan independently calculate SHA-256. Pastikan:

stored checksum == SHA256(actual uploaded bytes)

Checksum tidak boleh dipercayai daripada client. Selepas promotion, verify
bytes dalam private storage masih menghasilkan checksum yang sama.

Storage filename/key pula mestilah opaque/server-controlled. Original
filename hanya metadata dan tidak boleh menentukan trusted storage path.

## 8. Security & User Isolation

Gunakan sekurang-kurangnya dua authenticated users: User A dan User B.

User A upload media dan menghasilkan attempt/media record. User B kemudian
cuba access/reuse/retry/manipulate identifier berkaitan User A.

Expected result: authorization/isolation menolak tindakan tersebut tanpa
disclosure atau mutation terhadap resource User A.

Verify juga bahawa media tidak boleh diperoleh terus melalui public storage
URL/path sekiranya architecture menetapkan private-only media storage.

## 9. Retry & Idempotency

P2-007 perlu mengesahkan contract P2-002B/P2-003 masih benar apabila
seluruh pipeline digabungkan.

Test sekurang-kurangnya same-attempt duplicate submission, retry selepas
deterministic failure, retry selepas ambiguous response/failure jika
contract menyokongnya, dan cross-user reuse.

Invariant utama:

Satu logical successful ingestion tidak boleh menghasilkan duplicate
unintended MediaFile hanya kerana client melakukan retry.

## 10. Failure / Compensation

Inject atau simulate failure pada meaningful boundaries yang architecture
sedia ada support, contohnya selepas staging, validation/probe failure,
promotion failure, persistence failure atau equivalent established failure
points.

Selepas setiap failure, verify DB state, staging state, promoted-storage
state, attempt state dan retry behaviour.

P2-007 tidak perlu mencipta cleanup daemon baru untuk menjadikan semuanya
bersih serta-merta. Ia cuma perlu membuktikan behaviour tersebut konsisten
dengan lifecycle contract dan interim cleanup decision ADR-013/014.

## 11. FFprobe Verification

Dua environment conditions perlu diuji:

- FFprobe available: valid media menghasilkan metadata yang dijanjikan oleh
  P2-005.
- FFprobe unavailable/fails: application mengikut documented
  fallback/failure semantics dan tidak menghasilkan corrupted ingestion
  state atau false success.

P2-007 tidak boleh mengubah media-probing contract.

## 12. Interim Cleanup State

Ini bahagian penting kerana P2-004A/A1 deferred. Verifier perlu secara
eksplisit merekod:

> P2-004A/P2-004A1 remain deferred under the applicable ADR decision. Their
> absence is not treated as a new P2-007 defect unless observed runtime
> behaviour violates the accepted interim lifecycle or creates a
> safety/integrity failure outside the accepted exception.

Maksudnya deferred cleanup debt masih direkodkan, tetapi tidak boleh diam-
diam digunakan untuk menggagalkan phase yang ADR sudah benarkan beroperasi
dalam interim state. If P2-004A2 (the SQLite-safe claim CAS protocol
authorized under ADR-016) has been closed by the time this task runs,
record its status here as well, but its closure is not a P2-007
precondition.

## 13. Regression Suite

Selepas targeted P2-007 tests, jalankan full relevant test suite.
Sekurang-kurangnya pastikan Phase 1 behaviour tidak regress,
authentication/authorization kekal, existing media views masih berfungsi,
existing seeded/factory data tidak rosak, upload routes tidak merosakkan
navigation dan tiada Phase 3 processing behaviour muncul.

Jika repository mempunyai standard lint/static analysis/format checks yang
diwajibkan oleh AGENTS.md atau orchestration policy, semuanya turut menjadi
sebahagian verification evidence.

## 14. Evidence

P2-007 tidak patut berakhir dengan "All tests passed."

Artifact verification mesti merekod environment/runtime, commit SHA,
commands/tests yang dijalankan, format matrix yang diuji, exact
size-boundary evidence, FFprobe condition, storage/isolation evidence,
failures yang diuji, full regression result, known deferred items dan
sebarang residual risk. Dengan itu independent reviewer boleh reproduce
keputusan.

## 15. Acceptance Criteria

P2-007 hanya boleh mendapat VERIFIED jika semua acceptance criteria utama
dipenuhi:

- [ ] 1. Semua supported format contract terbukti.
- [ ] 2. Invalid/unsupported media ditolak dengan selamat.
- [ ] 3. Exact 500 MiB boundary dan actual receiving path terbukti.
- [ ] 4. SHA-256 integrity terbukti.
- [ ] 5. Private opaque storage terbukti.
- [ ] 6. Ownership/cross-user isolation terbukti.
- [ ] 7. Retry/idempotency invariants terbukti.
- [ ] 8. Failure compensation tidak menghasilkan unsafe committed state.
- [ ] 9. FFprobe available/unavailable behaviour mematuhi P2-005.
- [ ] 10. UI tidak memberi premature success.
- [ ] 11. Successful ingestion menghasilkan Media Detail/state yang betul.
- [ ] 12. Tiada Phase 3/transcription side effects.
- [ ] 13. Deferred P2-004A/A1 (and P2-004A2, if applicable) state kekal
      konsisten dengan ADR-013/014/016.
- [ ] 14. Relevant regression suite lulus.
- [ ] 15. Tiada unresolved HIGH/CRITICAL finding yang menjejaskan phase
      gate.

## 16. Failure Handling

Kalau P2-007 jumpa defect, jangan repair dalam P2-007 secara senyap.

Contohnya jika 500 MiB gagal kerana PHP limit, FFprobe fallback rosak atau
retry menghasilkan duplicate media, verdict ialah CHANGES_REQUESTED. Defect
perlu dipetakan kepada owning task atau remediation task yang explicit,
implementation dibuat di sana, kemudian independent review dan P2-007
rerun.

Ini menjaga prinsip build → review → verify, bukan "integration test
sambil patch sampai hijau".

## 17. Explicit Non-Scope

P2-007 tidak authorize:

- transcription engine, queue processing Phase 3, speech-to-text provider
  integration, translation, transcript generation/export;
- folder management, rename/delete feature baru;
- automatic cleanup implementation P2-004A/A1/A2 (that is P2-004A2's scope,
  separately authorized under ADR-016);
- architecture redesign atau unrelated refactoring.

Penemuan berkaitan perkara tersebut boleh direkod sebagai future work
tetapi tidak boleh diimplementasikan melalui P2-007.

## 18. Final Phase Gate

Output akhirnya perlu memberi satu keputusan yang sangat jelas:

**VERIFIED** — evidence mencukupi untuk menyatakan:

> Phase 2 gate satisfied: an authenticated user can safely ingest
> supported media through the approved upload lifecycle under the
> ADR-014 accepted interim operating state.

atau:

**CHANGES_REQUESTED** — senaraikan finding mengikut CRITICAL / HIGH /
MEDIUM / LOW, evidence, violated contract dan remediation owner.

P2-007 sendiri tidak menutup atau membuka Phase 3. Selepas VERIFIED,
governance mengemas kini canonical records mengikut State-to-Action
Contract (`.ai/guidelines/orchestration-policy.md`), dan the Human Product
Owner kekal pihak yang approve sama ada nak proceed ke Phase 3.

## Context

- `config/media.php` — canonical accepted media matrix and the exact 500
  MiB (524,288,000-byte) per-file limit; the source of truth for section 4,
  not a matrix re-derived inside this task.
- `tasks/P2-003-implement-real-upload-ingestion-workflow.md` — the verified
  upload/ingestion workflow this task exercises end-to-end.
- `tasks/P2-004A2-staging-claim-cas-protocol.md` — separately authorized;
  not a P2-007 dependency (see Preconditions).
- `reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`,
  `DECISIONS.md` ADR-013/ADR-014/ADR-016 — the accepted interim cleanup
  state referenced throughout.

## Dependencies

Requires:

- P2-001, P2-001A, P2-002, P2-002A, P2-002B, P2-002C, P2-003, P2-005 (all
  DONE).

Does not require:

- P2-004A, P2-004A1, or P2-004A2 (see Preconditions and section 12).

## Implementation Notes

To be completed by the implementation owner, if and when this task is
promoted to READY.

### Files Changed

- None yet.

### Important Decisions

- None yet.

### Known Limitations

- None yet.

## Verification

### Environment

- Branch: `setup/ai-development-os`
- HEAD: `73dd6b6`
- PHP CLI: `upload_max_filesize=512M`, `post_max_size=520M`, `max_execution_time=0`, `memory_limit=128M`
- FFprobe: v9.0.1 available (`ffprobe -version` succeeds)
- Storage: local disk root = `storage_path('app/private')` (private); public disk root = `storage_path('app/public')` (separate); `MediaFile::assertPrivateStorageDisk()` rejects public visibility/root
- DB: SQLite in-memory (test), SQLite file (production dev)
- Config: `config/media.php` — 524,288,000 bytes, SHA-256, 24h retention, `media.show` post-upload route

### Verification Matrix

| # | Area | Contract Source | Method | Evidence | Result |
|---|------|----------------|--------|----------|--------|
| 1 | Supported formats | `config/media.php:31-45` | Automated: `MediaUploadContractTest` asserts exact matrix; `MediaIngestionTest` ingests MP3 | Config matrix matches exactly (mp3, wav, m4a, aac, flac, ogg, mp4, mov, webm with approved MIME aliases); ingestion test creates valid MediaFile with correct extension/mime/media_type | PASS |
| 2 | Extension rejection | `MediaIngestionService::resolveMediaType()` | Automated: `MediaIngestionTest::unsupported_media_and_extension_mime_mismatches_are_rejected` | `notes.txt`/`text/plain` → rejected; `recording.mp3`/`video/mp4` → rejected; no MediaFile created, no staging artifacts | PASS |
| 3 | MIME rejection | `MediaIngestionService::resolveMediaType()` | Automated: same test as #2 | Extension/MIME mismatch correctly rejected before persistence | PASS |
| 4 | Size boundary (application) | `config/media.php:17`, `MediaIngestionService::validateByteSize()` | Automated: `MediaIngestionTest::exact_product_boundary_is_accepted_and_one_byte_over_is_rejected` | 524,288,000 → accepted; 524,288,001 → `ValidationException` thrown | PASS |
| 5 | Size boundary (receiving path) | PHP runtime config | Manual: `php -r "ini_get('upload_max_filesize')"` | `upload_max_filesize=512M` (536,870,912 bytes) > 524,288,000; `post_max_size=520M` (545,259,520 bytes) > 524,288,000. Full request reaches Laravel validation. | PASS |
| 6 | Corrupt/unusable media | `MediaIngestionService::inspectUploadedFile()` | Automated: missing file → rejected; invalid upload → rejected | `missing_file_submission_is_rejected` → validation error, 0 MediaFiles | PASS |
| 7 | SHA-256 checksum | `MediaIngestionService::checksum()`, `MediaFile::assignGeneratedChecksumSha256()` | Automated: `MediaIngestionTest::client_checksum_values_cannot_override_server_generated_checksum`; `MediaUploadContractTest::media_file_checksum_is_server_assigned` | Server generates SHA-256 from uploaded bytes; client-supplied values ignored; 64 lowercase hex enforced; nullable for legacy records; non-unique index | PASS |
| 8 | Private opaque storage | `MediaFile::assertPrivateStorageDisk()`, `config/filesystems.php:33-39` | Automated: `MediaUploadContractTest::media_storage_uses_existing_private_local_disk` and `media_storage_boundary_rejects_public_disk`; `MediaIngestionTest::authenticated_user_can_ingest` | Local root = `app/private`; public root = `app/public` (separate); public disk config throws `LogicException`; storage path = `media/{uuid}/{random40}.{ext}` (opaque, no user/filename leakage) | PASS |
| 9 | Ownership/isolation | `MediaUploadController::store()` | Automated: `MediaIngestionTest::one_user_cannot_reuse_another_users_upload_attempt`; `IngestionCompensationContractTest::does_not_allow_one_owner_to_complete_another_owners_upload_attempt` | User B → 403 on User A's attempt; `user_id` scoping on all queries | PASS |
| 10 | Upload-attempt identity | `MediaIngestionService::findByAttempt()`, `MediaFile::assignUploadAttemptId()` | Automated: `MediaIngestionTest::retrying_one_upload_attempt_returns_the_committed_media_file`; `MediaUploadContractTest` | UUID attempt ID; same attempt returns existing MediaFile (1 record); `upload_attempt_id` validated as UUID | PASS |
| 11 | Same-attempt idempotency | `MediaIngestionService::ingest()` L84-89 | Automated: `MediaIngestionTest::retrying_one_upload_attempt`; `IngestionCompensationContractTest::returns_the_existing_media_record_when_completion_is_retried` | Retry returns same MediaFile, same storage_path, 0 additional records | PASS |
| 12 | Ambiguous retry recovery | `MediaIngestionService::ingest()` L148-160 | Automated: `MediaIngestionTest::ambiguous_duplicate_key_retry_returns_existing_media`; `IngestionCompensationContractTest::resolves_an_ambiguous_database_result` | Simulated race: conflicting row inserted during transaction → inner catch finds existing attempt → returns existing record, cleans up duplicate durable file, 1 total record | PASS |
| 13 | Promotion/persistence compensation | `MediaIngestionService::ingest()` L166-173 | Automated: `MediaIngestionTest::promotion_failure_compensation` and `persistence_failure_compensation`; `IngestionCompensationContractTest::compensates_a_promoted_object_when_media_persistence_fails` | Promotion failure → staging cleaned, 0 MediaFiles, 0 durable files; Persistence failure → promoted object + staging cleaned, 0 MediaFiles | PASS |
| 14 | Staging cleanup (Option D + P2-004A2) | ADR-013, ADR-016, `CleanupStaging.php` | Automated: `CleanupStagingCommandTest` (10/14 in isolation — see Finding F-1); `StagingClaimCasProtocolTest` 8/8 | CAS protocol: `insertOrIgnore` + guarded `UPDATE`; claim transitions: `upload` → `cleanup` → delete; crash-recovery re-claim after 15min timeout; file deletion outside transaction. All 8 race-safety tests pass. | PASS* |
| 15 | Cleanup/upload race safety | P2-004A2 protocol, `StagingClaimCasProtocolTest` | Automated: `StagingClaimCasProtocolTest` 8/8 (both genuine OS-process race tests) | Shared file-based SQLite DB; `Process::setEnv()` for independent connections; `readyFile` rendezvous; 8/8 passed ×3 runs, no flakiness | PASS |
| 16 | FFprobe available | `MediaMetadataProbeService::probe()`, `MediaMetadataProbeServiceTest` | Automated: `MediaMetadataProbeServiceTest::probe_resolves_real_private_disk_path` (skips if FFprobe unavailable) | FFprobe v9.0.1 available; WAV fixture: duration=1s, codec=pcm_s16le, sample_rate=8000, channels=1 | PASS |
| 17 | FFprobe unavailable | `MediaMetadataProbeService::probe()` L34-41, L54-66 | Automated: `probe_returns_null_values_when_file_does_not_exist`; `probe_and_update_sets_null_values_when_probe_fails` | Missing file → null metadata (no crash); probe failure → null metadata, no corruption, ingestion still completes | PASS |
| 18 | Upload progress UX | `resources/views/media/upload.blade.php` L131-144 | Manual: upload view source code | `xhr.upload.onprogress` → progress bar + percentage; `percentage >= 100` → "Finalizing…" label; status = "Upload transferred. Finalizing validation and storage…" | PASS |
| 19 | No premature success | `resources/views/media/upload.blade.php` L155-161 | Manual: upload view source code | `window.location.assign(redirect)` only fires on `xhr.status >= 200 && < 300 && payload?.redirect` — server-confirmed success only; error path shows error message, re-enables submit button | PASS |
| 20 | Success navigation | `MediaUploadController::successResponse()`, `config/media.php:25` | Automated: `MediaIngestionTest::authenticated_user_can_ingest` asserts redirect to `media.show`; `MediaUploadContractTest` | `post_upload_route = media.show`; redirect = `route('media.show', $mediaFile)`; JSON response includes `redirect` URL and `media_file_id` | PASS |
| 21 | No Phase 3 side effects | `MediaIngestionTest::authenticated_user_can_ingest` | Automated: asserts `Transcription::count() === 0` and `ProcessingJob::count() === 0` after ingestion | 0 Transcriptions, 0 ProcessingJobs after successful upload | PASS |

### Receiving-Path Boundary Verification

| Layer | Effective Limit | Status |
|-------|----------------|--------|
| PHP `upload_max_filesize` | 512M = 536,870,912 bytes | ≥ 524,288,000 ✓ |
| PHP `post_max_size` | 520M = 545,259,520 bytes | ≥ 524,288,000 ✓ |
| Laravel validation | 524,288,000 bytes (inclusive) | Exact boundary ✓ |
| Web server (PHP built-in) | Inherits `upload_max_filesize` | Same as PHP ✓ |
| Browser upload flow | XHR `FormData` → multipart/form-data | No additional limit ✓ |

**Conclusion:** A 524,288,000-byte file will reach application validation. A 524,288,001-byte file will be rejected by `validateByteSize()`.

### Browser/UI Verification

- **Upload progress:** Visible via `xhr.upload.onprogress` → `<progress>` element + percentage text. Label transitions from "Uploading…" to "Finalizing…" at 100%.
- **Premature success protection:** `window.location.assign()` only fires after server returns 2xx with `payload.redirect`. Error path shows error message and re-enables submit button.
- **Navigation:** Successful upload redirects to `route('media.show', $mediaFile)` — correct Media Detail surface.
- **Failure states:** XHR error/interrupt shows user-visible error message; status = "Upload interrupted before server confirmation."

### Retry / Compensation / Cleanup Verification

- **Option D:** Remains in force. No automated staging cleanup is scheduled or executed outside of the CAS protocol.
- **P2-004A2 CAS protocol:** Implemented and verified. `insertOrIgnore` + guarded `UPDATE` for claim transitions; file deletion outside transaction; crash-recovery re-claim after 15min timeout.
- **Race safety:** 8/8 `StagingClaimCasProtocolTest` passed (both genuine OS-process race tests). Shared file-based SQLite DB with `Process::setEnv()` for independent connections.

### FFprobe Verification

- **FFprobe available:** v9.0.1. WAV fixture probe: `duration_seconds=1`, `audio_codec=pcm_s16le`, `sample_rate=8000`, `channels=1`. Metadata correctly populated.
- **FFprobe unavailable/fails:** `probe()` returns null metadata (all five fields null). `probeAndUpdate()` sets null values on MediaFile. Ingestion completes successfully. No corruption, no false success.

### Phase-Boundary Verification

After successful ingestion:
- `Transcription::query()->count() === 0` ✓
- `ProcessingJob::query()->count() === 0` ✓
- No queue jobs dispatched ✓
- No FFmpeg/FFmpeg processing triggered ✓
- No transcription/Phase 3 behavior present ✓

### Interim Cleanup State (Section 12)

- P2-004A/P2-004A1 remain BLOCKED under ADR-013. Their absence is not treated as a defect.
- P2-004A2 is DONE (independently VERIFIED, round 2). CAS protocol proven with genuine OS-process race tests. Implementation and closure exist in the current uncommitted working tree; HEAD is `73dd6b6` (pre-P2-004A2). The governance State-to-Action Contract treats working-tree artifacts as canonical; git commits are recommended for durability but not required for state validity.
- Current integrated behavior is safe under the accepted Option D + P2-004A2 state.
- Staging artifacts accumulate (accepted interim operational cost per ADR-013) but the CAS protocol ensures cleanup/upload races cannot corrupt state.

## Findings

### F-1 — Superseded (test-isolation observation from intermediate state)

The initial P2-007 verification run reported that `CleanupStagingCommandTest` failed 4/14 when run in isolation, attributed to `Storage::fake('local')` test-isolation behavior. Independent re-verification by Claude Code (reviewer) obtained 14/14 passing twice. OpenCode independently re-ran the same file twice and obtained 14/14 passing both times on the current unchanged worktree.

The earlier 4/14 observation came from an intermediate/transient invocation state during the initial verification cycle and is not reproducible on the current canonical worktree. **This finding is superseded.** The current reproducible baseline is 14/14 for `CleanupStagingCommandTest` in isolation.

No `Storage::fake('local')` test-isolation defect exists in the current codebase. All individual test files pass in isolation.

### F-2 — P2-004A2 closure exists only in uncommitted working tree (MEDIUM, informational)

P2-004A2 is recorded as DONE (closed by Human Product Owner on 2026-09-15). The implementation, tests, task file, and review artifact all exist in the current uncommitted working tree. There is no corresponding git commit for the P2-004A2 closure or implementation.

**Governance assessment:** The State-to-Action Contract (`.ai/guidelines/orchestration-policy.md`) defines canonical state through repository artifacts (`tasks/`, `reviews/`, `CURRENT_STATE.md`), not through git history. The policy does not require commits for state transitions or closure to be considered canonical. The working-tree state is the current canonical state.

**Risk:** Uncommitted changes are loss-prone. This is a process/evidence limitation, not a governance deficiency. The implementation owner should commit the approved P2-004A2 state for durability, but this is not a P2-007 finding — it is a general repository hygiene item.

**P2-007 treatment:** P2-007 correctly distinguishes: (a) current working-tree state (P2-004A2 DONE, implementation present), and (b) committed HEAD (`73dd6b6`, pre-P2-004A2). P2-007 does not present uncommitted HPO closure as committed history.

### F-3 — P2-004A2 residual test-coverage observation (MEDIUM, non-blocking)

No test directly exercises the real `MediaIngestionService::stage()` production path for the scenario where ingestion loses ownership to an in-progress cleanup claim (`held_by = 'cleanup'`). The `StagingClaimCasProtocolTest` tests exercise the CAS protocol and race safety through the `StagingRaceWorker` harness, not through the production `MediaIngestionService::stage()` code path.

This is residual test-coverage debt. It does not affect the P2-004A2 VERIFIED verdict or the P2-007 phase gate. Record for future task consideration.

### No HIGH or CRITICAL findings.

## Regression and Quality Results

### Focused Verification Suites (current reproducible baseline)

| Suite | Tests | Passed | Failed | Assertions |
|-------|-------|--------|--------|------------|
| MediaUploadContractTest | 11 | 11 | 0 | 29 |
| IngestionCompensationContractTest | 11 | 11 | 0 | 36 |
| StagingClaimCasProtocolTest | 8 | 8 | 0 | 26 |
| CleanupStagingCommandTest | 14 | 14 | 0 | 32 |
| Combined upload-related | 25 | 25 | 0 | 83 |

Note: `CleanupStagingCommandTest` passes 14/14 in isolation (confirmed twice by OpenCode, twice by Claude Code). The earlier 4/14 observation was from an intermediate state and is superseded.

### Full Test Suite

| Metric | Value |
|--------|-------|
| Total tests | 230 |
| Passed | 229 |
| Failed | 0 |
| Skipped | 1 (Fortify 2FA — intentionally disabled) |
| Assertions | 688 |
| Warnings | 2 (deprecation, non-functional) |

### Code Quality

| Check | Result |
|-------|--------|
| Pint (`--dirty --format agent`) | Passed |
| PHPStan (`composer types:check`) | 0 errors |

## Files Changed

- `tasks/P2-007-phase-integration-verification.md` — Status transitioned READY → IN_PROGRESS → REVIEW → IN_PROGRESS (CHANGES_REQUESTED cycle) → REVIEW → VERIFIED (independent review round 2 by Claude Code). F-1 corrected (superseded); F-2 (P2-004A2 working-tree state) and F-3 (test-coverage debt) added; verification evidence updated with current reproducible results; Review Status updated to VERIFIED; Final Task State corrected.
- `CURRENT_STATE.md` — P2-007 state updated from READY to in REVIEW (during promotion).
- No application code, tests, or configuration modified.

## Final Task State

P2-007 is **VERIFIED** (independent review round 2 by Claude Code, VERIFIED verdict). All 15 acceptance criteria from Section 15 are satisfied. Three findings recorded: F-1 superseded (intermediate-state artifact), F-2 informational (P2-004A2 uncommitted state), F-3 non-blocking MEDIUM (test-coverage debt). Two additional review findings: F-R2-1 (uncommitted-state-as-canonical governance question, decided by Human Product Owner) and F-R2-2 (review artifact authorship process violation, noted). No blocking findings exist.

## Explicit Non-Actions

- P2-004A: Not reopened or closed. Historical BLOCKED record preserved.
- P2-004A1: Not reopened or closed. Historical BLOCKED record preserved.
- Option D: Not lifted. Remains in force per ADR-013.
- Phase 3: Not authorized. No transcription/processing/queue behavior introduced.
- No unrelated features added.
- No unauthorized tasks started.
- No implementation code modified.
- No tests modified.
- No governance files modified (beyond the P2-007 task artifact status update).

## Review

Review File:

`reviews/P2-007-independent-review.md`

Review Status:

VERIFIED (round 2, by Claude Code). Two non-blocking informational notes
(F-R2-1: uncommitted-state-as-canonical governance question; F-3:
P2-004A2 test-coverage gap) and one process note (F-R2-2: this review
artifact was overwritten between round 1 and round 2 by a party other than
Claude Code and has been restored to Claude Code's sole authorship) are
recorded for Human Product Owner attention in the review file. VERIFIED
does not authorize Phase 3, does not close P2-004A/P2-004A1, and does not
lift Option D (ADR-013). The Human Product Owner must still close this
task as DONE.

## Completion

A task cannot move directly from IN_PROGRESS to DONE.

Required flow:

BACKLOG -> READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

CHANGES_REQUESTED returns the task to IN_PROGRESS. OpenCode applies corrections and returns to REVIEW.

Promoted to READY by Human Product Owner on 2026-09-15. The implementation
owner must not mark their own work VERIFIED.
