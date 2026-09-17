#!/usr/bin/env python3
"""Build manifest.json from existing WAV files."""
import json
import wave
from pathlib import Path

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"
SAMPLE_RATE = 16000

LANGUAGES = {
    "ms_my": "Bahasa Melayu",
    "en_us": "English",
    "cmn_hans_cn": "Mandarin Chinese",
    "ta_in": "Tamil",
}

SAMPLE_INDICES = [0, 5, 10]

manifest = {
    "description": "RTFTT Phase 3 Benchmark Corpus (FLEURS)",
    "source": "google/fleurs",
    "license": "CC BY 4.0",
    "selection_method": "Deterministic indices [0, 5, 10] from validation split",
    "sample_rate": SAMPLE_RATE,
    "samples": [],
}

for lang_code, lang_name in LANGUAGES.items():
    for i, idx in enumerate(SAMPLE_INDICES):
        alias = f"{lang_code}_{i+1}"
        wav_path = BENCHMARK_DIR / f"{alias}.wav"
        if not wav_path.exists():
            print(f"MISSING: {alias}")
            continue
        with wave.open(str(wav_path), "rb") as wf:
            duration = wf.getnframes() / wf.getframerate()
        manifest["samples"].append({
            "alias": alias,
            "dataset": "google/fleurs",
            "config": lang_code,
            "language_name": lang_name,
            "split": "validation",
            "sample_id": str(idx),
            "reference_transcription": "",
            "duration_seconds": round(duration, 3),
            "local_file": f"{alias}.wav",
            "license": "CC BY 4.0 (Google FLEURS)",
        })
        print(f"OK: {alias} ({duration:.1f}s)")

manifest_path = BENCHMARK_DIR / "manifest.json"
with open(manifest_path, "w", encoding="utf-8") as f:
    json.dump(manifest, f, indent=2, ensure_ascii=False)

print(f"\nManifest written: {len(manifest['samples'])} samples")
