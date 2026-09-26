<?php

use App\Deployment\DatastorePosture;

/*
 * P7-002: D7-01 datastore posture flip. sqlite (pre-cutover) stays legal;
 * pgsql requires provisioned credentials; any other driver fails closed.
 * Pure logic with injectable seams; enforced additively through the
 * P7-001 posture guard, never as a fork of it.
 */

function pgsqlCompleteEnv(): array
{
    return [
        'DB_HOST' => 'db.internal',
        'DB_PORT' => '5432',
        'DB_DATABASE' => 'rtftt',
        'DB_USERNAME' => 'rtftt',
        'DB_PASSWORD' => 'provisioned-secret',
    ];
}

it('accepts sqlite as the legal pre-cutover selection', function (): void {
    expect(DatastorePosture::evaluate('sqlite', []))->toBe([]);
});

it('accepts a fully provisioned pgsql selection', function (): void {
    expect(DatastorePosture::evaluate('pgsql', pgsqlCompleteEnv()))->toBe([]);
});

it('requires every pgsql credential once pgsql is selected', function (): void {
    $joined = implode(' ', DatastorePosture::evaluate('pgsql', []));

    expect($joined)->toContain('DB_HOST')
        ->and($joined)->toContain('DB_DATABASE')
        ->and($joined)->toContain('DB_USERNAME')
        ->and($joined)->toContain('DB_PORT')
        ->and($joined)->toContain('DB_PASSWORD');
});

it('rejects an empty or literal-null pgsql password', function (): void {
    foreach (['', 'null'] as $password) {
        $env = pgsqlCompleteEnv();
        $env['DB_PASSWORD'] = $password;

        expect(implode(' ', DatastorePosture::evaluate('pgsql', $env)))->toContain('DB_PASSWORD');
    }
});

it('rejects an out-of-range pgsql port', function (): void {
    $env = pgsqlCompleteEnv();
    $env['DB_PORT'] = '99999';

    expect(implode(' ', DatastorePosture::evaluate('pgsql', $env)))->toContain('DB_PORT');
});

it('fails closed on unsupported drivers', function (): void {
    foreach (['mysql', 'mariadb', 'sqlsrv'] as $driver) {
        expect(implode(' ', DatastorePosture::evaluate($driver, pgsqlCompleteEnv())))
            ->toContain('not a supported production selection');
    }
});

it('contributes no violations to the posture guard on the default sqlite connection', function (): void {
    expect(config('database.default'))->toBe('sqlite')
        ->and(DatastorePosture::evaluate(null))->toBe([]);
});
