# P2-007 — Phase Integration Verification

> **PLANNING CANDIDATE ONLY** — P2-007 is currently NOT ELIGIBLE / NOT
> AUTHORIZED under the canonical Phase 2 governance state. This artifact
> does not reopen Phase 2, supersede ADR-014, promote P2-007 to READY, or
> authorize execution. Any activation requires an explicit governance
> decision first.

## Status

BACKLOG

This task is a planning candidate only, per the guardrail above. It does not
exist as an authorized, runnable task. Promoting it to READY is an explicit
Human Product Owner decision, separate from and not implied by this
artifact's existence or level of detail.

## Ownership

Implementation Owner: UNASSIGNED (OpenCode, per AGENTS.md, once and if this
is promoted to READY).
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

Implementation owner must record the commands executed and results per
section 14 (Evidence).

Result:

PENDING

## Review

Review File:

None yet.

Review Status:

PENDING

## Completion

A task cannot move directly from IN_PROGRESS to DONE.

Required flow:

BACKLOG -> READY -> IN_PROGRESS -> REVIEW -> VERIFIED -> DONE

The Human Product Owner must explicitly promote this task from BACKLOG to
READY before any implementation owner may begin work. The implementation
owner must not mark their own work VERIFIED.
