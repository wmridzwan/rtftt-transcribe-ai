<?php

namespace App\Deployment;

/**
 * Datastore posture evaluation (P7-002).
 *
 * Owns the P7-002 registry flip: the `DB_*` keys are ADVISORY in the
 * P7-001 registry while the default connection is sqlite (pre-migration),
 * and become effectively REQUIRED once `DB_CONNECTION=pgsql` selects the
 * D7-01 production store. The registry rows stay static (no fork); this
 * class is where the conditional requirement lives.
 *
 * Pure `evaluate()` logic with injectable seams (connection name + env
 * map) so tests never depend on the machine. Called additively from
 * `ProductionPostureChecks::violations()` (P7-001 owns the guard; P7-002
 * contributes this rule set through its extension point) and from the
 * `deployment:verify` datastore sub-check.
 *
 * Only `sqlite` (pre-cutover dev/test) and `pgsql` (D7-01 production
 * target) are legal selections. Anything else fails closed.
 */
final class DatastorePosture
{
    /**
     * @param  array<string, mixed>|null  $env  env map override (tests); null reads the resolved pgsql config
     * @return list<string> human-readable violations; empty when safe
     */
    public static function evaluate(?string $connection, ?array $env = null): array
    {
        $connection ??= (string) config('database.default', 'sqlite');

        if ($connection === 'sqlite') {
            return [];
        }

        if ($connection !== 'pgsql') {
            return [
                sprintf(
                    'DB_CONNECTION "%s" is not a supported production selection; only "sqlite" (pre-cutover) or "pgsql" (D7-01 target) may be selected.',
                    $connection
                ),
            ];
        }

        // Resolved config values (never env() outside config/): this also
        // catches stale config caches, not just missing environment.
        $env ??= [
            'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_PORT' => config('database.connections.pgsql.port'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password'),
        ];

        $violations = [];

        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
            $value = $env[$key] ?? null;

            if (! is_string($value) || $value === '') {
                $violations[] = sprintf(
                    '%s must be provisioned when DB_CONNECTION=pgsql (D7-01 flip: advisory until cutover, required after).',
                    $key
                );
            }
        }

        $port = $env['DB_PORT'] ?? null;

        if (! is_numeric($port) || (int) $port < 1 || (int) $port > 65535) {
            $violations[] = 'DB_PORT must be a TCP port (1-65535) when DB_CONNECTION=pgsql.';
        }

        $password = $env['DB_PASSWORD'] ?? null;

        if (! is_string($password) || $password === '' || $password === 'null') {
            $violations[] = 'DB_PASSWORD must be provisioned (never empty, never the literal "null") when DB_CONNECTION=pgsql; credentials live in the environment only.';
        }

        return $violations;
    }
}
