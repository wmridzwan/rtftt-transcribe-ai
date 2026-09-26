<?php

/*
 * P7-001: secrets hygiene. No secret material in tracked shape-only
 * templates: token/password/key lines in `.env.example` must be empty
 * or the literal "null" (Laravel converts it to real null); no
 * provisioned-looking key material may appear.
 */

it('keeps secret-shaped example values unprovisioned', function (): void {
    $lines = explode("\n", (string) file_get_contents(base_path('.env.example')));
    $offenders = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if (preg_match('/^([A-Z][A-Z0-9_]*?(?:TOKEN|PASSWORD|SECRET|PRIVATE_KEY))=(.*)$/', $line, $matches) !== 1) {
            continue;
        }

        $value = trim($matches[2], " \t\"'");

        if ($value !== '' && strtolower($value) !== 'null') {
            $offenders[] = $matches[1].'='.$value;
        }
    }

    expect($offenders)->toBe([]);
});

it('carries no provisioned application key material in the example', function (): void {
    $content = (string) file_get_contents(base_path('.env.example'));

    expect($content)->not->toContain('base64:')
        ->and($content)->not->toContain('BEGIN ');
});

it('keeps private-key material out of application and config sources', function (): void {
    $hits = [];

    foreach (['app', 'config'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory)));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            if (str_contains($contents, 'PRIVATE KEY') || str_contains($contents, 'sk-live-')) {
                $hits[] = $file->getPathname();
            }
        }
    }

    expect($hits)->toBe([]);
});
