// Shared environment for the P5-008 canonical real-gate harness (ADR-024).
//
// This harness drives the REAL self-hosted translation path:
//   real browser -> real Laravel -> real Redis translation queue ->
//   real ProcessTranslation job -> real authenticated Python worker ->
//   real facebook/nllb-200-distilled-600M -> persisted translation.
//
// It deliberately does NOT use the P5-006 deterministic worker double. The
// P5-006 double-based suite remains the broad browser regression evidence.
//
// Services that must be running externally (see the runbook):
//   - Redis on RTFTT_REDIS_HOST/PORT (default 127.0.0.1:6379)
//   - the authenticated Python worker on workerPort, started with the same
//     RTFTT_TRANSCRIPTION_WORKER_TOKEN and HF_HOME (clean cache).
//
// The orchestrator (p5-008-real-gate.mjs) starts/stops the Python worker and
// the queue worker; this module only declares the shared configuration.
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));

export const root = path.resolve(here, '..');
export const phpBin = process.env.PHP_BIN ?? 'C:/Users/Admin/.config/herd/bin/php84/php.exe';
export const dbPath = path.join(root, 'database', 'p5-008-browser.sqlite');
export const appPort = Number(process.env.P5_008_APP_PORT ?? 8125);

// A dedicated, non-secret local verification token (the worker and Laravel must
// agree). Never reuse a production token here.
export const workerToken = process.env.P5_008_WORKER_TOKEN ?? 'p5-008-verification-token';
export const workerPort = Number(process.env.P5_008_WORKER_PORT ?? 8100);

// Canonical runtime/model (ADR-024). Weights are not committed; the clean cache
// is provisioned per the runbook and located by HF_HOME.
export const canonicalModel = 'facebook/nllb-200-distilled-600M';
export const modelHome = process.env.RTFTT_HF_HOME ?? 'C:/rtftt-hf-cache';

export const laravelEnv = {
    ...process.env,
    APP_URL: `http://127.0.0.1:${appPort}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbPath,
    QUEUE_CONNECTION: 'redis',
    RTFTT_TRANSLATION_QUEUE: 'translation',
    RTFTT_TRANSLATION_QUEUE_CONNECTION: 'redis',
    RTFTT_TRANSLATION_WORKER_URL: `http://127.0.0.1:${workerPort}`,
    RTFTT_TRANSLATION_WORKER_TOKEN: workerToken,
    RTFTT_TRANSCRIPTION_WORKER_URL: `http://127.0.0.1:${workerPort}`,
    RTFTT_TRANSCRIPTION_WORKER_TOKEN: workerToken,
    RTFTT_TRANSLATION_MODEL: canonicalModel,
    REDIS_QUEUE_RETRY_AFTER: '420',
};

// Environment for the Python worker process (canonical model resolved by name
// from the clean cache).
export const workerEnv = {
    ...process.env,
    HF_HOME: modelHome,
    TRANSFORMERS_OFFLINE: '1',
    HF_HUB_OFFLINE: '1',
    RTFTT_TRANSCRIPTION_WORKER_TOKEN: workerToken,
    RTFTT_TRANSLATION_MODEL: canonicalModel,
};