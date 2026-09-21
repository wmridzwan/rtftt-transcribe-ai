// Shared environment for the P5-006 browser verification harness (ADR-021).
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

export const root = path.resolve(here, '..');
export const phpBin = process.env.PHP_BIN ?? 'C:/Users/Admin/.config/herd/bin/php84/php.exe';
export const dbPath = path.join(root, 'database', 'p5-006-verification.sqlite');
export const appPort = 8123;
export const workerPort = 8124;
export const workerToken = 'verification-token';

// The Laravel server, seeder, and queue worker all share this environment: a
// dedicated SQLite DB, the database queue on the `translation` queue, and the
// deterministic translation-worker test double.
export const laravelEnv = {
    ...process.env,
    APP_URL: `http://127.0.0.1:${appPort}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbPath,
    QUEUE_CONNECTION: 'database',
    RTFTT_TRANSLATION_QUEUE: 'translation',
    RTFTT_TRANSLATION_QUEUE_CONNECTION: 'database',
    RTFTT_TRANSLATION_WORKER_URL: `http://127.0.0.1:${workerPort}`,
    RTFTT_TRANSLATION_WORKER_TOKEN: workerToken,
    RTFTT_TRANSLATION_MODEL: 'p5-006-test-double',
    PHP_CLI_SERVER_WORKERS: '4',
};
