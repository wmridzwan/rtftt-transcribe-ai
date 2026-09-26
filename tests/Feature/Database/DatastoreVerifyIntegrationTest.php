<?php

use Illuminate\Support\Facades\Artisan;

/*
 * P7-002: verify/diagnostics/hook integration. The datastore sub-check is
 * additive (pre-existing P7-008 checks unbroken); the pre-migrate hook
 * gate is exercised through P7-007's contract (OK only with a fresh
 * verified set, REFUSED otherwise); DB secrets never ship in the repo.
 */

it('reports the datastore sub-check inside deployment:verify', function (): void {
    $exit = Artisan::call('deployment:verify');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Datastore:');
});

it('refuses pre-migrate without a fresh verified backup set', function (): void {
    $exit = Artisan::call('backup:pre-migrate');

    expect($exit)->not->toBe(0)
        ->and(Artisan::output())->toContain('PRE-MIGRATE BACKUP REFUSED');
});

it('keeps DB credentials out of the committed example env', function (): void {
    $matches = [];

    foreach (explode("\n", (string) file_get_contents(base_path('.env.example'))) as $line) {
        if (preg_match('/^#?\s*DB_PASSWORD\s*=(.*)$/', trim($line), $found) === 1) {
            $matches[] = trim($found[1]);
        }
    }

    expect($matches)->not->toBeEmpty();

    foreach ($matches as $value) {
        expect($value)->toBe('');
    }
});

it('provisions no real database password anywhere in app or config', function (): void {
    $hits = [];

    foreach (['app', 'config', 'routes'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir))) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match('/DB_PASSWORD\s*[,=]\s*[\'"](?!["\'])(?!null)[^\'"]+[\'"]/', $contents) === 1) {
                $hits[] = $file->getPathname();
            }
        }
    }

    expect($hits)->toBe([]);
});
