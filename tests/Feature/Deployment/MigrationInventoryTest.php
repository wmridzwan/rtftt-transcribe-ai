<?php

/*
 * P7-008: historical migrations are immutable. The pinned sha256 manifest
 * (`deploy/migrations-inventory.json`) fails the suite if a recorded
 * migration is modified or removed. New migrations (e.g. P7-002) are
 * allowed but must be appended to the manifest by their owning task.
 */

it('keeps every pinned historical migration byte-identical', function (): void {
    $manifestPath = base_path('deploy/migrations-inventory.json');

    expect(is_file($manifestPath))->toBeTrue('migration inventory manifest must exist');

    $manifest = json_decode((string) file_get_contents($manifestPath), true);

    expect($manifest)->toBeArray()
        ->and($manifest['migrations'])->toBeArray()->not->toBeEmpty();

    $drifted = [];

    foreach ($manifest['migrations'] as $file => $hash) {
        $path = database_path('migrations/'.$file);

        if (! is_file($path) || hash_file('sha256', $path) !== $hash) {
            $drifted[] = $file;
        }
    }

    expect($drifted)->toBe([], 'historical migrations must never change: '.implode(', ', $drifted));
});

it('covers every migration file present when the manifest was frozen', function (): void {
    $manifest = json_decode(
        (string) file_get_contents(base_path('deploy/migrations-inventory.json')),
        true
    );

    $files = collect(scandir(database_path('migrations')) ?: [])
        ->filter(fn ($file): bool => str_ends_with((string) $file, '.php'))
        ->map(fn ($file): string => basename((string) $file))
        ->values()
        ->all();

    // Files newer than the manifest are reported (owning task must append
    // them); files older than it must all be pinned.
    $unpinned = array_values(array_diff($files, array_keys($manifest['migrations'])));

    expect($unpinned)->toBe([], 'new migrations must be appended to the manifest: '.implode(', ', $unpinned));
});
