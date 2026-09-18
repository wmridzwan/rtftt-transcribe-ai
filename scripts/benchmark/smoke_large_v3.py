#!/usr/bin/env python3
"""Small real worker smoke test using the newly selected default model.

Exercises the worker's own config/transcription path (not the benchmark
harness) with the canonical default model, on a short real Tamil sample.
"""
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).parent.parent.parent
sys.path.insert(0, str(REPO_ROOT))

from worker import config
from worker.transcription import transcribe_audio

print(f"Canonical MODEL_NAME (resolved): {config.MODEL_NAME}")

audio = REPO_ROOT / "benchmark-media" / "ta_in_4.wav"
print(f"Smoke audio: {audio.name} ({audio.stat().st_size} bytes)")

result = transcribe_audio(audio, requested_language=None)
print(f"speech_detected: {result['speech_detected']}")
print(f"language: {result['language']}")
print(f"segment_count: {len(result['segments'])}")
print(f"contract_version: {result['contract_version']}")
for seg in result["segments"]:
    print(f"  seg {seg['segment_index']}: lang={seg['language']} "
          f"[{seg['start_seconds']}-{seg['end_seconds']}]")

assert result["speech_detected"] is True
assert result["contract_version"] == "1.0"
assert len(result["segments"]) > 0
print("\nLarge-v3 worker smoke test PASSED.")
