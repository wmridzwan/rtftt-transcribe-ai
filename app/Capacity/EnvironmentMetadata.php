<?php

namespace App\Capacity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Environment metadata capture for P7-009 runs.
 *
 * Each run retains enough metadata to interpret its results: commit,
 * OS, CPU, memory, PHP/runtime config, DB driver, Redis details,
 * worker/queue configuration, storage characteristics, model/provider
 * configuration, and relevant application configuration.
 *
 * The `environment_classification` field is load-bearing governance:
 * Phase A rehearsal runs MUST report `REHEARSAL / SUBSTITUTE`, and no
 * consumer may relabel such a run as target capacity evidence.
 */
final class EnvironmentMetadata
{
    /**
     * @return array<string, mixed>
     */
    public static function collect(string $runId, string $classification = 'REHEARSAL / SUBSTITUTE'): array
    {
        return [
            'run_id' => $runId,
            'environment_classification' => $classification,
            'classification_notice' => CapacityEnvelope::CLASSIFICATION,
            'collected_at' => now()->toIso8601String(),
            'commit' => self::commit(),
            'os' => PHP_OS_FAMILY.' / '.php_uname(),
            'cpu' => self::cpu(),
            'memory' => self::memory(),
            'php' => [
                'version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ],
            'database' => self::database(),
            'redis' => self::redis(),
            'queue' => [
                'default_connection' => config('queue.default'),
                'database_queue_table' => config('queue.connections.database.table', 'jobs'),
                'queue_depth_jobs' => self::queueDepth(),
            ],
            'storage' => [
                'media_disk' => config('media.storage_disk', 'local'),
                'driver' => config('filesystems.disks.'.config('media.storage_disk', 'local').'.driver'),
                'root' => config('filesystems.disks.'.config('media.storage_disk', 'local').'.root'),
                'free_bytes' => self::freeBytes(),
            ],
            'models' => [
                'transcription' => config('transcription.model', 'unknown').' ('.config('transcription.worker_url', 'unknown').')',
                'translation' => config('translation.provider', 'unknown').' / '.config('translation.model', 'unknown'),
                'note' => 'Phase A rehearsal does not invoke real inference; model identifiers are recorded for target-run comparability only.',
            ],
            'application' => [
                'app_env' => config('app.env'),
                'app_debug' => config('app.debug'),
                'media_max_upload_bytes' => config('media.max_upload_bytes'),
                'deployment_min_free_bytes' => config('deployment.min_free_bytes'),
            ],
        ];
    }

    private static function commit(): string
    {
        try {
            $head = @file_get_contents(base_path('.git/HEAD'));

            if (! is_string($head)) {
                return 'unknown (no .git/HEAD)';
            }

            $head = trim($head);

            if (str_starts_with($head, 'ref:')) {
                $ref = base_path('.git/'.trim(substr($head, 4)));
                $sha = @file_get_contents($ref);

                return is_string($sha) ? trim($sha).' ('.trim(substr($head, 4)).')' : $head.' (unresolved)';
            }

            return $head;
        } catch (\Throwable) {
            return 'unknown (collection failed)';
        }
    }

    /**
     * @return array<string, string>
     */
    private static function cpu(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $name = (string) @shell_exec('wmic cpu get name /value 2>NUL');
            $cores = (string) @shell_exec('wmic cpu get NumberOfCores /value 2>NUL');

            return ['name' => trim($name) !== '' ? trim($name) : 'unknown', 'cores' => trim($cores) !== '' ? trim($cores) : 'unknown'];
        }

        $info = @file_get_contents('/proc/cpuinfo');

        if (! is_string($info)) {
            return ['name' => 'unknown', 'cores' => 'unknown'];
        }

        preg_match('/model name\s*:\s*(.+)/', $info, $m);

        return ['name' => trim($m[1] ?? 'unknown'), 'cores' => (string) substr_count($info, 'processor')];
    }

    /**
     * @return array<string, mixed>
     */
    private static function memory(): array
    {
        $total = null;

        if (PHP_OS_FAMILY === 'Windows') {
            $out = (string) @shell_exec('wmic computersystem get TotalPhysicalMemory /value 2>NUL');
            if (preg_match('/TotalPhysicalMemory=(\d+)/', $out, $m)) {
                $total = (int) $m[1];
            }
        } else {
            $info = @file_get_contents('/proc/meminfo');
            if (is_string($info) && preg_match('/MemTotal:\s*(\d+)\s*kB/', $info, $m)) {
                $total = ((int) $m[1]) * 1024;
            }
        }

        return ['total_bytes' => $total, 'php_usage_bytes' => memory_get_usage(true), 'php_peak_bytes' => memory_get_peak_usage(true)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function database(): array
    {
        try {
            $driver = DB::getDriverName();
        } catch (\Throwable) {
            $driver = 'unknown (connection failed)';
        }

        return [
            'default_connection' => config('database.default'),
            'driver' => $driver,
            'note' => 'Phase A substitute infrastructure expects sqlite; the target run requires the pg production store (P7-002).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function redis(): array
    {
        return [
            'client' => config('database.redis.client', 'unknown'),
            'default_host' => config('database.redis.default.host', 'unknown').':'.config('database.redis.default.port', 'unknown'),
            'queue_connection' => config('queue.connections.redis.connection', 'default'),
            'note' => 'Phase A rehearsal uses loopback/local Redis or the database queue; supervised target Redis belongs to the target run.',
        ];
    }

    private static function queueDepth(): int|string
    {
        try {
            if (config('queue.default') !== 'database') {
                return 'n/a (non-database driver: '.((string) config('queue.default')).')';
            }

            return (int) DB::table(config('queue.connections.database.table', 'jobs'))->count();
        } catch (\Throwable) {
            return 'unknown (inspection failed)';
        }
    }

    private static function freeBytes(): int|string
    {
        try {
            $root = config('filesystems.disks.'.config('media.storage_disk', 'local').'.root');

            if (! is_string($root)) {
                return 'unknown (no root configured)';
            }

            $free = @disk_free_space($root);

            return $free === false ? 'unknown (disk_free_space failed)' : (int) $free;
        } catch (\Throwable) {
            return 'unknown (inspection failed)';
        }
    }
}
