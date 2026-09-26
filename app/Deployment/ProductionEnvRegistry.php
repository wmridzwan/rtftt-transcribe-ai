<?php

namespace App\Deployment;

/**
 * Central production-env key registry (P7-001).
 *
 * One authoritative list of production-relevant environment keys: each entry
 * declares its requirement level (required in production / advisory /
 * dev-only), its value shape, and its owning task. Later tasks register
 * their own keys here (ClamAV keys → P7-006, backup keys → P7-007) instead
 * of scattering ownership.
 *
 * This registry owns *shape knowledge*, not enforcement: the boot guards
 * (`ProductionConfigGuard`, `ProductionPostureChecks`) own enforcement.
 * A CI test fails when `.env.example` gains a production-relevant key that
 * is neither registered nor explicitly dev-only, forcing explicit
 * ownership of every new key.
 */
final class ProductionEnvRegistry
{
    public const REQUIRED = 'required';

    public const ADVISORY = 'advisory';

    public const DEV_ONLY = 'dev-only';

    /**
     * @return array<string, array{requirement: string, shape: string|null, owner: string, description: string}>
     */
    public static function definitions(): array
    {
        return [
            // --- Application identity ---
            'APP_NAME' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Display name; no production safety impact.'],
            'APP_ENV' => ['requirement' => self::REQUIRED, 'shape' => 'nonempty', 'owner' => 'P7-008', 'description' => 'Must be "production" on the production host.'],
            'APP_KEY' => ['requirement' => self::REQUIRED, 'shape' => 'secret', 'owner' => 'P7-008', 'description' => 'Generated application key; enforced by ProductionConfigGuard.'],
            'APP_DEBUG' => ['requirement' => self::REQUIRED, 'shape' => 'bool', 'owner' => 'P7-008', 'description' => 'Must be false in production; enforced by ProductionConfigGuard.'],
            'APP_URL' => ['requirement' => self::REQUIRED, 'shape' => 'url', 'owner' => 'P7-008', 'description' => 'Canonical application URL.'],
            'APP_LOCALE' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Locale default.'],
            'APP_FALLBACK_LOCALE' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Locale fallback.'],
            'APP_FAKER_LOCALE' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Test/seeder locale.'],
            'APP_MAINTENANCE_DRIVER' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Maintenance-mode driver.'],
            'BCRYPT_ROUNDS' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Hash cost factor.'],

            // --- Logging (P7-005 channel; shapes validated, channel owned by P7-005) ---
            'LOG_CHANNEL' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-005', 'description' => 'Default log channel.'],
            'LOG_STACK' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Stack members.'],
            'LOG_DEPRECATIONS_CHANNEL' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Deprecation channel.'],
            'LOG_LEVEL' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-005', 'description' => 'Minimum log level.'],

            // --- Datastore (D7-01: self-hosted PostgreSQL target; advisory until P7-002) ---
            'DB_CONNECTION' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-002', 'description' => 'Pre-migration sqlite; post-migration pgsql (flip owned by P7-002).'],
            'DB_HOST' => ['requirement' => self::ADVISORY, 'shape' => 'host', 'owner' => 'P7-002', 'description' => 'Postgres host (post-P7-002).'],
            'DB_PORT' => ['requirement' => self::ADVISORY, 'shape' => 'port', 'owner' => 'P7-002', 'description' => 'Postgres port (post-P7-002).'],
            'DB_DATABASE' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-002', 'description' => 'Postgres database (post-P7-002).'],
            'DB_USERNAME' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-002', 'description' => 'Postgres role (post-P7-002).'],
            'DB_PASSWORD' => ['requirement' => self::ADVISORY, 'shape' => 'secret', 'owner' => 'P7-002', 'description' => 'Postgres credentials (post-P7-002; never in repo).'],
            'DB_SSLMODE' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-002', 'description' => 'Postgres sslmode (prefer/require/...; post-P7-002).'],

            // --- Sessions / cache (framework; no production-specific posture) ---
            'SESSION_DRIVER' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Session driver.'],
            'SESSION_LIFETIME' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Session lifetime minutes.'],
            'SESSION_ENCRYPT' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Session encryption flag.'],
            'SESSION_PATH' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Session cookie path.'],
            'SESSION_DOMAIN' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Session cookie domain.'],
            'BROADCAST_CONNECTION' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Broadcast driver.'],
            'FILESYSTEM_DISK' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Default filesystem disk.'],
            'CACHE_STORE' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-008', 'description' => 'Cache backend.'],
            'MEMCACHED_HOST' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Unused unless memcached selected.'],
            'VITE_APP_NAME' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Frontend build name.'],

            // --- Queue backbone (D7-02; keys owned by P7-003, shapes registered here) ---
            'QUEUE_CONNECTION' => ['requirement' => self::REQUIRED, 'shape' => 'connection', 'owner' => 'P7-003', 'description' => 'Must be "redis" in production; enforced by ProductionConfigGuard.'],
            'DB_QUEUE_RETRY_AFTER' => ['requirement' => self::ADVISORY, 'shape' => 'bytes', 'owner' => 'P7-003', 'description' => 'Database-queue retry_after seconds.'],
            'REDIS_QUEUE_RETRY_AFTER' => ['requirement' => self::REQUIRED, 'shape' => 'bytes', 'owner' => 'P7-003', 'description' => 'Must satisfy the provider < job < retry_after invariant; enforced by queue guards.'],
            'REDIS_CLIENT' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-003', 'description' => 'Redis client driver.'],
            'REDIS_HOST' => ['requirement' => self::REQUIRED, 'shape' => 'host', 'owner' => 'P7-001', 'description' => 'Redis host; loopback-only unless authenticated (TD-004 posture).'],
            'REDIS_PASSWORD' => ['requirement' => self::ADVISORY, 'shape' => null, 'owner' => 'P7-001', 'description' => 'Conditionally required: mandatory whenever Redis leaves loopback or is shared (TD-004); enforced by RedisPosture, not by shape. Null/empty is legal only for loopback dev.'],
            'REDIS_PORT' => ['requirement' => self::REQUIRED, 'shape' => 'port', 'owner' => 'P7-001', 'description' => 'Redis port.'],
            'RTFTT_TRANSCRIPTION_QUEUE_CONNECTION' => ['requirement' => self::ADVISORY, 'shape' => 'connection', 'owner' => 'P7-003', 'description' => 'Per-queue override; falls back to QUEUE_CONNECTION.'],
            'RTFTT_TRANSLATION_QUEUE_CONNECTION' => ['requirement' => self::ADVISORY, 'shape' => 'connection', 'owner' => 'P7-003', 'description' => 'Per-queue override; falls back to QUEUE_CONNECTION.'],
            'RTFTT_TRANSCRIPTION_JOB_TIMEOUT_SECONDS' => ['requirement' => self::ADVISORY, 'shape' => 'bytes', 'owner' => 'P7-003', 'description' => 'Transcription job timeout seconds.'],
            'RTFTT_TRANSCRIPTION_RETRY_AFTER_SECONDS' => ['requirement' => self::ADVISORY, 'shape' => 'bytes', 'owner' => 'P7-003', 'description' => 'Transcription retry_after seconds.'],

            // --- Workers (authenticated internal HTTP; enforced by ProductionConfigGuard) ---
            'RTFTT_TRANSCRIPTION_WORKER_URL' => ['requirement' => self::REQUIRED, 'shape' => 'url', 'owner' => 'P7-008', 'description' => 'Production worker base URL (never localhost).'],
            'RTFTT_TRANSLATION_WORKER_URL' => ['requirement' => self::REQUIRED, 'shape' => 'url', 'owner' => 'P7-008', 'description' => 'Production worker base URL (never localhost).'],
            'RTFTT_TRANSCRIPTION_WORKER_TOKEN' => ['requirement' => self::REQUIRED, 'shape' => 'secret', 'owner' => 'P7-008', 'description' => 'Worker auth token; never in repo/logs.'],
            'RTFTT_TRANSLATION_WORKER_TOKEN' => ['requirement' => self::REQUIRED, 'shape' => 'secret', 'owner' => 'P7-008', 'description' => 'Worker auth token; never in repo/logs.'],
            'RTFTT_WHISPER_MODEL' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-001', 'description' => 'Canonical model label (large-v3).'],
            'RTFTT_MIN_FREE_BYTES' => ['requirement' => self::ADVISORY, 'shape' => 'bytes', 'owner' => 'P7-001', 'description' => 'Minimum free bytes on the storage volume (default 1 GiB).'],

            // --- Media / tooling ---
            'RTFTT_MEDIA_DISK' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-004', 'description' => 'Private storage disk (local per D7-03).'],
            'RTFTT_FFPROBE_PATH' => ['requirement' => self::ADVISORY, 'shape' => 'path', 'owner' => 'P7-009', 'description' => 'FFprobe binary path.'],

            // --- Mail (log driver in this deployment; shapes registered, no posture) ---
            'MAIL_MAILER' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail driver.'],
            'MAIL_SCHEME' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail scheme.'],
            'MAIL_HOST' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail host.'],
            'MAIL_PORT' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail port.'],
            'MAIL_USERNAME' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail username.'],
            'MAIL_PASSWORD' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail password.'],
            'MAIL_FROM_ADDRESS' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail from address.'],
            'MAIL_FROM_NAME' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Mail from name.'],

            // --- Unused third-party surface (registered so the registry is total; no posture) ---
            'AWS_ACCESS_KEY_ID' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Unused (no object storage per D7-03); must stay empty.'],
            'AWS_SECRET_ACCESS_KEY' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Unused; must stay empty.'],
            'AWS_DEFAULT_REGION' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Unused region default.'],
            'AWS_BUCKET' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Unused; must stay empty.'],
            'AWS_USE_PATH_STYLE_ENDPOINT' => ['requirement' => self::DEV_ONLY, 'shape' => null, 'owner' => 'framework', 'description' => 'Unused flag.'],

            // --- P7-006 ClamAV service keys (owner P7-006; shapes authoritative here) ---
            'CLAMAV_ENABLED' => ['requirement' => self::ADVISORY, 'shape' => 'bool', 'owner' => 'P7-006', 'description' => 'Scanner master switch; disabled skips only outside production.'],
            'CLAMAV_HOST' => ['requirement' => self::ADVISORY, 'shape' => 'host', 'owner' => 'P7-006', 'description' => 'ClamAV daemon host; loopback-only unless explicitly allowed.'],
            'CLAMAV_PORT' => ['requirement' => self::ADVISORY, 'shape' => 'port', 'owner' => 'P7-006', 'description' => 'ClamAV daemon TCP port.'],
            'CLAMAV_SOCKET' => ['requirement' => self::ADVISORY, 'shape' => 'path', 'owner' => 'P7-006', 'description' => 'ClamAV unix socket path (preferred over TCP).'],
            'CLAMAV_TIMEOUT_SECONDS' => ['requirement' => self::ADVISORY, 'shape' => 'bytes', 'owner' => 'P7-006', 'description' => 'Per-scan socket timeout; inside the job budget.'],
            'CLAMAV_MAX_SIGNATURE_AGE_HOURS' => ['requirement' => self::ADVISORY, 'shape' => 'bytes', 'owner' => 'P7-006', 'description' => 'Maximum acceptable signature age.'],
            'CLAMAV_UNAVAILABLE_MODE' => ['requirement' => self::ADVISORY, 'shape' => 'nonempty', 'owner' => 'P7-006', 'description' => 'hold|reject when the scanner is unavailable (fail closed).'],

            // --- P7-007 backup keys (owner P7-007; shapes authoritative here) ---
            'RTFTT_BACKUP_TARGET' => ['requirement' => self::ADVISORY, 'shape' => 'path', 'owner' => 'P7-007', 'description' => 'Backup set target directory.'],
            'RTFTT_BACKUP_GENERATIONS' => ['requirement' => self::ADVISORY, 'shape' => 'bytes', 'owner' => 'P7-007', 'description' => 'Retained backup generations.'],
        ];
    }

    public static function isRegistered(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    /**
     * @return string|null a human-readable shape violation, or null when the value fits
     */
    public static function validateValue(string $key, mixed $value): ?string
    {
        $definition = self::definitions()[$key] ?? null;

        if ($definition === null) {
            return sprintf('Env key "%s" is not registered; ownership required before production use.', $key);
        }

        if ($definition['requirement'] === self::DEV_ONLY) {
            return null;
        }

        return match ($definition['shape']) {
            null => null,
            'nonempty' => is_string($value) && $value !== '' ? null : sprintf('Env key "%s" must be a non-empty string.', $key),
            'secret' => is_string($value) && $value !== '' && $value !== 'null' ? null : sprintf('Env key "%s" must be provisioned (never empty, never the literal "null").', $key),
            'url' => is_string($value) && preg_match('~^https?://[^\s/$.?#].[^\s]*$~i', $value) === 1
                ? null
                : sprintf('Env key "%s" must be an http(s) URL.', $key),
            'host' => is_string($value) && $value !== '' && preg_match('#^[A-Za-z0-9.\\-_/]+$#', $value) === 1
                ? null
                : sprintf('Env key "%s" must be a valid host or socket path.', $key),
            'port' => is_numeric($value) && (int) $value >= 1 && (int) $value <= 65535
                ? null
                : sprintf('Env key "%s" must be a TCP port (1-65535).', $key),
            'bytes' => is_numeric($value) && (int) $value >= 0
                ? null
                : sprintf('Env key "%s" must be a non-negative integer.', $key),
            'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true)
                ? null
                : sprintf('Env key "%s" must be boolean.', $key),
            'path' => is_string($value) && $value !== ''
                ? null
                : sprintf('Env key "%s" must be a non-empty path.', $key),
            'connection' => $value === null || $value === '' || (is_string($value) && preg_match('#^[A-Za-z0-9_\\-]+$#', $value) === 1)
                ? null
                : sprintf('Env key "%s" must be a valid connection name or empty (falls back to default).', $key),
            default => sprintf('Env key "%s" has an unknown shape "%s".', $key, (string) $definition['shape']),
        };
    }

    /**
     * @return list<string> keys active in `.env.example` that are neither
     *                      registered nor explicitly dev-only exempt
     */
    public static function unregisteredExampleKeys(string $examplePath): array
    {
        if (! is_readable($examplePath)) {
            return [];
        }

        $missing = [];

        foreach (explode("\n", (string) file_get_contents($examplePath)) as $line) {
            if (preg_match('/^([A-Z][A-Z0-9_]+)=/', trim($line), $matches) !== 1) {
                continue;
            }

            if (! self::isRegistered($matches[1])) {
                $missing[] = $matches[1];
            }
        }

        return array_values(array_unique($missing));
    }
}
