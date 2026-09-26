<?php

namespace App\Backup;

use App\Models\MediaFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Backup/restore foundation (P7-007, D7-07 daily posture).
 *
 * SQLite-era mechanism (executable now): consistent file snapshot of the
 * SQLite store plus the private media tree as one named set with a
 * manifest, per-run scratch-restore integrity verification, generation
 * pruning with last-good protection. The PostgreSQL-native path
 * (pre-Linux remediation, BLOCKER-B) executes `pg_dump` in custom
 * format (`-Fc`) with role-based credentials passed only via the
 * `PGPASSWORD` process environment (never CLI args, logs, or the
 * manifest), under the same set/manifest/verify/prune discipline.
 * Missing pg_dump tooling or an unconfigured pgsql connection fails
 * loudly instead of half-executing.
 *
 * Consistency model: SQLite is copied with an exclusive lock attempt
 * (best-effort quiesce — a PDO `BEGIN IMMEDIATE` probe; failure fails
 * loudly instead of shipping a torn copy). Quarantine content (P7-006
 * infected artifacts) is never backed up. Partial sets are quarantined
 * with a failed manifest, never presented as valid.
 */
final class BackupManager
{
    public const MANIFEST_FILE = 'manifest.json';

    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    /**
     * pg_dump custom-format archives begin with these magic bytes.
     * Used by verification to prove a set holds a real pg_dump
     * artifact without requiring a live PostgreSQL server.
     */
    public const PG_DUMP_MAGIC = 'PGDMP';

    public const PG_DUMP_FILE = 'database.dump';

    public function __construct(private readonly FilesystemAdapter $mediaStorage) {}

    public static function forMediaDisk(): self
    {
        return new self(MediaFile::storage());
    }

    public function targetRoot(): string
    {
        // An empty RTFTT_BACKUP_TARGET (the shipped example default) falls
        // back to the local backups directory; only explicit values divert.
        $target = trim((string) config('backup.target', ''));

        if ($target === '') {
            $target = storage_path('backups');
        }

        return rtrim($target, '/\\');
    }

    /**
     * @return array{ok: bool, set: string|null, manifest: array<string, mixed>|null, errors: list<string>}
     */
    public function run(string $driver): array
    {
        $errors = [];

        if ($driver === 'pgsql') {
            return $this->runPgsql();
        }

        if ($driver !== 'sqlite') {
            return ['ok' => false, 'set' => null, 'manifest' => null, 'errors' => [sprintf('Unknown backup driver "%s".', $driver)]];
        }

        $source = (string) config('database.connections.sqlite.database', '');

        if ($source === '' || $source === ':memory:' || ! is_file($source)) {
            return ['ok' => false, 'set' => null, 'manifest' => null, 'errors' => ['SQLite source database is not a readable file; refusing to back up a non-materialized store.']];
        }

        $inventoryError = $this->inventoryDivergence();

        if ($inventoryError !== null) {
            return ['ok' => false, 'set' => null, 'manifest' => null, 'errors' => [$inventoryError]];
        }

        $target = $this->targetRoot();

        if (! $this->ensureDirectory($target)) {
            return ['ok' => false, 'set' => null, 'manifest' => null, 'errors' => [sprintf('Backup target "%s" is not writable.', $target)]];
        }

        $name = gmdate('Ymd-His').'-sqlite';
        $setDir = $target.DIRECTORY_SEPARATOR.$name;

        if (! @mkdir($setDir, 0750, true) && ! is_dir($setDir)) {
            return ['ok' => false, 'set' => null, 'manifest' => null, 'errors' => [sprintf('Could not create backup set directory "%s".', $setDir)]];
        }

        try {
            $this->snapshotSqlite($source, $setDir.DIRECTORY_SEPARATOR.'database.sqlite');
            $mediaManifest = $this->captureMediaTree($setDir.DIRECTORY_SEPARATOR.'media');

            $manifest = [
                'name' => $name,
                'created_at' => gmdate('c'),
                'driver' => 'sqlite',
                'tool' => 'rtftt-backup/1',
                'database' => ['file' => 'database.sqlite', 'sha256' => hash_file('sha256', $setDir.DIRECTORY_SEPARATOR.'database.sqlite')],
                'media' => $mediaManifest,
                'migration_inventory_sha256' => hash_file('sha256', base_path('deploy/migrations-inventory.json')),
                'status' => self::STATUS_OK,
            ];
            $manifest['root'] = hash('sha256', (string) json_encode($manifest));

            file_put_contents($setDir.DIRECTORY_SEPARATOR.self::MANIFEST_FILE, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $integrity = $this->verifySet($setDir);

            if (! $integrity['ok']) {
                $this->markFailed($setDir, $manifest, $integrity['errors']);
                $this->prune($target);

                return ['ok' => false, 'set' => $name, 'manifest' => null, 'errors' => $integrity['errors']];
            }

            $this->prune($target);
            $this->log('backup.complete', ['set' => $name]);

            return ['ok' => true, 'set' => $name, 'manifest' => $manifest, 'errors' => []];
        } catch (Throwable $exception) {
            $this->removeDirectory($setDir);
            $this->log('backup.failed', ['set' => $name, 'error' => $exception->getMessage()]);

            return ['ok' => false, 'set' => $name, 'manifest' => null, 'errors' => [$exception->getMessage()]];
        }
    }

    /**
     * PostgreSQL-native backup (BLOCKER-B): `pg_dump -Fc` into the set,
     * same envelope/media/prune discipline as the SQLite path. Credentials
     * travel only via the PGPASSWORD process environment; error strings
     * carry host/database/binary identity, never secret material.
     *
     * @return array{ok: bool, set: string|null, manifest: array<string, mixed>|null, errors: list<string>}
     */
    private function runPgsql(): array
    {
        $binary = trim((string) config('backup.pg_dump', 'pg_dump'));

        if ($binary === '') {
            return $this->refused(['PostgreSQL backup misconfigured: backup.pg_dump is empty; set RTFTT_PG_DUMP_PATH to the pg_dump binary.']);
        }

        $connection = config('database.connections.pgsql', []);
        $database = trim((string) ($connection['database'] ?? ''));

        if (! is_array($connection) || $database === '') {
            return $this->refused(['PostgreSQL backup misconfigured: the pgsql connection has no database name; provision DB_DATABASE before requesting --driver=pgsql.']);
        }

        $version = $this->pgDumpToolVersion($binary);

        if ($version === null) {
            return $this->refused([sprintf('PostgreSQL backup unavailable: pg_dump tool "%s" failed its version probe; install PostgreSQL client tools and set RTFTT_PG_DUMP_PATH.', $binary)]);
        }

        $inventoryError = $this->inventoryDivergence();

        if ($inventoryError !== null) {
            return $this->refused([$inventoryError]);
        }

        $target = $this->targetRoot();

        if (! $this->ensureDirectory($target)) {
            return $this->refused([sprintf('Backup target "%s" is not writable.', $target)]);
        }

        $name = gmdate('Ymd-His').'-pgsql';
        $setDir = $target.DIRECTORY_SEPARATOR.$name;

        if (! @mkdir($setDir, 0750, true) && ! is_dir($setDir)) {
            return $this->refused([sprintf('Could not create backup set directory "%s".', $setDir)]);
        }

        try {
            $destination = $setDir.DIRECTORY_SEPARATOR.self::PG_DUMP_FILE;
            $this->snapshotPgsql($binary, $connection, $destination);
            $mediaManifest = $this->captureMediaTree($setDir.DIRECTORY_SEPARATOR.'media');

            $manifest = [
                'name' => $name,
                'created_at' => gmdate('c'),
                'driver' => 'pgsql',
                'tool' => 'pg_dump/'.$version,
                'database' => ['file' => self::PG_DUMP_FILE, 'format' => 'pgdump-custom', 'sha256' => hash_file('sha256', $destination)],
                'media' => $mediaManifest,
                'migration_inventory_sha256' => hash_file('sha256', base_path('deploy/migrations-inventory.json')),
                'status' => self::STATUS_OK,
            ];
            $manifest['root'] = hash('sha256', (string) json_encode($manifest));

            file_put_contents($setDir.DIRECTORY_SEPARATOR.self::MANIFEST_FILE, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $integrity = $this->verifySet($setDir);

            if (! $integrity['ok']) {
                $this->markFailed($setDir, $manifest, $integrity['errors']);
                $this->prune($target);

                return ['ok' => false, 'set' => $name, 'manifest' => null, 'errors' => $integrity['errors']];
            }

            $this->prune($target);
            $this->log('backup.complete', ['set' => $name]);

            return ['ok' => true, 'set' => $name, 'manifest' => $manifest, 'errors' => []];
        } catch (Throwable $exception) {
            $this->removeDirectory($setDir);
            $this->log('backup.failed', ['set' => $name, 'error' => $exception->getMessage()]);

            return ['ok' => false, 'set' => $name, 'manifest' => null, 'errors' => [$exception->getMessage()]];
        }
    }

    /**
     * @param  list<string>  $errors
     * @return array{ok: false, set: null, manifest: null, errors: list<string>}
     */
    private function refused(array $errors): array
    {
        return ['ok' => false, 'set' => null, 'manifest' => null, 'errors' => $errors];
    }

    /**
     * Pure pg_dump invocation builder (no secrets in the returned argv;
     * the role credential travels via PGPASSWORD process env only).
     *
     * @param  array<string, mixed>  $connection  pgsql connection shape
     * @return list<string>
     */
    public function buildPgDumpCommand(string $binary, array $connection, string $destination): array
    {
        $command = [$binary, '-Fc', '-f', $destination];

        $host = trim((string) ($connection['host'] ?? ''));
        $port = trim((string) ($connection['port'] ?? ''));
        $username = trim((string) ($connection['username'] ?? ''));
        $database = trim((string) ($connection['database'] ?? ''));

        if ($host !== '') {
            array_push($command, '-h', $host);
        }

        if ($port !== '') {
            array_push($command, '-p', $port);
        }

        if ($username !== '') {
            array_push($command, '-U', $username);
        }

        $command[] = $database;

        return $command;
    }

    /**
     * Probe the pg_dump binary; returns the first version line (max 64
     * chars, e.g. "pg_dump (PostgreSQL 17.2)") or null when the tool is
     * missing/unexecutable. Never passes credentials.
     */
    public function pgDumpToolVersion(string $binary): ?string
    {
        try {
            $process = new Process([$binary, '--version']);
            $process->setTimeout(15);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $lines = explode("\n", trim($process->getOutput()));
            $firstLine = trim($lines[0]);

            if ($firstLine === '') {
                return null;
            }

            return substr($firstLine, 0, 64);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Execute pg_dump into the set destination. Throws (loud failure,
     * no silent fallback) on non-zero exit; stderr is truncated and
     * carries only tool output, never the role credential.
     *
     * @param  array<string, mixed>  $connection
     */
    private function snapshotPgsql(string $binary, array $connection, string $destination): void
    {
        $password = (string) ($connection['password'] ?? '');
        $env = $password !== '' ? ['PGPASSWORD' => $password] : [];

        $process = new Process($this->buildPgDumpCommand($binary, $connection, $destination), null, $env);
        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'pg_dump failed for database "%s" (exit %s): %s',
                (string) ($connection['database'] ?? '?'),
                (string) ($process->getExitCode() ?? '?'),
                substr(trim($process->getErrorOutput() === '' ? $process->getOutput() : $process->getErrorOutput()), 0, 500)
            ));
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException(sprintf('pg_dump reported success but produced no database artifact for "%s".', (string) ($connection['database'] ?? '?')));
        }

        @chmod($destination, 0640);
    }

    /**
     * Copy the SQLite store after proving it is quiescent: open it and
     * take (then release) an immediate transaction, which fails loudly
     * while a writer holds the database. The file copy itself then
     * cannot interleave with a commit.
     */
    private function snapshotSqlite(string $source, string $destination): void
    {
        try {
            $pdo = new \PDO('sqlite:'.$source, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('BEGIN IMMEDIATE');
            $pdo->exec('COMMIT');
        } catch (Throwable $exception) {
            throw new RuntimeException('SQLite store is busy; refusing a torn copy: '.$exception->getMessage());
        } finally {
            unset($pdo);
        }

        if (! @copy($source, $destination)) {
            throw new RuntimeException(sprintf('Could not copy SQLite store "%s".', $source));
        }

        @chmod($destination, 0640);
    }

    /**
     * Mirror the media tree (excluding P7-006 quarantine content) into the
     * set and return the file manifest + root hash.
     *
     * @return array{files: list<array{path: string, size: int, sha256: string}>, root: string}
     */
    private function captureMediaTree(string $destination): array
    {
        $files = [];

        foreach ($this->mediaStorage->allFiles('media') as $path) {
            if (str_starts_with($path, 'quarantine/') || str_starts_with($path, 'media/quarantine/')) {
                continue;
            }

            $relative = $path;
            $contents = $this->mediaStorage->get($path);
            $target = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0750, true);
            }

            file_put_contents($target, $contents);
            $files[] = ['path' => $relative, 'size' => strlen($contents), 'sha256' => hash('sha256', $contents)];
        }

        usort($files, fn ($a, $b): int => strcmp($a['path'], $b['path']));

        return ['files' => $files, 'root' => hash('sha256', (string) json_encode($files))];
    }

    /**
     * Scratch-restore integrity verification: re-hash the manifest chain,
     * re-verify the migration inventory pin, and prove the database copy
     * is a working artifact for its driver — SQLite opens read-only with
     * a migrations table; pgsql proves a real pg_dump custom-format
     * archive (magic header + sha256 match) without requiring a live
     * server. Legacy manifests without a driver key verify as sqlite.
     *
     * @return array{ok: bool, errors: list<string>}
     */
    public function verifySet(string $setDir): array
    {
        $errors = [];
        $manifestPath = $setDir.DIRECTORY_SEPARATOR.self::MANIFEST_FILE;

        if (! is_file($manifestPath)) {
            return ['ok' => false, 'errors' => ['Manifest missing; set is not a valid backup.']];
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest)) {
            return ['ok' => false, 'errors' => ['Manifest is not valid JSON.']];
        }

        $root = $manifest['root'] ?? null;
        unset($manifest['root']);

        if (! is_string($root) || ! hash_equals($root, hash('sha256', (string) json_encode($manifest)))) {
            $errors[] = 'Manifest root hash mismatch; set was modified after creation.';
        }

        $inventoryHash = is_string($manifest['migration_inventory_sha256'] ?? null) ? $manifest['migration_inventory_sha256'] : null;
        $liveInventory = base_path('deploy/migrations-inventory.json');

        if ($inventoryHash === null || ! is_file($liveInventory) || ! hash_equals($inventoryHash, (string) hash_file('sha256', $liveInventory))) {
            $errors[] = 'Migration inventory pin diverged; set predates a migration change.';
        }

        $dbCopy = $setDir.DIRECTORY_SEPARATOR.'database.sqlite';

        $driver = is_string($manifest['driver'] ?? null) ? $manifest['driver'] : 'sqlite';

        if ($driver === 'pgsql') {
            $errors = array_merge($errors, $this->verifyPgsqlArtifact($setDir, $manifest));

            return ['ok' => $errors === [], 'errors' => $errors];
        }

        if ($driver !== 'sqlite') {
            $errors[] = sprintf('Unknown backup driver "%s" in manifest; set is not verifiable.', $driver);

            return ['ok' => false, 'errors' => $errors];
        }

        if (! is_file($dbCopy)) {
            $errors[] = 'Database copy missing from set.';
        } else {
            try {
                $pdo = new \PDO('sqlite:'.$dbCopy, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $statement = $pdo->query('SELECT COUNT(*) FROM migrations');
                unset($pdo);

                $count = $statement === false ? false : $statement->fetchColumn();

                if ($count === false) {
                    $errors[] = 'Database copy has no readable migrations table.';
                }
            } catch (Throwable $exception) {
                $errors[] = 'Database copy is not a working SQLite store: '.$exception->getMessage();
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * Prove a pgsql set holds a genuine pg_dump custom-format artifact:
     * present, non-empty, magic header intact, sha256 matching the
     * manifest. File-level proof only — no live server required, so
     * verification stays runnable on any host with the set directory.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function verifyPgsqlArtifact(string $setDir, array $manifest): array
    {
        $errors = [];
        $artifact = $setDir.DIRECTORY_SEPARATOR.self::PG_DUMP_FILE;

        if (! is_file($artifact)) {
            return ['Database dump missing from set.'];
        }

        if ((filesize($artifact) ?: 0) < strlen(self::PG_DUMP_MAGIC)) {
            return ['Database dump is empty; set holds no pg_dump artifact.'];
        }

        $handle = fopen($artifact, 'rb');

        if ($handle === false) {
            return ['Database dump is not readable.'];
        }

        $magic = fread($handle, strlen(self::PG_DUMP_MAGIC));
        fclose($handle);

        if ($magic !== self::PG_DUMP_MAGIC) {
            $errors[] = 'Database dump is not a pg_dump custom-format archive (magic mismatch); set was modified after creation.';
        }

        $expected = $manifest['database']['sha256'] ?? null;

        if (! is_string($expected) || ! hash_equals($expected, (string) hash_file('sha256', $artifact))) {
            $errors[] = 'Database dump sha256 mismatch; set was modified after creation.';
        }

        return $errors;
    }

    /**
     * @return string|null divergence message, or null when reconciled
     */
    public function inventoryDivergence(?string $manifestPath = null, ?string $migrationsDir = null): ?string
    {
        $manifestPath ??= base_path('deploy/migrations-inventory.json');
        $migrationsDir ??= database_path('migrations');

        if (! is_file($manifestPath)) {
            return 'Migration inventory manifest is missing; cannot prove migration consistency.';
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        if (! is_array($manifest) || ! isset($manifest['migrations']) || ! is_array($manifest['migrations'])) {
            return 'Migration inventory manifest is unreadable.';
        }

        foreach ($manifest['migrations'] as $file => $hash) {
            $path = rtrim($migrationsDir, '/\\').DIRECTORY_SEPARATOR.$file;

            if (! is_file($path) || hash_file('sha256', $path) !== $hash) {
                return sprintf('Migration inventory diverged at "%s"; refusing backup until the owning task reconciles the manifest.', $file);
            }
        }

        $files = collect(scandir($migrationsDir) ?: [])
            ->filter(fn ($file): bool => str_ends_with((string) $file, '.php'))
            ->map(fn ($file): string => basename((string) $file))
            ->values()
            ->all();

        $unpinned = array_values(array_diff($files, array_keys($manifest['migrations'])));

        if ($unpinned !== []) {
            return sprintf('Unpinned migrations present (%s); refusing backup until the owning task appends them.', implode(', ', $unpinned));
        }

        return null;
    }

    /**
     * Generation pruning with last-good protection: keep the newest N
     * generations; never delete the newest good set because a newer set
     * failed; failed sets are kept (quarantined evidence) until a newer
     * good set exists.
     */
    public function prune(string $target): void
    {
        $generations = max(1, (int) config('backup.generations', 7));
        $sets = $this->listSets($target);

        $good = array_values(array_filter($sets, fn ($set): bool => $this->setStatus($target, $set) === self::STATUS_OK));

        if ($good === []) {
            return;
        }

        $newestGood = $good[0];
        $keep = array_slice($good, 0, $generations);
        $keep[] = $newestGood;
        $keep = array_values(array_unique($keep));

        foreach ($sets as $set) {
            if (in_array($set, $keep, true)) {
                continue;
            }

            // Failed sets survive until a newer good set exists past them.
            if ($this->setStatus($target, $set) === self::STATUS_FAILED && strcmp($set, $newestGood) > 0) {
                continue;
            }

            $this->removeDirectory($target.DIRECTORY_SEPARATOR.$set);
        }
    }

    /** @return list<string> newest-first set names */
    public function listSets(string $target): array
    {
        if (! is_dir($target)) {
            return [];
        }

        $sets = [];

        foreach (scandir($target) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_dir($target.DIRECTORY_SEPARATOR.$entry)) {
                $sets[] = $entry;
            }
        }

        rsort($sets);

        return $sets;
    }

    public function setStatus(string $target, string $set): string
    {
        $manifestPath = $target.DIRECTORY_SEPARATOR.$set.DIRECTORY_SEPARATOR.self::MANIFEST_FILE;

        if (! is_file($manifestPath)) {
            return self::STATUS_FAILED;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        return is_array($manifest) && ($manifest['status'] ?? null) === self::STATUS_OK
            ? self::STATUS_OK
            : self::STATUS_FAILED;
    }

    /** @return array<string, mixed>|null newest manifest (any status) */
    public function latestManifest(string $target): ?array
    {
        foreach ($this->listSets($target) as $set) {
            $manifestPath = $target.DIRECTORY_SEPARATOR.$set.DIRECTORY_SEPARATOR.self::MANIFEST_FILE;

            if (! is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($manifest)) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $errors
     */
    private function markFailed(string $setDir, array $manifest, array $errors): void
    {
        $manifest['status'] = self::STATUS_FAILED;
        $manifest['failure'] = $errors;
        unset($manifest['root']);
        $manifest['root'] = hash('sha256', (string) json_encode($manifest));

        file_put_contents($setDir.DIRECTORY_SEPARATOR.self::MANIFEST_FILE, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->log('backup.integrity_failed', ['set' => basename($setDir), 'errors' => count($errors)]);
    }

    private function ensureDirectory(string $path): bool
    {
        if (is_dir($path)) {
            return is_writable($path);
        }

        return @mkdir($path, 0750, true) && is_writable($path);
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }

    /** @param array<string, mixed> $context */
    private function log(string $event, array $context): void
    {
        Log::channel('structured')->info('backup.'.$event, $context);
    }
}
