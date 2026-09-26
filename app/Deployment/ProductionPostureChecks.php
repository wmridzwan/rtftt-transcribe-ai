<?php

namespace App\Deployment;

use App\Security\SecurityAuditLog;
use LogicException;

/**
 * Production posture checks beyond the P7-008 deployment matrix (P7-001).
 *
 * Additive rule sets that extend — never replace or fork — the P7-008
 * `ProductionConfigGuard` verdict: Redis auth posture per environment
 * (TD-004 certification), upload-limit adequacy (TD-002 application
 * share), and storage capacity signals. Queue-driver and timeout
 * consistency stay owned by the P7-003 guards; the deployment matrix
 * stays owned by `ProductionConfigGuard`.
 *
 * Pure `violations()` logic with injectable seams (ini values, redis
 * record, free-bytes override) so tests never depend on the machine.
 * Enforced at boot in production only, alongside the P7-008 guard.
 */
final class ProductionPostureChecks
{
    /**
     * @param  array{upload_max_filesize?: string, post_max_size?: string}|null  $ini
     * @param  array{host?: mixed, port?: mixed, password?: mixed}|null  $redisConnection
     * @param  int|null  $freeBytes  override for disk_free_space (tests)
     * @return list<string> human-readable violations; empty when safe
     */
    public static function violations(?array $ini = null, ?array $redisConnection = null, ?int $freeBytes = null): array
    {
        $violations = [];

        $audit = UploadLimitAudit::evaluate($ini);

        foreach ($audit['findings'] as $finding) {
            $violations[] = $finding;
        }

        $record = RedisPosture::record($redisConnection);

        foreach ($record['violations'] as $violation) {
            $violations[] = $violation;
        }

        $freeBytes ??= @disk_free_space(storage_path('app'));
        $minimum = max(0, (int) config('deployment.min_free_bytes', 1_073_741_824));

        if (! is_int($freeBytes) && ! is_float($freeBytes)) {
            $violations[] = 'Storage capacity could not be determined for "'.storage_path('app').'".';
        } elseif ((int) $freeBytes < $minimum) {
            $violations[] = sprintf(
                'Free space on the application storage volume (%s) is below the required minimum (%s).',
                UploadLimitAudit::formatBytes($freeBytes),
                UploadLimitAudit::formatBytes($minimum)
            );
        }

        // P7-002 datastore rule set (D7-01 flip): advisory while sqlite,
        // required once pgsql is selected. Contributed through this
        // extension point; the P7-001 rules above are untouched.
        foreach (DatastorePosture::evaluate(null) as $violation) {
            $violations[] = $violation;
        }

        return $violations;
    }

    /**
     * @throws LogicException when production posture is unsafe
     */
    public static function assertValid(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $violations = self::violations();

        if ($violations !== []) {
            SecurityAuditLog::guardRefusal('ProductionPostureChecks', count($violations).' violation(s)');

            throw new LogicException(
                'Unsafe production posture (P7-001): '.implode(' ', $violations)
            );
        }
    }
}
