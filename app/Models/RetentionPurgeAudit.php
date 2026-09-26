<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One audited purge decision outcome (P7-011).
 *
 * Outcomes: `purged` (bytes deleted + row tombstoned/claimed),
 * `absent` (already missing — idempotent no-op, tombstoned where a row
 * exists), `failed` (deletion or persistence did not complete — never
 * presented as success), `skipped` (protected/excluded class or dry
 * run), `protected` is folded into `skipped` with an explicit reason.
 * Rows are never purge-eligible themselves (§6.10).
 */
class RetentionPurgeAudit extends Model
{
    public const OUTCOME_PURGED = 'purged';

    public const OUTCOME_ABSENT = 'absent';

    public const OUTCOME_FAILED = 'failed';

    public const OUTCOME_SKIPPED = 'skipped';

    protected $fillable = [
        'run_id',
        'purgeable_type',
        'purgeable_id',
        'artifact_path',
        'outcome',
        'reason',
    ];
}
