<?php

/*
 * P7-002: static PostgreSQL compatibility audit, enforced in CI.
 * Mirrors `verification/p7-002/MIGRATION-AUDIT.md`: no SQLite-only DML,
 * no unsupported schema operations, index names within the PostgreSQL
 * 63-byte identifier limit, and the pg parity migration pinned in the
 * P7-008 inventory.
 */

function migrationSources(): array
{
    $sources = [];

    foreach (glob(database_path('migrations/*.php')) ?: [] as $path) {
        $sources[basename((string) $path)] = (string) file_get_contents((string) $path);
    }

    return $sources;
}

it('uses no SQLite-only DML or unsupported schema operations', function (): void {
    $offenders = [];

    foreach (migrationSources() as $file => $source) {
        foreach (['insertOrIgnore', 'orIgnore', 'renameColumn', '->enum('] as $pattern) {
            if (str_contains($source, $pattern)) {
                $offenders[] = $file.':'.$pattern;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps every index name within the PostgreSQL 63-byte limit', function (): void {
    $offenders = [];

    foreach (migrationSources() as $file => $source) {
        if (preg_match_all('/(?:unique|index)\(\s*[\'"]([^\'"]+)[\'"]/i', $source, $matches) === 1) {
            foreach ($matches[1] as $name) {
                if (strlen($name) > 63) {
                    $offenders[] = $file.':'.$name;
                }
            }
        }

        foreach (['processing_jobs_active_attempt_unique', 'translations_active_target_unique'] as $name) {
            if (str_contains($source, $name)) {
                expect(strlen($name))->toBeLessThanOrEqual(63);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('pins the pg parity migration in the P7-008 inventory', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(base_path('deploy/migrations-inventory.json')),
        true
    );

    $file = '2026_09_26_130000_add_pg_partial_unique_indexes.php';

    expect($manifest['migrations'])->toHaveKey($file)
        ->and(is_file(database_path('migrations/'.$file)))->toBeTrue()
        ->and(hash_file('sha256', database_path('migrations/'.$file)))->toBe($manifest['migrations'][$file]);
});

it('documents the sqlite-gated partial indexes as the pg parity rationale', function (): void {
    $audit = base_path('verification/p7-002/MIGRATION-AUDIT.md');

    expect(is_file($audit))->toBeTrue();

    $contents = (string) file_get_contents($audit);

    expect($contents)->toContain('processing_jobs_active_attempt_unique')
        ->and($contents)->toContain('translations_active_target_unique')
        ->and($contents)->toContain('2026_09_26_130000_add_pg_partial_unique_indexes');
});
