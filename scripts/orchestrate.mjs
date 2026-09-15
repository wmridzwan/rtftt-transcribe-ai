#!/usr/bin/env node
// Local orchestrator: watches tasks/*.md and drives the OpenCode <-> Claude Code
// handoff defined in .ai/guidelines/orchestration-policy.md's State-to-Action
// Contract. This script makes no product, scope, or architecture decisions —
// it only detects a task-file status transition and invokes the CLI that the
// contract already says should act next. It never closes VERIFIED tasks as
// DONE and never starts a new phase; those remain Human Product Owner actions.
//
// Usage:
//   node scripts/orchestrate.mjs             # watch, dry-run (logs only, spawns nothing)
//   node scripts/orchestrate.mjs --once       # scan every task file once, dry-run, exit
//   node scripts/orchestrate.mjs --live       # watch and actually spawn claude/opencode
//
// --live requires the ORCHESTRATE_UNATTENDED=1 environment variable as a
// second, explicit opt-in, because it runs claude/opencode with permission
// checks relaxed (acceptEdits / --auto) so they can proceed without a human
// approving each tool call. Without both, --live refuses to spawn anything.

import { readFile, writeFile, mkdir, readdir } from "node:fs/promises";
import { watch } from "node:fs";
import path from "node:path";
import { spawn } from "node:child_process";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "..");
const TASKS_DIR = path.join(ROOT, "tasks");
const REVIEWS_DIR = path.join(ROOT, "reviews");
const STATE_DIR = path.join(ROOT, "storage", "orchestration");
const STATE_FILE = path.join(STATE_DIR, "state.json");
const LOG_FILE = path.join(STATE_DIR, "orchestrate.log");
const CONFIG_FILE = path.join(__dirname, "orchestrate.config.json");

const args = process.argv.slice(2);
const LIVE = args.includes("--live");
const ONCE = args.includes("--once");
const UNATTENDED_OK = process.env.ORCHESTRATE_UNATTENDED === "1";

/** @type {{status:string, changeCycles:number}} */
const DEFAULT_TASK_STATE = { status: "", changeCycles: 0 };

async function loadConfig() {
    const raw = await readFile(CONFIG_FILE, "utf8");
    return JSON.parse(raw);
}

async function loadState() {
    try {
        const raw = await readFile(STATE_FILE, "utf8");
        return JSON.parse(raw);
    } catch {
        return {};
    }
}

async function saveState(state) {
    await mkdir(STATE_DIR, { recursive: true });
    await writeFile(STATE_FILE, JSON.stringify(state, null, 2));
}

async function log(line) {
    const stamped = `[${new Date().toISOString()}] ${line}`;
    console.log(stamped);
    await mkdir(STATE_DIR, { recursive: true });
    await writeFile(LOG_FILE, stamped + "\n", { flag: "a" });
}

function parseStatus(content) {
    const match = content.match(/##\s*Status\s*\r?\n+\s*([A-Z_]+)/);
    return match ? match[1] : null;
}

function parseOwner(content) {
    const match = content.match(/Implementation Owner:\s*([^\n,]+)/i);
    return match ? match[1].trim() : null;
}

function latestReviewFor(taskId, reviewFiles) {
    const candidates = reviewFiles.filter((f) => f.includes(taskId));
    if (candidates.length === 0) {
        return null;
    }
    candidates.sort();
    return candidates[candidates.length - 1];
}

let running = Promise.resolve();

/** Serializes agent invocations so only one claude/opencode process runs at a time. */
function enqueue(fn) {
    running = running.then(fn, fn);
    return running;
}

function spawnAgent(command, argv, promptText) {
    return new Promise((resolve, reject) => {
        const child = spawn(command, argv, {
            cwd: ROOT,
            stdio: ["pipe", "inherit", "inherit"],
            shell: process.platform === "win32",
        });
        child.stdin.write(promptText);
        child.stdin.end();
        child.on("error", reject);
        child.on("exit", (code) => resolve(code));
    });
}

async function triggerReview(config, taskFile, taskId) {
    const prompt = [
        `Task ${taskId} has moved to REVIEW.`,
        `Act as the independent reviewer per .ai/guidelines/orchestration-policy.md and AGENTS.md.`,
        `Read tasks/${taskFile} and the implementation it describes, review it (correctness, acceptance criteria, architecture compliance, regressions, authorization/ownership boundaries, security, edge cases, test adequacy), write a durable review artifact under reviews/, and update the task's Status section to VERIFIED or CHANGES_REQUESTED.`,
        `Do not modify implementation code. Do not close the task as DONE — that requires the Human Product Owner.`,
    ].join(" ");

    await log(`REVIEW -> claude for ${taskId}`);
    if (!LIVE) {
        await log(`[DRY RUN] would run: ${config.claude.command} -p --permission-mode ${config.claude.permissionMode} (stdin prompt)`);
        return;
    }
    const argv = ["-p", "--permission-mode", config.claude.permissionMode, ...config.claude.extraArgs];
    const code = await spawnAgent(config.claude.command, argv, prompt);
    await log(`claude exited ${code} for ${taskId} review`);
}

async function triggerFix(config, taskFile, taskId, cycle, reviewFile) {
    const reviewNote = reviewFile ? `The latest review is reviews/${reviewFile}.` : `No review file was found by filename match; locate the relevant review under reviews/.`;
    const prompt = [
        `Task ${taskId} received CHANGES_REQUESTED (cycle ${cycle} of ${config.maxChangeRequestedCycles}).`,
        `Act as the implementation owner (OpenCode) per .ai/guidelines/orchestration-policy.md and AGENTS.md.`,
        `Read tasks/${taskFile}. ${reviewNote}`,
        `Fix the BLOCKER/HIGH findings, update tests, run relevant verification, update the task's implementation notes, and move Status back to REVIEW when done.`,
        `Do not expand scope beyond this task and do not mark your own work VERIFIED.`,
    ].join(" ");

    await log(`CHANGES_REQUESTED -> opencode for ${taskId} (cycle ${cycle})`);
    if (!LIVE) {
        await log(`[DRY RUN] would run: ${config.opencode.command} run (stdin prompt)`);
        return;
    }
    const argv = ["run", ...config.opencode.extraArgs];
    const code = await spawnAgent(config.opencode.command, argv, prompt);
    await log(`opencode exited ${code} for ${taskId} fix`);
}

async function processTaskFile(config, state, fileName) {
    if (!fileName.endsWith(".md")) {
        return;
    }
    const fullPath = path.join(TASKS_DIR, fileName);
    let content;
    try {
        content = await readFile(fullPath, "utf8");
    } catch {
        return; // file removed/renamed mid-scan
    }

    const status = parseStatus(content);
    if (!status) {
        return;
    }
    const taskId = fileName.replace(/\.md$/, "");
    const prev = state[taskId] ?? { ...DEFAULT_TASK_STATE };

    if (status === prev.status) {
        return; // no transition
    }

    await log(`${taskId}: ${prev.status || "(unknown)"} -> ${status}`);

    if (status === "REVIEW") {
        await enqueue(() => triggerReview(config, fileName, taskId));
        prev.changeCycles = prev.changeCycles ?? 0;
    } else if (status === "CHANGES_REQUESTED") {
        const cycle = (prev.changeCycles ?? 0) + 1;
        prev.changeCycles = cycle;
        if (cycle > config.maxChangeRequestedCycles) {
            await log(
                `${taskId}: CHANGES_REQUESTED cycle ${cycle} exceeds max ${config.maxChangeRequestedCycles}. ` +
                `Per the three-cycle escalation rule, this must be escalated as BLOCKED for the Human Product Owner ` +
                `to decide (reassign, defer, accept, or close) — no automatic fix attempt was made.`
            );
        } else {
            let reviewFiles = [];
            try {
                reviewFiles = await readdir(REVIEWS_DIR);
            } catch {
                // reviews dir missing; proceed without a matched file
            }
            const reviewFile = latestReviewFor(taskId, reviewFiles);
            await enqueue(() => triggerFix(config, fileName, taskId, cycle, reviewFile));
        }
    } else if (status === "VERIFIED") {
        await log(
            `${taskId}: VERIFIED. Awaiting Human Product Owner closure to DONE — this is not automated ` +
            `(see .ai/guidelines/orchestration-policy.md: "VERIFIED does not mean DONE").`
        );
    } else if (status === "BLOCKED") {
        await log(`${taskId}: BLOCKED. Awaiting Human Product Owner decision — no automatic action taken.`);
    } else if (status === "DONE") {
        prev.changeCycles = 0;
        await log(`${taskId}: DONE.`);
    }
    // READY / IN_PROGRESS / BACKLOG: assignment is a Human Product Owner action
    // (or an explicitly pre-authorized OpenCode self-assign) per the contract
    // table. autoAssignReadyTasks is off by default; this script does not
    // start new task implementations on its own unless that config is enabled
    // and a --live run explicitly wires it up.

    prev.status = status;
    state[taskId] = prev;
    await saveState(state);
}

async function scanAll(config, state) {
    const files = await readdir(TASKS_DIR);
    for (const file of files) {
        await processTaskFile(config, state, file);
    }
}

async function main() {
    const config = await loadConfig();
    const state = await loadState();

    if (LIVE && !UNATTENDED_OK) {
        console.error(
            "--live was passed but ORCHESTRATE_UNATTENDED=1 is not set. Refusing to spawn claude/opencode " +
            "unattended. Set the environment variable explicitly if you want this script to run agents with " +
            "relaxed permission checks (acceptEdits / --auto) without a human approving each step."
        );
        process.exit(1);
    }

    await log(`orchestrate.mjs starting (live=${LIVE}, once=${ONCE})`);
    await scanAll(config, state);

    if (ONCE) {
        await log("orchestrate.mjs: --once scan complete, exiting.");
        return;
    }

    await log(`watching ${TASKS_DIR} for status changes...`);
    const debounce = new Map();
    watch(TASKS_DIR, { recursive: true }, (_event, fileName) => {
        if (!fileName) {
            return;
        }
        clearTimeout(debounce.get(fileName));
        debounce.set(
            fileName,
            setTimeout(() => {
                processTaskFile(config, state, fileName).catch((err) =>
                    log(`error processing ${fileName}: ${err.stack || err}`)
                );
            }, 400)
        );
    });
}

main().catch((err) => {
    console.error(err);
    process.exit(1);
});
