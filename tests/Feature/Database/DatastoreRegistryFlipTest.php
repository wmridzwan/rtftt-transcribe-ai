<?php

use App\Deployment\ProductionEnvRegistry;

/*
 * P7-002: registry flip record. DB_* keys stay ADVISORY in the static
 * registry (no fork) with P7-002 ownership; the advisory→required flip
 * is enforced by DatastorePosture once pgsql is selected. DB_SSLMODE is
 * registered because the pgsql config reads it and .env.example documents
 * it — an unregistered key would fail the registry-integrity gate.
 */

it('keeps DB keys advisory and owned by P7-002 until cutover', function (): void {
    $definitions = ProductionEnvRegistry::definitions();

    foreach (['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SSLMODE'] as $key) {
        expect($definitions)->toHaveKey($key)
            ->and($definitions[$key]['owner'])->toBe('P7-002')
            ->and($definitions[$key]['requirement'])->toBe(ProductionEnvRegistry::ADVISORY);
    }
});

it('registers every DB key present in .env.example', function (): void {
    expect(ProductionEnvRegistry::unregisteredExampleKeys(base_path('.env.example')))->toBe([]);
});

it('validates pgsql value shapes without provisioning secrets', function (): void {
    expect(ProductionEnvRegistry::validateValue('DB_PORT', '5432'))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('DB_PORT', '99999'))->not->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('DB_HOST', 'db.internal'))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('DB_PASSWORD', 'provisioned'))->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('DB_PASSWORD', ''))->not->toBeNull()
        ->and(ProductionEnvRegistry::validateValue('DB_SSLMODE', 'prefer'))->toBeNull();
});

it('leaves the pgsql connection configured with env-driven credentials and sslmode', function (): void {
    $pgsql = config('database.connections.pgsql');

    expect($pgsql['driver'])->toBe('pgsql')
        ->and($pgsql)->toHaveKey('sslmode')
        ->and($pgsql['search_path'])->toBe('public');
});

it('keeps sqlite the default connection until cutover', function (): void {
    expect(config('database.default'))->toBe('sqlite');
});
