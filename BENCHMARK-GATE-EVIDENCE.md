# Phase 3 Benchmark Gate Evidence — turbo vs large-v3

Date: 2026-09-17 (correction cycle 1)
Status: BLOCKED — Python installation failed in current environment

## Environment

- faster-whisper version: N/A (not installed)
- PyTorch version: N/A (not installed)
- CUDA available: N/A
- GPU: N/A
- CPU: Windows (PHP 8.4 environment)
- RAM: N/A
- FFmpeg: 9.0.1 (available)
- Python: NOT INSTALLED (winget install timed out, Windows Store alias only)

## Blocker

Python 3.11 installation via winget repeatedly timed out in the current
Windows environment. The Windows Store python.exe alias is not a real
Python installation. Without Python, faster-whisper cannot be installed
and the benchmark cannot be executed.

## Benchmark Script

`scripts/benchmark/benchmark_gate.py` — ready to execute once Python is available.

### Usage

```bash
pip install faster-whisper
python scripts/benchmark/benchmark_gate.py --media-dir ./benchmark-media --output benchmark-results.json
```

## Required Test Media

Representative workload must include:
- Bahasa Melayu sample
- English sample
- Chinese sample
- Tamil sample
- Mixed/code-switching sample

## Evidence Structure (per sample/model)

- sample ID/description
- sample duration
- hardware (CPU/GPU, RAM/VRAM)
- model, device, compute_type
- processing duration
- real-time factor
- qualitative transcript observation
- BM/English/Chinese/Tamil/code-switching observations
- operational/resource notes

## Decision Rule

turbo remains preferred unless benchmark evidence shows
materially unacceptable degradation for RTFTT.

## Privacy

Representative user media must not be committed unless
explicitly approved.

## Next Step (HPO Decision Required)

1. Install Python 3.10+ (manual installation or alternative method)
2. `pip install faster-whisper`
3. Prepare representative media in `benchmark-media/`
4. Run benchmark script
5. Record evidence in this file
6. Apply decision rule

The benchmark gate remains OPEN (DECISION-P3-BENCHMARK-GATE-001).
HPO chose Option 1 (run real benchmark), but environment setup is
blocked by Python installation failure.
