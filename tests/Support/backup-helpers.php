<?php

/*
 * Shared backup test fixtures (pre-Linux remediation batch).
 *
 * Loaded once from tests/Pest.php so any backup test file can be run in
 * isolation, not only as part of the whole directory/suite. Temp-file
 * sqlite sources keep the suite independent of the :memory: test
 * database; RefreshDatabase state is never touched (the manager uses
 * raw PDO + files, never the Laravel connection). All temp artifacts
 * are removed in finally blocks by callers.
 */

if (! function_exists('backupTempDb')) {
    function backupTempDb(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'p7007-db-').'.sqlite';
        $pdo = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE migrations (migration VARCHAR(255), batch INT)');
        $pdo->exec("INSERT INTO migrations VALUES ('2026_01_01_000001_probe.php', 1)");
        unset($pdo);

        return $path;
    }
}

if (! function_exists('backupTempTarget')) {
    function backupTempTarget(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'p7007-target-'.uniqid();
        mkdir($dir, 0750, true);

        return $dir;
    }
}

if (! function_exists('removeDirectoryTree')) {
    function removeDirectoryTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}

if (! function_exists('withBackupIsolation')) {
    /**
     * Point the manager at an isolated sqlite file + target for one test.
     * Restores config afterwards; never purges the Laravel connection
     * (the manager does not use it).
     */
    function withBackupIsolation(string $db, string $target, callable $test): void
    {
        $originalDb = config('database.connections.sqlite.database');
        $originalTarget = config('backup.target');

        config()->set('database.connections.sqlite.database', $db);
        config()->set('backup.target', $target);

        try {
            $test();
        } finally {
            config()->set('database.connections.sqlite.database', $originalDb);
            config()->set('backup.target', $originalTarget);
        }
    }
}
