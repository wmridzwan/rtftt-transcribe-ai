# Spike proposals — PROPOSAL ONLY, NOT EXECUTED, NOT AUTHORIZED

Research date: 2026-09-27. Each needs separate HPO authorization with objective/hypothesis, fixture, ceiling, egress disclosure, cleanup.

- SPIKE-01 desktop whisper benchmark: HYP local large-v3/turbo feasible on desktop CPU; FIX 5/30/60min ms/en/zh/ta/code-switch; MEAS RTF, peak RAM, thermal, normalization compat; PASS/FAIL from measurements; EGRESS none (local only); CLEANUP remove models/fixtures. Effort M.
- SPIKE-02 iPhone native benchmark: HYP small/quantized feasible, large-v3 fails budget; FIX same corpus short clips; MEAS RTF/RAM/battery/thermal/screen-off survival/interrupt-resume; EGRESS none. Effort L.
- SPIKE-03 browser WASM/WebGPU: HYP tiny/base feasible, large-v3 infeasible in-tab; FIX short clips; MEAS load bytes, RTF, RAM, suspend survival; EGRESS none. Effort M.
- SPIKE-04 external STT normalization: HYP shortlisted APIs mappable into 12-case taxonomy + segment shape; FIX synthetic payloads only (no customer media); MEAS timestamp MAE, language accuracy, count/index parity, $/hr; EGRESS declared per candidate, none at proposal stage. Effort S/M.
- SPIKE-05 chunk recomposition: HYP overlap+offset+dedup yields canonical transcript; FIX synthetic chunks with known ground truth; MEAS boundary WER, duplication rate, language-transition accuracy, per-chunk retry idempotency; EGRESS none. Effort M.

No spike executed under current authorization (FACT).
