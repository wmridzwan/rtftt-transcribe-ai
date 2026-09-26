<?php

namespace App\Deployment;

use Throwable;

/**
 * Redis security-posture record + certification (P7-001, TD-004 / HPO-04).
 *
 * P7-003 defined the required posture (supervision spec §8: "TD-004
 * definition; P7-001 certifies"); this class performs the certification:
 * it records the per-environment posture (host, loopback status, auth,
 * version) and reports violations. Rule:
 *
 * - loopback host (127.0.0.1 / ::1 / localhost) without a password:
 *   acceptable for dev; recorded, never a violation (isolation *is* the
 *   control there);
 * - any non-loopback host without a password: violation in every
 *   environment (fails closed at boot in production via
 *   `ProductionPostureChecks`);
 * - version capture: best-effort live `INFO server` read; unreachable
 *   Redis records `reachable: false` with a null version rather than
 *   failing the posture verdict (connectivity surfaces elsewhere).
 *
 * Pure logic with injectable seams so tests never need a live server;
 * live capture is covered by a skip-if-unreachable test.
 */
final class RedisPosture
{
    /** @var list<string> hosts that count as loopback-isolated */
    public const LOOPBACK_HOSTS = ['127.0.0.1', '::1', 'localhost'];

    /**
     * @param  array{host?: mixed, port?: mixed, password?: mixed}|null  $connection
     * @param  string|null  $version  live-captured version, or null when uncaptured
     * @param  bool|null  $reachable  live reachability, or null when not probed
     * @return array{host: string, port: int, password_set: bool, loopback: bool, version: string|null, reachable: bool|null, violations: list<string>}
     */
    public static function record(?array $connection = null, ?string $version = null, ?bool $reachable = null): array
    {
        $connection ??= (array) config('database.redis.default', []);
        $host = is_string($connection['host'] ?? null) && $connection['host'] !== '' ? $connection['host'] : '127.0.0.1';
        $port = isset($connection['port']) && is_numeric($connection['port']) ? (int) $connection['port'] : 6379;
        $password = $connection['password'] ?? null;
        $passwordSet = is_string($password) && $password !== '' && $password !== 'null';
        $loopback = in_array(strtolower($host), self::LOOPBACK_HOSTS, true);

        $violations = [];

        if (! $passwordSet && ! $loopback) {
            $violations[] = sprintf(
                'Redis at "%s:%d" has no password and is not loopback-isolated (TD-004); passwordless Redis relies entirely on network isolation and is prohibited outside loopback.',
                $host,
                $port
            );
        }

        return [
            'host' => $host,
            'port' => $port,
            'password_set' => $passwordSet,
            'loopback' => $loopback,
            'version' => $version,
            'reachable' => $reachable,
            'violations' => $violations,
        ];
    }

    /**
     * Best-effort live `INFO server` version capture. Returns the
     * `redis_version` string, or null when Redis is unreachable or the
     * reply cannot be parsed. Never throws.
     */
    public static function captureVersion(): ?string
    {
        try {
            $reply = app('redis')->connection('default')->command('INFO', ['server']);
        } catch (Throwable) {
            return null;
        }

        // Shape 1: associative map (phpredis INFO) — e.g. ['redis_version' => '7.2.4', ...].
        if (is_array($reply) && isset($reply['redis_version']) && is_string($reply['redis_version'])) {
            return trim($reply['redis_version']);
        }

        foreach (is_array($reply) ? $reply : explode("\n", (string) $reply) as $line) {
            if (is_string($line) && preg_match('/^redis_version:(.+)\r?$/', trim($line), $matches) === 1) {
                return trim($matches[1]);
            }

            if (is_array($line)) {
                foreach ($line as $sub) {
                    if (is_string($sub) && preg_match('/^redis_version:(.+)$/', trim($sub), $matches) === 1) {
                        return trim($matches[1]);
                    }
                }
            }
        }

        return null;
    }

    public static function isReachable(): bool
    {
        try {
            $pong = app('redis')->connection('default')->ping();

            return $pong === true || $pong === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }
}
