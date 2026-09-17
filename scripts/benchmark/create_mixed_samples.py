#!/usr/bin/env python3
"""
Create synthetic mixed-language concatenation samples for benchmark gate.

MIX-01: ms_my → en_us (Bahasa Melayu to English)
MIX-02: en_us → cmn_hans_cn (English to Mandarin)
MIX-03: cmn_hans_cn → ta_in (Mandarin to Tamil)
MIX-04: ms_my → en_us → cmn_hans_cn → ta_in (all four languages)

These are SYNTHETIC concatenated samples, explicitly labeled as such.
They test model language-transition behavior, not natural code-switching.
"""

import json
import subprocess
import wave
from pathlib import Path

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"
SAMPLE_RATE = 16000

# Source segments (first sample from each language)
SEGMENTS = {
    "ms_my": "ms_my_1.wav",
    "en_us": "en_us_1.wav",
    "cmn_hans_cn": "cmn_hans_cn_1.wav",
    "ta_in": "ta_in_1.wav",
}

MIXES = [
    {
        "alias": "MIX-01",
        "description": "Bahasa Melayu to English transition",
        "source_languages": ["ms_my", "en_us"],
        "label": "SYNTHETIC — concatenated single-language segments",
    },
    {
        "alias": "MIX-02",
        "description": "English to Mandarin Chinese transition",
        "source_languages": ["en_us", "cmn_hans_cn"],
        "label": "SYNTHETIC — concatenated single-language segments",
    },
    {
        "alias": "MIX-03",
        "description": "Mandarin Chinese to Tamil transition",
        "source_languages": ["cmn_hans_cn", "ta_in"],
        "label": "SYNTHETIC — concatenated single-language segments",
    },
    {
        "alias": "MIX-04",
        "description": "All four languages in sequence",
        "source_languages": ["ms_my", "en_us", "cmn_hans_cn", "ta_in"],
        "label": "SYNTHETIC — concatenated single-language segments",
    },
]


def get_duration(wav_path: Path) -> float:
    with wave.open(str(wav_path), "rb") as wf:
        return wf.getnframes() / wf.getframerate()


def concat_wavs(input_paths: list[Path], output_path: Path) -> float:
    """Concatenate WAV files using ffmpeg."""
    # Create ffmpeg filter for concatenation
    filter_parts = []
    inputs = []
    for p in input_paths:
        inputs.extend(["-i", str(p)])
    
    n = len(input_paths)
    filter_str = "".join(f"[{i}:a]" for i in range(n)) + f"concat=n={n}:v=0:a=1[out]"
    
    cmd = ["ffmpeg", "-y"] + inputs + [
        "-filter_complex", filter_str,
        "-map", "[out]",
        "-ar", str(SAMPLE_RATE), "-ac", "1", "-f", "wav",
        str(output_path)
    ]
    
    result = subprocess.run(cmd, capture_output=True, timeout=30)
    if result.returncode != 0:
        raise RuntimeError(f"ffmpeg failed: {result.stderr.decode()[:300]}")
    
    return get_duration(output_path)


def main():
    manifest_path = BENCHMARK_DIR / "manifest.json"
    with open(manifest_path, "r", encoding="utf-8") as f:
        manifest = json.load(f)

    for mix in MIXES:
        alias = mix["alias"]
        source_files = [BENCHMARK_DIR / SEGMENTS[lang] for lang in mix["source_languages"]]
        output_path = BENCHMARK_DIR / f"{alias}.wav"
        
        print(f"Creating {alias}: {' + '.join(mix['source_languages'])}")
        
        # Verify source files exist
        for sf in source_files:
            if not sf.exists():
                print(f"  ERROR: Missing source file {sf}")
                continue
        
        duration = concat_wavs(source_files, output_path)
        
        info = {
            "alias": alias,
            "dataset": "synthetic/concatenation",
            "config": "mixed",
            "language_name": mix["description"],
            "split": "N/A",
            "sample_id": "N/A",
            "reference_transcription": "",
            "duration_seconds": round(duration, 3),
            "local_file": f"{alias}.wav",
            "license": "N/A (synthetic)",
            "source_languages": mix["source_languages"],
            "source_files": [sf.name for sf in source_files],
            "synthetic_label": mix["label"],
        }
        manifest["samples"].append(info)
        print(f"  OK: {alias} ({duration:.1f}s)")

    with open(manifest_path, "w", encoding="utf-8") as f:
        json.dump(manifest, f, indent=2, ensure_ascii=False)
    
    print(f"\nDone. Total samples: {len(manifest['samples'])}")


if __name__ == "__main__":
    main()
