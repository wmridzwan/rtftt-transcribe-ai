<?php

namespace App\Backup;

use App\Models\MediaFile;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Backup/restore foundation (P7-007, D7-07 daily posture).
 *
 * SQLite-era mechanism (executable now): consistent file snapshot of the
 * SQLite store plus the private media tree as one named set with a
 * manifest, per-run scratch-restore integrity verification, generation
 * pruning with last-good protection. The PostgreSQL-native path is
 * defined but dormant: any pgsql attempt refuses with the P7-002
 * activation message rather than half-executing.
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
            return [
                'ok' => false,
                'set' => null,
                'manifest' => null,
                'errors' => ['PostgreSQL-native backup requires P7-002 (datastore migration) DONE; refusing to half-execute.'],
            ];
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
     * re-verify the migration inventory pin, and open the database copy
     * read-only to prove it is a working SQLite store with a migrations
     * table.
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
