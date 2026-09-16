# Phase 3 Benchmark Gate Evidence — turbo vs large-v3

Date: TBD (pending Python + faster-whisper environment setup)
Status: DEFERRED — Python not installed on current system

## Environment

- faster-whisper version: TBD
- PyTorch version: TBD
- CUDA available: TBD
- GPU: TBD
- CPU: TBD
- RAM: TBD
- FFmpeg: 9.0.1 (available)

## Benchmark Script

`scripts/benchmark/benchmark_gate.py`

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

## Next Step

1. Install Python 3.10+
2. `pip install faster-whisper`
3. Prepare representative media in `benchmark-media/`
4. Run benchmark script
5. Record evidence in this file
6. Apply decision rule
