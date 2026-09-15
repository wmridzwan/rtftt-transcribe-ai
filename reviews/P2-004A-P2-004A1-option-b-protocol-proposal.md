# P2-004A / P2-004A1 — Option B Protocol Proposal (SQLite-Safe Claim CAS)

## Status

PROPOSAL — for Human Product Owner architecture approval. This is a design
recommendation only. No implementation, migration, or test is authorized by
this document. See `reviews/P2-004A-P2-004A1-sqlite-concurrency-decision-package.md`
for the accepted background and the four resolution options (A/B/C/D).

## Why this exists

ADR-013 selected Option D (defer) for the current Phase 2 gate. This document
answers the follow-up question already recorded as the preferred future
resolution class: what would Option B (redesign the cleanup/claim protocol so
correctness does not depend on unsupported row-level locking) concretely look
like against the actual schema already in the repository?

The prior three CHANGES_REQUESTED cycles failed because the task asked the
implementation owner to prove a concurrency guarantee without specifying the
exact mechanism. This document exists to remove that ambiguity before any
future implementation task is authorized.

## Root cause, precisely

`app/Console/Commands/CleanupStaging.php` currently does:

```php
DB::transaction(function () {
    $claim = StagingClaim::query()->...->lockForUpdate()->first(); // (1)
    // ...decide based on $claim...                                 // (2)
    $claim->update([...]) or StagingClaim::create([...]);           // (3)
    // ...delete files from disk, still inside the transaction...   // (4)
    $claim->delete();                                                // (5)
});
```

Two problems:

1. **`lockForUpdate()` is a no-op on SQLite** (Laravel's SQLite grammar drops
   it). Steps (1)-(3) are a classic check-then-act sequence with no row lock
   between the read and the write, so a concurrent ingestion request that
   refreshes the same claim row between (1) and (3) is not excluded by
   anything the code itself guarantees — only by incidental SQLite/PDO
   behavior that is not a documented contract.
2. **File I/O happens inside the transaction** (step 4), so whatever broad
   database-level lock SQLite does take is held for the duration of disk
   deletion, which can stall unrelated writes elsewhere in the app.

## Proposed protocol: single-statement compare-and-set, no locks

SQLite does not need row locks to be safe, because **a single `UPDATE` or
`INSERT ... WHERE`-guarded statement is atomic in SQLite regardless of
`lockForUpdate()`** — the database serializes writers at the engine level for
one statement. The fix is to make the *entire claim decision* one such
statement instead of a separate read-then-write pair, and to move file I/O
outside any transaction.

### Schema change (new migration required)

Add two columns to `staging_claims`:

```php
$table->string('held_by')->default('upload'); // 'upload' | 'cleanup'
$table->timestamp('cleanup_claimed_at')->nullable();
```

### Step 1 — Ingestion claims its own row atomically (no read-then-write)

When `MediaIngestionService` stages a new attempt, it inserts the claim with
`insertOrIgnore` (compiles to `INSERT OR IGNORE`, atomic on SQLite via the
existing `(user_id, upload_attempt_id)` unique index):

```php
$inserted = DB::table('staging_claims')->insertOrIgnore([
    'user_id' => $ownerId,
    'upload_attempt_id' => $attemptId,
    'staging_path' => $stagingPath,
    'held_by' => 'upload',
    'claimed_at' => now(),
    'expires_at' => now()->addHours($retentionHours),
    'created_at' => now(),
    'updated_at' => now(),
]);

if ($inserted === 0) {
    // A row already exists for this attempt. Re-fetch: if held_by is still
    // 'upload', this is a normal same-attempt retry -> fall through to the
    // renewal step below. If held_by is 'cleanup', cleanup has already
    // claimed this attempt for deletion -> this is the contended case: do
    // NOT write into the staging directory. Return a controlled retryable
    // failure (new upload_attempt_id) rather than silently proceeding.
}
```

Same-attempt retry renewal (also a single guarded `UPDATE`, no read-then-write):

```php
DB::table('staging_claims')
    ->where('user_id', $ownerId)
    ->where('upload_attempt_id', $attemptId)
    ->where('held_by', 'upload')
    ->update(['expires_at' => now()->addHours($retentionHours), 'updated_at' => now()]);
```

### Step 2 — Cleanup claims an expired attempt atomically

Cleanup never reads the row to decide, then writes. It attempts the
transition directly and inspects the affected-row count:

```php
$claimed = DB::table('staging_claims')
    ->where('user_id', $ownerId)
    ->where('upload_attempt_id', $attemptId)
    ->where('held_by', 'upload')
    ->where('expires_at', '<', now())
    ->update(['held_by' => 'cleanup', 'cleanup_claimed_at' => now()]);

if ($claimed === 0) {
    // Either: no claim row (orphan with no claim record - handle via
    // insertOrIgnore with held_by='cleanup' as a fallback claim), or the
    // claim is still active ('upload', not expired), or cleanup already
    // owns it. In every case: do not delete. Defer.
    return; // deferred, not an error
}

// $claimed === 1: this process now exclusively owns the attempt for cleanup.
// No other caller can also have won this UPDATE for the same row.
```

Re-verify (defensive, cheap, does not need to be atomic with the claim since
the claim already excludes concurrent writers) that no `MediaFile` was
committed for this attempt before deleting. Then:

### Step 3 — Delete files OUTSIDE any transaction

```php
foreach ($disk->allFiles($attemptDir) as $file) {
    $disk->delete($file);
}
$disk->deleteDirectory($attemptDir);

DB::table('staging_claims')
    ->where('user_id', $ownerId)
    ->where('upload_attempt_id', $attemptId)
    ->where('held_by', 'cleanup')
    ->delete();
```

No database lock is held during disk I/O, resolving the liveness concern in
the decision package as well as the correctness concern.

### Crash recovery

If a cleanup process crashes after winning the `held_by='cleanup'` update but
before finishing deletion, the row is stuck in `cleanup` state. A later
cleanup run may re-claim it once `cleanup_claimed_at` is older than a bounded
timeout (e.g. 15 minutes):

```php
->where('held_by', 'cleanup')->where('cleanup_claimed_at', '<', now()->subMinutes(15))
```

## Why this satisfies the decision package's invariant

- No step relies on `lockForUpdate()`, `FOR UPDATE`, or any lock that SQLite
  silently drops.
- Every claim-state transition is a single guarded `UPDATE`/`INSERT ... OR
  IGNORE` statement; there is no read-then-write gap for a competing process
  to land in.
- Contention has an explicit, non-silent outcome in both directions: cleanup
  that loses the race defers (no deletion); ingestion that loses the race
  (staging already claimed by cleanup) returns a controlled retryable
  failure instead of writing into a directory about to be deleted.
- File deletion is outside the decision transaction.
- Crash recovery is bounded and explicit, not inferred from file mtime alone.

## What a future implementation task must still define/prove

This document is the mechanism design. An authorized implementation task
must still:

1. Write the migration adding `held_by` and `cleanup_claimed_at`, with a
   backfill default (`held_by = 'upload'`) for any existing rows.
2. Update `MediaIngestionService` and `CleanupStaging` to use the statements
   above instead of `lockForUpdate()`.
3. Prove the race with **genuine independent connections/processes** — not a
   single-process simulation. Recommended approach: use Symfony's `Process`
   component (already available via Laravel) to spawn two real separate PHP
   CLI processes, each opening its own SQLite connection to the same
   database file, coordinated by a filesystem rendezvous (each process
   writes a "ready" sentinel and polls for a "go" sentinel) so both attempt
   their conditional statement at the same wall-clock moment. A dedicated
   test-harness Artisan command (e.g. `test:staging-race-worker`, guarded to
   only exist in the testing environment) is the cleanest way to give each
   spawned process a script to run.
4. Cover: normal same-attempt retry renewal, cleanup winning against an
   expired attempt, cleanup losing against an active attempt, ingestion
   losing against an in-progress cleanup claim (retryable failure path),
   and crash-recovery re-claim after the timeout.
5. Run the full P2-002B/P2-003 regression suite unchanged in behavior.

## Recommendation

Approve this design as the basis for a new, narrowly-scoped implementation
task, recorded under a new ADR amending/superseding the concurrency portion
of ADR-013. Keep the task scope limited to exactly the four files above (one
migration, `MediaIngestionService`, `CleanupStaging`, and the new race
tests) — do not fold in P2-004A2, P2-004B+, P2-005+, or Phase 3 work.
