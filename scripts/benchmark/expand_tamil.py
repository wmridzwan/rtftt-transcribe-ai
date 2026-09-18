#!/usr/bin/env python3
"""
Expand Tamil benchmark corpus for H6 escalation remediation.

Selection rule: Deterministic indices from FLEURS ta_in validation split.
Existing: indices [0, 5, 10] (ta_in_1, ta_in_2, ta_in_3)
New: indices [15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70]
Total: 15 Tamil samples

All indices selected before any model execution. No cherry-picking.
"""

import json
import subprocess
import urllib.request
import wave
from pathlib import Path

BENCHMARK_DIR = Path(__file__).parent.parent.parent / "benchmark-media"
SAMPLE_RATE = 16000

# Deterministic selection rule (recorded before execution)
EXISTING_TAMIL_INDICES = [0, 5, 10]
NEW_TAMIL_INDICES = [15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70]
ALL_TAMIL_INDICES = EXISTING_TAMIL_INDICES + NEW_TAMIL_INDICES


def download_sample(config: str, idx: int, alias: str) -> dict | None:
    """Download a FLEURS sample via HF datasets server API."""
    url = f"https://datasets-server.huggingface.co/rows?dataset=google/fleurs&config={config}&split=validation&offset={idx}&length=1"
    req = urllib.request.Request(url, headers={"Accept": "application/json"})

    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            data = json.loads(resp.read())
    except Exception as e:
        print(f"  FAILED to fetch {alias}: {e}")
        return None

    if "rows" not in data or len(data["rows"]) == 0:
        print(f"  No rows returned for {alias}")
        return None

    row = data["rows"][0]["row"]
    audio_data = row.get("audio", {})
    if isinstance(audio_data, list) and len(audio_data) > 0:
        audio_data = audio_data[0]

    audio_blob_url = audio_data.get("src")
    if not audio_blob_url:
        print(f"  No audio blob URL for {alias}")
        return None

    # Download audio
    audio_req = urllib.request.Request(audio_blob_url)
    with urllib.request.urlopen(audio_req, timeout=60) as resp:
        audio_bytes = resp.read()

    # Write raw and convert with ffmpeg
    temp_path = BENCHMARK_DIR / f"{alias}_raw.bin"
    with open(temp_path, "wb") as f:
        f.write(audio_bytes)

    audio_path = BENCHMARK_DIR / f"{alias}.wav"
    cmd = [
        "ffmpeg", "-y", "-i", str(temp_path),
        "-ar", str(SAMPLE_RATE), "-ac", "1", "-f", "wav",
        str(audio_path),
    ]
    result = subprocess.run(cmd, capture_output=True, timeout=30)
    temp_path.unlink(missing_ok=True)

    if result.returncode != 0:
        print(f"  ffmpeg conversion failed for {alias}")
        return None

    # Get duration and transcription
    with wave.open(str(audio_path), "rb") as wf:
        duration = wf.getnframes() / wf.getframerate()

    transcription = row.get("transcription", "")

    info = {
        "alias": alias,
        "dataset": "google/fleurs",
        "config": config,
        "language_name": "Tamil",
        "split": "validation",
        "sample_id": str(idx),
        "reference_transcription": transcription,
        "duration_seconds": round(duration, 3),
        "local_file": f"{alias}.wav",
        "license": "CC BY 4.0 (Google FLEURS)",
    }
    return info


def main():
    BENCHMARK_DIR.mkdir(parents=True, exist_ok=True)

    # Load existing manifest
    manifest_path = BENCHMARK_DIR / "manifest.json"
    if manifest_path.exists():
        with open(manifest_path, "r", encoding="utf-8") as f:
            manifest = json.load(f)
    else:
        manifest = {
            "description": "RTFTT Phase 3 Benchmark Corpus (FLEURS)",
            "source": "google/fleurs",
            "license": "CC BY 4.0",
            "sample_rate": SAMPLE_RATE,
            "samples": [],
        }

    existing_aliases = {s["alias"] for s in manifest["samples"]}

    # Download new Tamil samples
    print("=== Expanding Tamil corpus ===")
    print(f"Selection rule: deterministic indices {NEW_TAMIL_INDICES}")
    print(f"Existing Tamil indices: {EXISTING_TAMIL_INDICES}")
    print(f"Total target: {len(ALL_TAMIL_INDICES)} samples")

    for i, idx in enumerate(NEW_TAMIL_INDICES):
        alias = f"ta_in_{3 + i + 1}"  # ta_in_4 through ta_in_15
        if alias in existing_aliases:
            wav_path = BENCHMARK_DIR / f"{alias}.wav"
            if wav_path.exists():
                print(f"  Skipping {alias} (already exists)")
                continue

        info = download_sample("ta_in", idx, alias)
        if info:
            manifest["samples"].append(info)
            # Save incrementally
            with open(manifest_path, "w", encoding="utf-8") as f:
                json.dump(manifest, f, indent=2, ensure_ascii=False)
            print(f"  OK: {alias} ({info['duration_seconds']:.1f}s, idx={idx})")

    print(f"\nTotal Tamil samples: {len([s for s in manifest['samples'] if s['config'] == 'ta_in'])}")
    print(f"Total corpus samples: {len(manifest['samples'])}")


if __name__ == "__main__":
    main()
