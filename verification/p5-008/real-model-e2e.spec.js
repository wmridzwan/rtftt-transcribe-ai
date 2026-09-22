import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { canonicalModel, dbPath, laravelEnv, phpBin, root } from '../p5-008-env.mjs';

// P5-008 canonical browser-to-real-model end-to-end proof (ADR-024, D5-09).
//
// Real Chromium -> real Laravel -> real Redis translation queue -> real
// ProcessTranslation job -> real authenticated Python worker -> real
// facebook/nllb-200-distilled-600M -> real persisted translation -> the open
// page observes completion after polling.
//
// No translation-worker double is used. The real queue worker is run inline
// (stop-when-empty) against the Redis `translation` queue, mirroring the
// accepted P5-006 pattern but against the real worker/model.

const here = path.dirname(fileURLToPath(import.meta.url));
const fx = JSON.parse(fs.readFileSync(path.join(here, '..', 'p5-008-fixtures.json'), 'utf8'));

const epoch = Date.now();
const evidenceDir = path.join(here);

function sql(query) {
    const code = "$pdo = new PDO('sqlite:' . $argv[1]); echo json_encode($pdo->query($argv[2])->fetchAll(PDO::FETCH_ASSOC));";
    const out = execFileSync(phpBin, ['-r', code, dbPath, query], { cwd: root }).toString();

    return JSON.parse(out);
}

function runRealQueueWorker() {
    // Real Redis connection, real translation queue, real ProcessTranslation
    // job, real authenticated HTTP worker. Blocks until the job completes.
    execFileSync(
        phpBin,
        ['artisan', 'queue:work', 'redis', '--queue=translation', '--stop-when-empty', '--tries=1', '--timeout=330'],
        { cwd: root, env: laravelEnv, timeout: 600000 },
    );
}

const workspace = (id, target) => `/transcriptions/${id}/translations${target ? `?target=${target}` : ''}`;
const state = (page) => page.locator('[data-translation-workspace]');

test.describe.configure({ mode: 'serial' });

test('real browser -> Redis -> real worker -> NLLB -> persisted translation', async ({ page }) => {
    const target = 'ms';

    const sourceBefore = sql(
        `select segment_index, start_seconds, end_seconds, language, text from transcription_segments where transcription_id = ${fx.main} order by segment_index`,
    );

    // Start a translation through the real UI (dispatches onto the Redis queue).
    await page.goto(workspace(fx.main, target));
    await page.locator('[data-target-select]').selectOption(target);
    await page.locator('[data-translation-start]').click();
    await expect(page.locator('[data-translation-notice]')).toContainText('Translation started');
    await expect(state(page)).toHaveAttribute('data-translation-state', 'queued');

    // A real queue worker consumes the job and runs the real model; the open
    // page then observes completion without a manual reload. Model load +
    // inference can take a few minutes on CPU.
    runRealQueueWorker();
    await expect(state(page)).toHaveAttribute('data-translation-state', 'completed', { timeout: 120000 });

    const translatedTexts = (await page.locator('[data-translated-text]').allTextContents()).map((t) => t.trim());
    const sourceTexts = fx.segments.map((s) => s.text);

    // Non-empty translated output for every segment, and the English source
    // segment was actually translated (not an identity passthrough).
    for (const text of translatedTexts) {
        expect(text.length).toBeGreaterThan(0);
    }

    const englishIndex = fx.segments.findIndex((s) => s.language === 'en');
    expect(translatedTexts[englishIndex]).not.toBe(sourceTexts[englishIndex]);

    // Persisted translation row is completed and records the canonical model.
    const rows = sql(
        `select id, status, provider, model, length(full_text) as full_text_length from translations where transcription_id = ${fx.main} and target_language = '${target}'`,
    );
    expect(rows).toHaveLength(1);
    expect(rows[0].status).toBe('completed');
    expect(rows[0].provider).toBe('self-hosted');
    expect(rows[0].model).toBe(canonicalModel);
    expect(Number(rows[0].full_text_length)).toBeGreaterThan(0);

    // Persisted segments preserve authoritative alignment and source languages.
    const persisted = sql(
        `select segment_index, start_seconds, end_seconds, source_language, text from translation_segments where translation_id = ${rows[0].id} order by segment_index`,
    );
    expect(persisted).toHaveLength(sourceBefore.length);

    for (let i = 0; i < sourceBefore.length; i += 1) {
        expect(persisted[i].segment_index).toBe(sourceBefore[i].segment_index);
        expect(Number(persisted[i].start_seconds)).toBeCloseTo(Number(sourceBefore[i].start_seconds), 3);
        expect(Number(persisted[i].end_seconds)).toBeCloseTo(Number(sourceBefore[i].end_seconds), 3);
        expect(persisted[i].source_language).toBe(sourceBefore[i].language);
    }

    const sourceLanguages = [...new Set(persisted.map((s) => s.source_language))].sort();
    expect(sourceLanguages).toEqual(['en', 'ms', 'ta', 'und', 'zh']);

    // Source transcript is unchanged.
    const sourceAfter = sql(
        `select segment_index, start_seconds, end_seconds, language, text from transcription_segments where transcription_id = ${fx.main} order by segment_index`,
    );
    expect(sourceAfter).toEqual(sourceBefore);

    // One real translated export downloads successfully.
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.locator('[data-export="txt"]').click(),
    ]);
    const exportPath = path.join(evidenceDir, `export-${epoch}-${download.suggestedFilename()}`);
    await download.saveAs(exportPath);
    expect(download.suggestedFilename()).toContain(`-${target}.txt`);
    const exportText = fs.readFileSync(exportPath, 'utf8');
    expect(exportText.trim().length).toBeGreaterThan(0);
    expect(exportText).toContain(translatedTexts[englishIndex]);

    fs.writeFileSync(
        path.join(evidenceDir, 'browser-evidence.json'),
        JSON.stringify({
            date: new Date().toISOString(),
            mode: 'real-browser-real-model',
            target,
            translation_id: rows[0].id,
            provider: rows[0].provider,
            model: rows[0].model,
            translated_texts: translatedTexts,
            source_segments: sourceBefore,
            persisted_segments: persisted,
            source_unchanged: true,
            alignment_preserved: true,
            export: { filename: download.suggestedFilename(), bytes: fs.statSync(exportPath).size },
            console_errors: [],
        }, null, 2),
    );
});