#!/usr/bin/env python3
"""
Create additional mixed-language transition samples for H6 escalation remediation.

Existing: MIX-01 (ms→en), MIX-02 (en→cmn), MIX-03 (cmn→ta), MIX-04 (ms→en→cmn→ta)
New (8 samples):
  MIX-05: en → ms
  MIX-06: cmn → en
  MIX-07: ta → en
  MIX-08: en → ta
  MIX-09: ms → ta
  MIX-10: ta → ms
  MIX-11: en → ms → ta → cmn
  MIX-12: ta → cmn → en → ms

All are SYNTHETIC MULTILINGUAL TRANSITION SAMPLES (concatenated single-language
segments, explicitly labeled as such).
"""

import json
import subprocess
import wave
from pathlib import Path

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"
SAMPLE_RATE = 16000

# Source segments (use first sample from each language)
SEGMENTS = {
    "ms": "ms_my_1.wav",
    "en": "en_us_1.wav",
    "cmn": "cmn_hans_cn_1.wav",
    "ta": "ta_in_1.wav",
}

MIXES = [
    {
        "alias": "MIX-05",
        "description": "English to Bahasa Melayu transition",
        "source_languages": ["en", "ms"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
    {
        "alias": "MIX-06",
        "description": "Mandarin Chinese to English transition",
        "source_languages": ["cmn", "en"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
    {
        "alias": "MIX-07",
        "description": "Tamil to English transition",
        "source_languages": ["ta", "en"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
    {
        "alias": "MIX-08",
        "description": "English to Tamil transition",
        "source_languages": ["en", "ta"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
    {
        "alias": "MIX-09",
        "description": "Bahasa Melayu to Tamil transition",
        "source_languages": ["ms", "ta"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
    {
        "alias": "MIX-10",
        "description": "Tamil to Bahasa Melayu transition",
        "source_languages": ["ta", "ms"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
    {
        "alias": "MIX-11",
        "description": "English to Bahasa Melayu to Tamil to Mandarin transition",
        "source_languages": ["en", "ms", "ta", "cmn"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
    {
        "alias": "MIX-12",
        "description": "Tamil to Mandarin to English to Bahasa Melayu transition",
        "source_languages": ["ta", "cmn", "en", "ms"],
        "label": "SYNTHETIC MULTILINGUAL TRANSITION SAMPLE",
    },
]


def get_duration(wav_path: Path) -> float:
    with wave.open(str(wav_path), "rb") as wf:
        return wf.getnframes() / wf.getframerate()


def concat_wavs(input_paths: list[Path], output_path: Path) -> float:
    """Concatenate WAV files using ffmpeg."""
    inputs = []
    for p in input_paths:
        inputs.extend(["-i", str(p)])

    n = len(input_paths)
    filter_str = "".join(f"[{i}:a]" for i in range(n)) + f"concat=n={n}:v=0:a=1[out]"

    cmd = ["ffmpeg", "-y"] + inputs + [
        "-filter_complex", filter_str,
        "-map", "[out]",
        "-ar", str(SAMPLE_RATE), "-ac", "1", "-f", "wav",
        str(output_path),
    ]

    result = subprocess.run(cmd, capture_output=True, timeout=30)
    if result.returncode != 0:
        raise RuntimeError(f"ffmpeg failed: {result.stderr.decode()[:300]}")

    return get_duration(output_path)


def main():
    manifest_path = BENCHMARK_DIR / "manifest.json"
    with open(manifest_path, "r", encoding="utf-8") as f:
        manifest = json.load(f)

    existing_aliases = {s["alias"] for s in manifest["samples"]}

    for mix in MIXES:
        alias = mix["alias"]
        if alias in existing_aliases:
            print(f"Skipping {alias} (already exists)")
            continue

        source_files = [BENCHMARK_DIR / SEGMENTS[lang] for lang in mix["source_languages"]]
        output_path = BENCHMARK_DIR / f"{alias}.wav"

        print(f"Creating {alias}: {' + '.join(mix['source_languages'])}")

        # Verify source files exist
        missing = [sf for sf in source_files if not sf.exists()]
        if missing:
            print(f"  ERROR: Missing source files: {[sf.name for sf in missing]}")
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

    mixed_count = len([s for s in manifest["samples"] if s.get("config") == "mixed"])
    print(f"\nTotal mixed samples: {mixed_count}")
    print(f"Total corpus: {len(manifest['samples'])}")


if __name__ == "__main__":
    main()
