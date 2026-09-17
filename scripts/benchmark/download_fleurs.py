#!/usr/bin/env python3
"""
Download FLEURS benchmark samples via direct HTTP (no torch dependency).

Uses Hugging Face Hub API to fetch pre-encoded WAV files from the
google/fleurs Parquet-based dataset.
"""

import json
import os
import struct
import sys
import urllib.request
import wave
from pathlib import Path

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"
SAMPLES_PER_LANGUAGE = 3
SAMPLE_RATE = 16000

LANGUAGES = {
    "ms_my": "Bahasa Melayu",
    "en_us": "English",
    "cmn_hans_cn": "Mandarin Chinese",
    "ta_in": "Tamil",
}

# Deterministic sample indices
SAMPLE_INDICES = [0, 5, 10]

HF_API = "https://huggingface.co/api/datasets/google/fleurs"


def fetch_parquet_row(config: str, split: str, idx: int) -> dict:
    """Fetch a single row from the HF datasets API."""
    url = f"{HF_API}/viewer/{config}/{split}/parquet?offset={idx}&length=1"
    req = urllib.request.Request(url, headers={"Accept": "application/json"})
    with urllib.request.urlopen(req, timeout=30) as resp:
        data = json.loads(resp.read())
    return data


def download_fleurs_sample(config: str, lang_name: str, idx: int) -> dict | None:
    """Download a FLEURS sample using the datasets-server parquet endpoint."""
    print(f"  Fetching {config} sample at index {idx}...")

    url = f"https://datasets-server.huggingface.co/rows?dataset=google/fleurs&config={config}&split=validation&offset={idx}&length=1"
    req = urllib.request.Request(url, headers={"Accept": "application/json"})

    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            data = json.loads(resp.read())
    except Exception as e:
        print(f"  FAILED to fetch: {e}")
        return None

    if "rows" not in data or len(data["rows"]) == 0:
        print(f"  No rows returned")
        return None

    row = data["rows"][0]["row"]
    audio_data = row.get("audio", {})

    # audio can be a dict or a list with one dict
    if isinstance(audio_data, list) and len(audio_data) > 0:
        audio_data = audio_data[0]

    # Get audio bytes from the blob URL or inline
    audio_blob_url = audio_data.get("src")
    if audio_blob_url:
        audio_req = urllib.request.Request(audio_blob_url)
        with urllib.request.urlopen(audio_req, timeout=60) as resp:
            audio_bytes = resp.read()
        sample_rate = audio_data.get("sampling_rate", SAMPLE_RATE)
    else:
        print(f"  No audio blob URL found in row")
        return None

    # Write as WAV
    alias = f"{config}_{SAMPLE_INDICES.index(idx)+1}"
    audio_path = BENCHMARK_DIR / f"{alias}.wav"

    # The blob is typically in flac or wav format from HF
    # Write raw bytes to temp file first, then convert
    temp_path = BENCHMARK_DIR / f"{alias}_raw.bin"
    with open(temp_path, "wb") as f:
        f.write(audio_bytes)

    # Use ffmpeg to convert to 16kHz mono WAV
    import subprocess
    cmd = [
        "ffmpeg", "-y", "-i", str(temp_path),
        "-ar", str(SAMPLE_RATE), "-ac", "1", "-f", "wav",
        str(audio_path)
    ]
    result = subprocess.run(cmd, capture_output=True, timeout=30)
    temp_path.unlink(missing_ok=True)

    if result.returncode != 0:
        print(f"  ffmpeg conversion failed: {result.stderr.decode()[:200]}")
        return None

    # Get duration
    with wave.open(str(audio_path), "rb") as wf:
        frames = wf.getnframes()
        sr = wf.getframerate()
        duration = frames / sr

    transcription = row.get("transcription", "")
    info = {
        "alias": alias,
        "dataset": "google/fleurs",
        "config": config,
        "language_name": lang_name,
        "split": "validation",
        "sample_id": str(idx),
        "reference_transcription": transcription,
        "duration_seconds": round(duration, 3),
        "local_file": f"{alias}.wav",
        "license": "CC BY 4.0 (Google FLEURS)",
    }
    try:
        print(f"  OK: {alias} ({duration:.1f}s)")
    except UnicodeEncodeError:
        print(f"  OK: {alias} ({duration:.1f}s) [unicode suppressed]")
    return info


def main():
    BENCHMARK_DIR.mkdir(parents=True, exist_ok=True)

    manifest_path = BENCHMARK_DIR / "manifest.json"
    
    # Load existing manifest if resuming
    if manifest_path.exists():
        with open(manifest_path, "r", encoding="utf-8") as f:
            manifest = json.load(f)
        existing_aliases = {s["alias"] for s in manifest["samples"]}
    else:
        manifest = {
            "description": "RTFTT Phase 3 Benchmark Corpus (FLEURS)",
            "source": "google/fleurs",
            "license": "CC BY 4.0",
            "selection_method": "Deterministic indices [0, 5, 10] from validation split",
            "sample_rate": SAMPLE_RATE,
            "samples": [],
        }
        existing_aliases = set()

    for lang_code, lang_name in LANGUAGES.items():
        print(f"\n=== {lang_name} ({lang_code}) ===")
        for i, idx in enumerate(SAMPLE_INDICES):
            alias = f"{lang_code}_{i+1}"
            wav_path = BENCHMARK_DIR / f"{alias}.wav"
            if wav_path.exists():
                print(f"  Skipping {alias} (already downloaded)")
                continue
            info = download_fleurs_sample(lang_code, lang_name, idx)
            if info:
                manifest["samples"].append(info)
                # Save after each successful download
                with open(manifest_path, "w", encoding="utf-8") as f:
                    json.dump(manifest, f, indent=2, ensure_ascii=False)

    print(f"\n=== Done. {len(manifest['samples'])} samples in manifest. ===")


if __name__ == "__main__":
    main()
