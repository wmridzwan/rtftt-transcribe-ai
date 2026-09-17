# Phase 3 Benchmark Gate Evidence — turbo vs large-v3

Date: 2026-09-17 (correction cycle 2 — Python retry)
Status: BLOCKED — environment now verified working; blocked only on missing
representative benchmark media

## Environment (independently verified this cycle)

- OS: Windows 11 Pro 10.0.26200
- Python: 3.13.14, executable
  `C:\Users\Admin\AppData\Local\Microsoft\WindowsApps\PythonSoftwareFoundation.Python.3.13_qbz5n2kfra8p0\python.exe`
  (confirmed a real interpreter, not the Store-alias stub: resolves to an
  actual install directory, executes code, and reports a real version)
- pip: 26.2.1 (in `worker/.venv`)
- Virtual environment: `worker/.venv` (created via `python -m venv`)
- faster-whisper: 1.2.1
- ctranslate2: 4.8.2 (CPU-supported compute types on this machine:
  `int8_float32`, `int16`, `int8`, `float32` — note `float16` is NOT
  supported on CPU; `worker/transcription.py::get_model()` already
  downgrades `float16`→`int8` on CPU, verified by test and by the real
  model-load smoke test below)
- pytest: 9.1.1 / pytest-asyncio: 1.4.0
- FFmpeg: 9.0.1-full_build-www.gyan.dev
- FFprobe: 9.0.1-full_build-www.gyan.dev
- CPU: Intel(R) Core(TM) i5-10210U CPU @ 1.60GHz, 4 cores / 8 logical
  processors
- GPU: Intel(R) UHD Graphics (integrated only). No CUDA-capable device.
  `ctranslate2.get_cuda_device_count()` → `0`.
- RAM: ~23.8 GiB total (25,520,779,264 bytes)
- VRAM: Not applicable (no discrete GPU; integrated graphics report shared
  system memory, not dedicated VRAM, and are not usable by ctranslate2's
  CUDA path)
- Disk free (C:): ~18.2 GiB at time of benchmark-environment verification

## Real Runtime Verification (this cycle)

- `import faster_whisper, ctranslate2` — succeeds, no errors.
- Device resolution: `auto` → `cpu` (no CUDA device), `compute_type`
  `float16` (documented default) → downgraded to `int8` (CPU-supported).
  This resolution is now implemented without an undeclared `torch`
  dependency (previously `get_model()` called `import torch` unconditionally
  on `device="auto"`, which would have raised `ModuleNotFoundError` — never
  caught before because every existing unit test mocked `get_model`
  entirely; fixed to use `ctranslate2.get_cuda_device_count()` instead, see
  `tasks/P3-003-python-ffmpeg-fasterwhisper-provider.md` correction notes).
- Real model load (not mocked): `WhisperModel("turbo", device="cpu",
  compute_type="int8")` — loaded successfully. First load required
  downloading the model from Hugging Face Hub
  (`mobiuslabsgmbh/faster-whisper-large-v3-turbo`, ~1.5 GB); took 289.22
  seconds on this connection (dominated by download, not inference).
  Hugging Face Hub's default cache symlink mechanism failed with
  `OSError: [WinError 1314] A required privilege is not held by the client`
  (this machine cannot create filesystem symlinks without Developer Mode or
  elevation — confirmed independently) — worked around by setting
  `HF_HUB_DISABLE_SYMLINKS=1`, which makes the cache copy files instead of
  symlinking them. This is an environment note for whoever next runs this
  worker on a similar unprivileged Windows machine, not a code defect.
- Real inference (not mocked): ran `model.transcribe()` on a synthetic
  3-second 440 Hz sine tone (16 kHz mono PCM16, generated with
  `ffmpeg -f lavfi -i sine=...`, not real speech) with `vad_filter=True`.
  Result: `detected language: en` (default/fallback with no genuine speech
  present), `segments: 0` (VAD correctly found no speech in a pure tone).
  This proves the load → inference → response pipeline runs end-to-end on
  this machine; it is a mechanical smoke test only and carries no
  accuracy/WER/RTF meaning (no real speech, no comparative model run).

## Benchmark Script

`scripts/benchmark/benchmark_gate.py` — ready to execute; unchanged this
cycle. Confirmed importable and consistent with the now-verified
environment above.

### Usage

```bash
pip install -r worker/requirements.txt
python scripts/benchmark/benchmark_gate.py --media-dir ./benchmark-media --output benchmark-results.json
```

## Required Test Media

Representative workload must include:
- Bahasa Melayu sample
- English sample
- Chinese sample
- Tamil sample
- Mixed/code-switching sample

**Searched this cycle:** no `benchmark-media/` directory exists in the
repository, and no audio files (`.mp3`/`.wav`/`.m4a`/`.flac`/`.ogg`) exist
anywhere in the repository tree outside of `vendor/`/`node_modules/`/`.git/`
other than a single tiny test fixture
(`storage/app/private/media/.staging/.../test.mp3`, not representative
speech in any of the required languages). Per the retry instructions, this
is a STOP-and-report condition: no samples were fabricated, generated via
TTS, or otherwise substituted as a stand-in for real representative speech.

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

Not available — the actual comparative turbo-vs-large-v3 benchmark has not
been run, because no representative multilingual media exists to run it
against (see above). Nothing below this point is measured; do not treat
absence as a negative result for either model.

## Decision Rule

turbo remains preferred unless benchmark evidence shows
materially unacceptable degradation for RTFTT.

## Privacy

Representative user media must not be committed unless
explicitly approved.

## Next Step (HPO Decision Required)

The Python/faster-whisper/ctranslate2 environment blocker from cycle 1 is
now resolved (verified above). The remaining blocker is narrower:

1. HPO supplies representative sample media (or a path/location to obtain
   it) covering Bahasa Melayu, English, Chinese, Tamil, and a mixed/
   code-switching sample, to be placed in `benchmark-media/` (git-ignored,
   not committed) — then the benchmark script can run immediately against
   the now-verified environment, or
2. HPO exercises Option 2 from `DECISION_QUEUE.md`
   (DECISION-P3-BENCHMARK-GATE-001): explicitly waive/defer the gate and
   accept `turbo` as the interim model default, recorded durably.

The benchmark gate remains OPEN (DECISION-P3-BENCHMARK-GATE-001).
