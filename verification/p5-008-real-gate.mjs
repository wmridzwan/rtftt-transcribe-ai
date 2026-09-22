// P5-008 canonical real-gate orchestrator (ADR-024, DECISION-P5-008-CORRECTIVE-001).
//
// One reproducible entry point for the Phase 5 final integration gate:
//
//   1. start the real authenticated Python worker (canonical NLLB, clean cache)
//   2. run the artisan preflight (runtime versions, Redis, auth, queue/timeout)
//   3. run the real browser-to-real-model end-to-end proof (Playwright)
//   4. run the real Redis queued path for ms/en/zh/ta + code-switch + exports
//   5. consolidate tracked evidence under verification/p5-008/
//
// Requirements (see README section "P5-008 real gate"):
//   - Redis reachable (127.0.0.1:6379 by default)
//   - PHP at PHP_BIN (default Herd php84)
//   - worker/.venv with worker/requirements.txt installed (ADR-024 pins)
//   - the canonical model provisioned into a clean HF cache on C: (HF_HOME)
//   - Playwright browsers installed (npm i -D @playwright/test && npx playwright install chromium)
//
// Nothing here is production code, and no secrets are embedded.
import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import {
    appPort,
    canonicalModel,
    laravelEnv,
    modelHome,
    phpBin,
    root,
    workerEnv,
    workerPort,
    workerToken,
} from './p5-008-env.mjs';

const evidenceDir = path.join(root, 'verification', 'p5-008');
fs.mkdirSync(evidenceDir, { recursive: true });

const gateDbPath = path.join(root, 'database', 'p5-008-verification.sqlite');
const statePath = path.join(evidenceDir, 'artisan-state.json');
const gateEnv = { ...laravelEnv, DB_DATABASE: gateDbPath, RTFTT_P5008_RUN: '1' };

const targets = ['ms', 'en', 'zh', 'ta'];

function log(message) {
    process.stdout.write(`[p5-008] ${message}\n`);
}

function artisan(args, env, timeout = 900000) {
    const result = spawnSync(phpBin, ['artisan', ...args], {
        cwd: root,
        env,
        encoding: 'utf8',
        timeout,
        maxBuffer: 128 * 1024 * 1024,
    });

    if (result.status !== 0) {
        throw new Error(`artisan ${args.join(' ')} failed (${result.status}):\n${result.stdout}\n${result.stderr}`);
    }

    return result;
}

async function waitFor(url, timeoutMs = 60000) {
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        try {
            const response = await fetch(url);
            if (response.ok) {
                return true;
            }
        } catch {
            // not up yet
        }
        await new Promise((resolve) => setTimeout(resolve, 1500));
    }

    throw new Error(`Timed out waiting for ${url}`);
}

async function workerAlreadyUp() {
    try {
        const response = await fetch(`http://127.0.0.1:${workerPort}/health`);
        return response.ok;
    } catch {
        return false;
    }
}

const python = process.env.RTFTT_PYTHON
    ?? path.join(root, 'worker', '.venv', 'Scripts', 'python.exe');

async function main() {
    if (! fs.existsSync(python)) {
        throw new Error(`Python worker interpreter not found: ${python}`);
    }

    log(`canonical model ${canonicalModel}; HF_HOME=${modelHome}`);
    log(`worker http://127.0.0.1:${workerPort}; Laravel http://127.0.0.1:${appPort}`);

    let worker = null;

    if (await workerAlreadyUp()) {
        log('worker already running; reusing it');
    } else {
        log('starting Python worker');
        worker = spawn(python, ['-m', 'uvicorn', 'worker.main:app', '--host', '127.0.0.1', '--port', String(workerPort)], {
            cwd: root,
            env: workerEnv,
            stdio: ['ignore', 'pipe', 'pipe'],
        });
        worker.stdout.on('data', () => {});
        worker.stderr.on('data', () => {});
    }

    try {
        await waitFor(`http://127.0.0.1:${workerPort}/health`);
        log('worker healthy');

        // ---- 1. preflight -------------------------------------------------
        log('preflight (runtime, redis, auth, queue/timeout invariant)');
        artisan(['test:p5-008-integration', '--mode=preflight', `--out=${path.join(evidenceDir, 'preflight.json')}`], gateEnv);

        // ---- 2. real browser -> real model ---------------------------------
        log('browser -> real Redis queue -> real worker -> real NLLB');
        const browser = spawnSync('npx', ['playwright', 'test', '-c', 'verification/playwright.p5-008.config.js'], {
            cwd: root,
            env: { ...process.env },
            stdio: 'inherit',
            timeout: 1200000,
            shell: true,
        });

        if (browser.status !== 0) {
            throw new Error(`browser-to-real-model proof failed (${browser.status})`);
        }

        // ---- 3. real Redis queued path (artisan) --------------------------
        log('fresh gate database + migrate');
        artisan(['migrate:fresh', '--force'], gateEnv);
        artisan(['queue:clear', 'redis', '--queue=translation'], gateEnv);

        log('seed code-switched source transcript');
        artisan(['test:p5-008-integration', '--mode=seed', `--state=${statePath}`], gateEnv);

        for (const target of targets) {
            log(`dispatch ${target}`);
            artisan(['test:p5-008-integration', '--mode=dispatch', `--state=${statePath}`, `--target=${target}`], gateEnv);
        }

        log('capture Redis queue payload (small identifiers only)');
        artisan(['test:p5-008-integration', '--mode=redis-payload', `--state=${statePath}`, `--out=${path.join(evidenceDir, 'redis-payload.json')}`], gateEnv);

        log('consume the real Redis translation queue with a real queue worker');
        artisan(
            ['queue:work', 'redis', '--queue=translation', '--stop-when-empty', '--tries=1', '--timeout=330'],
            gateEnv,
            1800000,
        );

        log('assert persisted results / alignment / source immutability / ownership');
        artisan(['test:p5-008-integration', '--mode=assert', `--state=${statePath}`, `--out=${path.join(evidenceDir, 'assert.json')}`], gateEnv);

        log('verify all four translated exports');
        artisan(['test:p5-008-integration', '--mode=export', `--state=${statePath}`, `--out=${path.join(evidenceDir, 'exports.json')}`], gateEnv);

        const summary = {
            date: new Date().toISOString(),
            mode: 'canonical-real-gate',
            canonical_model: canonicalModel,
            canonical_pins: JSON.parse(fs.readFileSync(path.join(evidenceDir, 'preflight.json'), 'utf8')).canonical_pins,
            targets,
            evidence: {
                preflight: 'preflight.json',
                browser: 'browser-evidence.json',
                redis_payload: 'redis-payload.json',
                assert: 'assert.json',
                exports: 'exports.json',
            },
        };

        fs.writeFileSync(path.join(evidenceDir, 'gate-summary.json'), JSON.stringify(summary, null, 2));
        log('gate complete; evidence in verification/p5-008/');
    } finally {
        if (worker !== null) {
            worker.kill();
        }
    }
}

main().catch((error) => {
    process.stderr.write(`[p5-008] FAILED: ${error.message}\n`);
    process.exit(1);
});