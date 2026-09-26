<?php

namespace App\Storage;

use App\Deployment\UploadLimitAudit;

/**
 * Local private-storage topology validation (P7-004).
 *
 * Single-backend truth per D7-03/A: the configured media disk must be a
 * local private disk (never object storage, never public), its root must
 * exist and be writable, and free space must clear the P7-001 floor
 * (`deployment.min_free_bytes`, default 1 GiB).
 *
 * Pure `evaluate()` logic with injectable seams so tests never depend on
 * the machine. The `storage:validate-topology` command and the
 * `deployment:verify` storage-topology sub-check report this evaluation;
 * enforcement stays advisory outside production (P7-008 conventions).
 */
final class StorageTopology
{
    /**
     * @param  array{disk?: mixed, driver?: mixed, root?: mixed, rootExists?: mixed, rootWritable?: mixed, freeBytes?: mixed, minimum?: mixed}|null  $seams
     * @return array{violations: list<string>, detail: array<string, string>}
     */
    public static function evaluate(?array $seams = null): array
    {
        $disk = $seams['disk'] ?? config('media.storage_disk', 'local');
        $driver = $seams['driver'] ?? config("filesystems.disks.{$disk}.driver");
        $root = $seams['root'] ?? config("filesystems.disks.{$disk}.root");
        $rootExists = $seams['rootExists'] ?? (is_string($root) && is_dir($root));
        $rootWritable = $seams['rootWritable'] ?? (is_string($root) && is_writable($root));
        $freeBytes = $seams['freeBytes'] ?? (is_string($root) ? @disk_free_space($root) : false);
        $minimum = $seams['minimum'] ?? max(0, (int) config('deployment.min_free_bytes', 1_073_741_824));

        $violations = [];
        $detail = [
            'disk' => is_string($disk) ? $disk : get_debug_type($disk),
            'driver' => is_string($driver) ? $driver : get_debug_type($driver),
            'root' => is_string($root) ? $root : get_debug_type($root),
        ];

        if (! is_string($disk) || $disk === '') {
            $violations[] = 'The media storage disk is not configured (media.storage_disk).';
        }

        if ($driver !== 'local') {
            $violations[] = sprintf(
                'The media storage disk driver must be "local" (D7-03); got "%s". Object storage is deferred, not adopted.',
                is_scalar($driver) ? (string) $driver : get_debug_type($driver)
            );
        }

        if (! is_string($root) || $root === '') {
            $violations[] = 'The media storage disk has no root path configured.';
        } else {
            if ($rootExists !== true) {
                $violations[] = sprintf('The media storage root does not exist: %s.', $root);
            } elseif ($rootWritable !== true) {
                $violations[] = sprintf('The media storage root is not writable: %s.', $root);
            }

            if (! is_int($freeBytes) && ! is_float($freeBytes)) {
                $violations[] = sprintf('Free space could not be determined for "%s".', $root);
            } elseif ((int) $freeBytes < $minimum) {
                $violations[] = sprintf(
                    'Free space on the media storage volume is below the required minimum (%s).',
                    UploadLimitAudit::formatBytes($minimum)
                );
            }
        }

        return ['violations' => $violations, 'detail' => $detail];
    }
}
